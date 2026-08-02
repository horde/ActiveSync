<?php

/**
 * Unit tests for the PING response when a parallel request has updated the
 * sync cache (COLLECTION_ERR_STALE).
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
use Horde_ActiveSync_Collections;
use Horde_ActiveSync_Driver_Base;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Request_Ping;
use Horde_ActiveSync_State_Base;
use Horde_ActiveSync_Wbxml_Decoder;
use Horde_ActiveSync_Wbxml_Encoder;
use Horde_Controller_Request_Mock;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

#[CoversClass(Horde_ActiveSync_Request_Ping::class)]
class PingStaleCacheResponseTest extends TestCase
{
    protected function tearDown(): void
    {
        $deviceRef = new ReflectionProperty(Horde_ActiveSync::class, '_device');
        $deviceRef->setAccessible(true);
        $deviceRef->setValue(null, null);
        parent::tearDown();
    }

    /**
     * A stale sync cache during PING (a parallel SYNC took over the pending
     * changes) must NOT produce an empty HTTP body. MS-ASCMD requires every
     * PING response to carry a status, and clients like Nine treat a 0-byte
     * response as a dead connection ("No connection RETRY") and back off.
     */
    public function testStaleCacheDuringPingSendsStatusNoChanges()
    {
        $collections = $this->createMock(Horde_ActiveSync_Collections::class);
        $collections->method('getHeartbeat')->willReturn(0);
        $collections->method('haveHierarchy')->willReturn(true);
        $collections->expects($this->once())
            ->method('pollForChanges')
            ->willReturn(Horde_ActiveSync_Collections::COLLECTION_ERR_STALE);

        $fixture = $this->_createHandler($collections);

        // Mirror the request Nine sends: heartbeat plus explicit folders.
        $this->_writeWbxml($fixture->input, function (Horde_ActiveSync_Wbxml_Encoder $encoder) {
            $encoder->startTag(Horde_ActiveSync_Request_Ping::PING);
            $encoder->startTag(Horde_ActiveSync_Request_Ping::HEARTBEATINTERVAL);
            $encoder->content('240');
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync_Request_Ping::FOLDERS);
            foreach (['Fb8e0b27e', 'F50317d62'] as $folderid) {
                $encoder->startTag(Horde_ActiveSync_Request_Ping::FOLDER);
                $encoder->startTag(Horde_ActiveSync_Request_Ping::SERVERENTRYID);
                $encoder->content($folderid);
                $encoder->endTag();
                $encoder->startTag(Horde_ActiveSync_Request_Ping::FOLDERTYPE);
                $encoder->content('Email');
                $encoder->endTag();
                $encoder->endTag();
            }
            $encoder->endTag();
            $encoder->endTag();
        });
        $fixture->decoder->readWbxmlHeader();

        $method = new ReflectionMethod(Horde_ActiveSync_Request_Ping::class, '_handle');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($fixture->handler));

        $wbxml = $this->_readStream($fixture->output);
        $this->assertNotSame('', $wbxml, 'PING must never return an empty body.');
        $this->assertSame(
            Horde_ActiveSync_Request_Ping::STATUS_NOCHANGES,
            $this->_decodePingStatus($wbxml)
        );
    }

    /**
     * @return object{
     *   handler: Horde_ActiveSync_Request_Ping,
     *   decoder: Horde_ActiveSync_Wbxml_Decoder,
     *   input: resource,
     *   output: resource
     * }
     */
    protected function _createHandler(Horde_ActiveSync_Collections $collections): object
    {
        $logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());
        $input = fopen('php://memory', 'wb+');
        $decoder = new Horde_ActiveSync_Wbxml_Decoder($input);

        $output = fopen('php://memory', 'wb+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($output);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('getHeartbeatConfig')->willReturn([
            'waitinterval' => 5,
            'heartbeatmin' => 60,
            'heartbeatmax' => 3540,
            'heartbeatdefault' => 480,
        ]);
        $state = $this->createMock(Horde_ActiveSync_State_Base::class);

        $request = new Horde_Controller_Request_Mock([
            'get' => [
                'Cmd' => 'Ping',
                'DeviceId' => 'TESTDEVICE',
            ],
            'server' => [
                'HTTP_MS_ASPROTOCOLVERSION' => Horde_ActiveSync::VERSION_SIXTEEN,
            ],
        ]);

        $server = $this->getMockBuilder(Horde_ActiveSync::class)
            ->setConstructorArgs([$driver, $decoder, $encoder, $state, $request])
            ->onlyMethods(['getCollectionsObject', 'checkGlobalError'])
            ->getMock();
        $server->method('getCollectionsObject')->willReturn($collections);
        $server->method('checkGlobalError')->willReturn(false);

        $device = new \Horde_ActiveSync_Device($state);
        $device->id = 'TESTDEVICE';
        $device->version = Horde_ActiveSync::VERSION_SIXTEEN;
        $deviceRef = new ReflectionProperty(Horde_ActiveSync::class, '_device');
        $deviceRef->setAccessible(true);
        $deviceRef->setValue(null, $device);

        $handler = new Horde_ActiveSync_Request_Ping($server);
        $handler->setLogger($logger);

        return (object) [
            'handler' => $handler,
            'decoder' => $decoder,
            'input' => $input,
            'output' => $output,
        ];
    }

    protected function _writeWbxml($stream, callable $writer): void
    {
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($stream);
        $encoder->startWBXML();
        $writer($encoder);
        rewind($stream);
    }

    protected function _decodePingStatus(string $wbxml): int
    {
        $stream = fopen('php://memory', 'wb+');
        fwrite($stream, $wbxml);
        rewind($stream);

        $decoder = new Horde_ActiveSync_Wbxml_Decoder($stream);
        $decoder->readWbxmlHeader();
        $this->assertNotFalse(
            $decoder->getElementStartTag(Horde_ActiveSync_Request_Ping::PING)
        );
        $this->assertNotFalse(
            $decoder->getElementStartTag(Horde_ActiveSync_Request_Ping::STATUS)
        );
        $status = (int) $decoder->getElementContent();
        $decoder->getElementEndTag();
        $decoder->getElementEndTag();
        fclose($stream);

        return $status;
    }

    protected function _readStream($stream): string
    {
        rewind($stream);
        return stream_get_contents($stream);
    }
}
