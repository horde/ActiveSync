<?php

/**
 * Unit tests for streaming ActiveSync Sync responses.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project
 * @package   ActiveSync
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use Horde_ActiveSync_Connector_Importer;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Message_Base;
use Horde_ActiveSync_Request_Sync;
use Horde_ActiveSync_State_Base;
use Horde_ActiveSync_Wbxml;
use Horde_ActiveSync_Wbxml_Decoder;
use Horde_ActiveSync_Wbxml_Encoder;
use Horde_Log_Handler_Null;
use Horde_Stream_Temp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Horde_ActiveSync_Request_Sync::class)]
#[CoversClass(Horde_ActiveSync_Wbxml_Encoder::class)]
class SyncStreamingTest extends TestCase
{
    public function testStreamingWindowSizeCapsWindow()
    {
        $sync = $this->_syncRequestWithoutConstructor();

        // Cap smaller than window wins.
        $this->assertSame(10, $this->_invokeSyncMethod(
            $sync,
            '_streamingMaxWindowSize',
            [50, true, 10]
        ));

        // Window smaller than cap wins.
        $this->assertSame(5, $this->_invokeSyncMethod(
            $sync,
            '_streamingMaxWindowSize',
            [5, true, 10]
        ));

        // No effect when streaming is off.
        $this->assertSame(50, $this->_invokeSyncMethod(
            $sync,
            '_streamingMaxWindowSize',
            [50, false, 10]
        ));

        // Cap disabled.
        $this->assertSame(50, $this->_invokeSyncMethod(
            $sync,
            '_streamingMaxWindowSize',
            [50, true, 0]
        ));
    }

    public function testGuardExceeded()
    {
        $sync = $this->_syncRequestWithoutConstructor();

        $this->assertTrue($this->_invokeSyncMethod(
            $sync,
            '_isGuardExceeded',
            [microtime(true) - 20, 10]
        ));

        $this->assertFalse($this->_invokeSyncMethod(
            $sync,
            '_isGuardExceeded',
            [microtime(true) - 5, 10]
        ));

        // Disabled guard never triggers.
        $this->assertFalse($this->_invokeSyncMethod(
            $sync,
            '_isGuardExceeded',
            [microtime(true) - 3600, 0]
        ));
    }

    public function testUseSyncCommandsBufferDisabledWhenStreaming()
    {
        $sync = $this->_syncRequestWithoutConstructor();

        // Buffering would be used without streaming...
        $this->assertTrue($this->_invokeSyncMethod(
            $sync,
            '_useSyncCommandsBuffer',
            [25, 10, 50, 0, 100, false]
        ));

        // ...but never when streaming is enabled.
        $this->assertFalse($this->_invokeSyncMethod(
            $sync,
            '_useSyncCommandsBuffer',
            [25, 10, 50, 0, 100, true]
        ));
    }

    public function testMoreAvailablePrecedesCommandsWhenStreaming()
    {
        $main = fopen('php://memory', 'wb+');
        $encoder = $this->_encoder($main);

        // Simulate the streaming folder output: truncation is known before
        // the Commands section starts (count-capped window), so
        // MOREAVAILABLE is emitted inline and Commands stream unbuffered.
        $encoder->startWBXML();
        $encoder->startTag(Horde_ActiveSync::SYNC_SYNCHRONIZE);
        $encoder->startTag(Horde_ActiveSync::SYNC_FOLDERS);
        $encoder->startTag(Horde_ActiveSync::SYNC_FOLDER);
        $encoder->startTag(Horde_ActiveSync::SYNC_MOREAVAILABLE, false, true);
        $encoder->startTag(Horde_ActiveSync::SYNC_COMMANDS);
        $encoder->startTag(Horde_ActiveSync::SYNC_ADD);
        $encoder->startTag(Horde_ActiveSync::SYNC_SERVERENTRYID);
        $encoder->content('1');
        $encoder->endTag();
        $encoder->endTag();
        $encoder->endTag();
        $encoder->endTag();
        $encoder->endTag();
        $encoder->endTag();

        rewind($main);
        $bytes = stream_get_contents($main);

        // MoreAvailable (0x14, empty tag) must precede Commands
        // (0x16 | content flag 0x40 = 0x56) in the byte stream.
        $moreAvailablePos = strpos($bytes, chr(0x14));
        $commandsPos = strpos($bytes, chr(0x56));
        $this->assertNotFalse($moreAvailablePos);
        $this->assertNotFalse($commandsPos);
        $this->assertLessThan($commandsPos, $moreAvailablePos);
    }

    public function testFlushOutputKeepsStreamIntact()
    {
        $main = fopen('php://memory', 'wb+');
        $encoder = $this->_encoder($main);

        $encoder->startWBXML();
        $encoder->flushOutput();
        $encoder->startTag(Horde_ActiveSync::SYNC_SYNCHRONIZE);
        $encoder->startTag(Horde_ActiveSync::SYNC_STATUS);
        $encoder->content('1');
        $encoder->flushOutput();
        $encoder->endTag();
        $encoder->endTag();
        $encoder->flushOutput();

        rewind($main);
        $bytes = stream_get_contents($main);

        // WBXML header + Synchronize/Status/'1'/ends, nothing lost or
        // duplicated by flushing.
        $this->assertSame(
            chr(0x03) . chr(0x01) . chr(106) . chr(0x00)
                . chr(0x45) . chr(0x4e) . chr(0x03) . '1' . chr(0x00)
                . chr(0x01) . chr(0x01),
            $bytes
        );
    }

    public function testFlushOutputSafeOnSwappedBufferStream()
    {
        $main = fopen('php://memory', 'wb+');
        $encoder = $this->_encoder($main);
        $encoder->startWBXML();

        $buffer = new Horde_Stream_Temp();
        $saved = $encoder->swapOutputStream($buffer);
        $encoder->startTag(Horde_ActiveSync::SYNC_COMMANDS);
        $encoder->startTag(Horde_ActiveSync::SYNC_ADD, false, true);
        $encoder->flushOutput();
        $encoder->endTag();
        $bufferLength = $buffer->length();
        $encoder->swapOutputStream($saved);

        $this->assertGreaterThan(0, $bufferLength);
    }

    public function testKeepAliveTokensAreTransparentToDecoder()
    {
        $main = fopen('php://memory', 'wb+');
        $encoder = $this->_encoder($main);

        $encoder->startWBXML();
        $encoder->keepAlive();
        $encoder->startTag(Horde_ActiveSync::SYNC_SYNCHRONIZE);
        $encoder->startTag(Horde_ActiveSync::SYNC_STATUS);
        $encoder->keepAlive();
        $encoder->content('1');
        $encoder->endTag();
        $encoder->keepAlive();
        $encoder->endTag();

        rewind($main);
        $bytes = stream_get_contents($main);

        // Keep-alive is a redundant SWITCH_PAGE to the active code page.
        $this->assertSame(3, substr_count($bytes, chr(0x00) . chr(0x00)));

        // A WBXML token parser must consume the document unchanged.
        rewind($main);
        $decoder = new Horde_ActiveSync_Wbxml_Decoder($main);
        $decoder->readWbxmlHeader();
        $this->assertNotFalse($decoder->getElementStartTag(Horde_ActiveSync::SYNC_SYNCHRONIZE));
        $this->assertNotFalse($decoder->getElementStartTag(Horde_ActiveSync::SYNC_STATUS));
        $this->assertSame('1', $decoder->getElementContent());
        $this->assertNotFalse($decoder->getElementEndTag());
        $this->assertNotFalse($decoder->getElementEndTag());
    }

    public function testWbxmlHeaderIsEmittedExactlyOnce()
    {
        $main = fopen('php://memory', 'wb+');
        $encoder = $this->_encoder($main);

        // keepAlive() before startWBXML() must pre-send the header;
        // startWBXML() must not emit it a second time.
        $encoder->keepAlive();
        $encoder->startWBXML();
        $encoder->startTag(Horde_ActiveSync::SYNC_SYNCHRONIZE);
        $encoder->startTag(Horde_ActiveSync::SYNC_STATUS);
        $encoder->content('1');
        $encoder->endTag();
        $encoder->endTag();

        rewind($main);
        $bytes = stream_get_contents($main);

        $header = chr(0x03) . chr(0x01) . chr(106) . chr(0x00);
        $this->assertSame(0, strpos($bytes, $header));
        $this->assertSame(1, substr_count($bytes, $header));
    }

    public function testRunDeferredSyncCommandsImportsAndEmitsKeepAlives()
    {
        $sync = $this->_syncRequestWithoutConstructor();
        $ref = new ReflectionClass($sync);

        $appdata = $this->createMock(Horde_ActiveSync_Message_Base::class);

        $importer = $this->createMock(Horde_ActiveSync_Connector_Importer::class);
        $importer->expects($this->once())->method('init');
        $importer->expects($this->exactly(2))
            ->method('importMessageChange')
            ->willReturnOnConsecutiveCalls(
                // MODIFY result: stat array.
                ['id' => '100', 'mod' => 1],
                // ADD result: stat array with new server uid.
                ['id' => '200', 'mod' => 1]
            );

        $as = $this->createMock(Horde_ActiveSync::class);
        $as->method('getImporter')->willReturn($importer);

        $main = fopen('php://memory', 'wb+');
        $encoder = $this->_encoder($main);

        foreach ([
            '_activeSync' => $as,
            '_device' => $this->createMock(\Horde_ActiveSync_Device::class),
            '_state' => $this->createMock(Horde_ActiveSync_State_Base::class),
            '_encoder' => $encoder,
            '_logger' => new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null()),
            '_deferredCommands' => [
                'F1' => [
                    'commands' => [
                        [
                            'type' => Horde_ActiveSync::SYNC_MODIFY,
                            'serverid' => '100',
                            'clientid' => false,
                            'appdata' => $appdata,
                        ],
                        [
                            'type' => Horde_ActiveSync::SYNC_ADD,
                            'serverid' => false,
                            'clientid' => 'client-1',
                            'appdata' => $appdata,
                        ],
                    ],
                ],
            ],
        ] as $property => $value) {
            $prop = $ref->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($sync, $value);
        }

        $collection = [
            'id' => 'F1',
            'class' => Horde_ActiveSync::CLASS_EMAIL,
            'synckey' => '{uuid}5',
            'conflict' => Horde_ActiveSync::CONFLICT_OVERWRITE_PIM,
            'clientids' => [],
        ];

        $method = $ref->getMethod('_runDeferredSyncCommands');
        $method->setAccessible(true);
        $collectionArgs = [&$collection];
        $method->invokeArgs($sync, $collectionArgs);

        // Import results recorded like the inline (non-streaming) path.
        $this->assertTrue($collection['importedchanges']);
        $this->assertSame(['100'], $collection['modifiedids']);
        $this->assertSame(['client-1' => '200'], $collection['clientids']);

        // Queue consumed.
        $deferredProp = $ref->getProperty('_deferredCommands');
        $deferredProp->setAccessible(true);
        $this->assertSame([], $deferredProp->getValue($sync));

        // One keep-alive per imported command reached the output stream.
        rewind($main);
        $bytes = stream_get_contents($main);
        $this->assertSame(2, substr_count($bytes, chr(0x00) . chr(0x00)));
    }

    public function testRunDeferredSyncCommandsNoopWithoutQueue()
    {
        $sync = $this->_syncRequestWithoutConstructor();
        $ref = new ReflectionClass($sync);

        $collection = ['id' => 'F1'];
        $method = $ref->getMethod('_runDeferredSyncCommands');
        $method->setAccessible(true);
        $collectionArgs = [&$collection];
        $method->invokeArgs($sync, $collectionArgs);

        $this->assertSame(['id' => 'F1'], $collection);
    }

    protected function _encoder($stream)
    {
        $encoder = new Horde_ActiveSync_Wbxml_Encoder(
            $stream,
            Horde_ActiveSync_Wbxml::LOG_PROTOCOL
        );
        $encoder->setLogger(
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );

        return $encoder;
    }

    protected function _syncRequestWithoutConstructor()
    {
        $ref = new ReflectionClass(Horde_ActiveSync_Request_Sync::class);
        return $ref->newInstanceWithoutConstructor();
    }

    protected function _invokeSyncMethod($object, $method, array $args = [])
    {
        $ref = new ReflectionClass($object);
        $method = $ref->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
