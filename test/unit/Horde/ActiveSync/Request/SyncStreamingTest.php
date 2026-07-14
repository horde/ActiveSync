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
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Request_Sync;
use Horde_ActiveSync_Wbxml;
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
