<?php

/**
 * Unit tests for MoveItems handling of stale folder UIDs.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project (http://www.horde.org/)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use Horde_ActiveSync_Connector_Importer;
use Horde_ActiveSync_Driver_Base;
use Horde_ActiveSync_Exception_FolderGone;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Request_MoveItems;
use Horde_ActiveSync_State_Base;
use Horde_ActiveSync_Wbxml;
use Horde_ActiveSync_Wbxml_Decoder;
use Horde_ActiveSync_Wbxml_Encoder;
use Horde_Controller_Request_Mock;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

#[CoversClass(Horde_ActiveSync_Request_MoveItems::class)]
class MoveItemsFolderGoneTest extends TestCase
{
    protected function tearDown(): void
    {
        $deviceRef = new ReflectionProperty(Horde_ActiveSync::class, '_device');
        $deviceRef->setAccessible(true);
        $deviceRef->setValue(null, null);
        parent::tearDown();
    }

    public function testMoveItemsReturnsInvalidSrcWhenSourceFolderIsGone()
    {
        $fixture = $this->_createHandler();
        $fixture->importer->expects($this->once())
            ->method('init')
            ->willThrowException(new Horde_ActiveSync_Exception_FolderGone('Folder not found in cache.'));

        $this->_writeWbxml($fixture->input, function (Horde_ActiveSync_Wbxml_Encoder $encoder) {
            $encoder->startTag(Horde_ActiveSync_Request_MoveItems::MOVES);
            $encoder->startTag(Horde_ActiveSync_Request_MoveItems::MOVE);
            $encoder->startTag(Horde_ActiveSync_Request_MoveItems::SRCMSGID);
            $encoder->content('169462');
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync_Request_MoveItems::SRCFLDID);
            $encoder->content('F85953279');
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync_Request_MoveItems::DSTFLDID);
            $encoder->content('F37c56728');
            $encoder->endTag();
            $encoder->endTag();
            $encoder->endTag();
        });
        $fixture->decoder->readWbxmlHeader();

        $method = new ReflectionMethod(Horde_ActiveSync_Request_MoveItems::class, '_handle');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($fixture->handler));

        $response = $this->_decodeMoveItemsResponse(
            $this->_readStream($fixture->output)
        );
        $this->assertSame(Horde_ActiveSync_Request_MoveItems::STATUS_INVALID_SRC, $response['status']);
        $this->assertSame('169462', $response['srcMsgId']);
    }

    public function testMoveItemsReturnsInvalidSrcWhenDestinationFolderIsGone()
    {
        $fixture = $this->_createHandler();
        $fixture->importer->expects($this->once())->method('init');
        $fixture->importer->expects($this->once())
            ->method('importMessageMove')
            ->willThrowException(new Horde_ActiveSync_Exception_FolderGone('Folder not found in cache.'));

        $this->_writeWbxml($fixture->input, function (Horde_ActiveSync_Wbxml_Encoder $encoder) {
            $encoder->startTag(Horde_ActiveSync_Request_MoveItems::MOVES);
            $encoder->startTag(Horde_ActiveSync_Request_MoveItems::MOVE);
            $encoder->startTag(Horde_ActiveSync_Request_MoveItems::SRCMSGID);
            $encoder->content('169462');
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync_Request_MoveItems::SRCFLDID);
            $encoder->content('F85953279');
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync_Request_MoveItems::DSTFLDID);
            $encoder->content('F37c56728');
            $encoder->endTag();
            $encoder->endTag();
            $encoder->endTag();
        });
        $fixture->decoder->readWbxmlHeader();

        $method = new ReflectionMethod(Horde_ActiveSync_Request_MoveItems::class, '_handle');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($fixture->handler));

        $response = $this->_decodeMoveItemsResponse(
            $this->_readStream($fixture->output)
        );
        $this->assertSame(Horde_ActiveSync_Request_MoveItems::STATUS_INVALID_SRC, $response['status']);
    }

    /**
     * @return object{
     *   handler: Horde_ActiveSync_Request_MoveItems,
     *   input: resource,
     *   output: resource,
     *   importer: Horde_ActiveSync_Connector_Importer&\PHPUnit\Framework\MockObject\MockObject,
     *   state: Horde_ActiveSync_State_Base&\PHPUnit\Framework\MockObject\MockObject
     * }
     */
    protected function _createHandler(): object
    {
        $logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());
        $input = fopen('php://memory', 'wb+');
        $decoder = new Horde_ActiveSync_Wbxml_Decoder($input);

        $output = fopen('php://memory', 'wb+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($output);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $importer = $this->createMock(Horde_ActiveSync_Connector_Importer::class);

        $request = new Horde_Controller_Request_Mock([
            'get' => [
                'Cmd' => 'MoveItems',
                'DeviceId' => 'TESTDEVICE',
            ],
            'server' => [
                'HTTP_MS_ASPROTOCOLVERSION' => Horde_ActiveSync::VERSION_SIXTEENONE,
            ],
        ]);

        $server = $this->getMockBuilder(Horde_ActiveSync::class)
            ->setConstructorArgs([$driver, $decoder, $encoder, $state, $request])
            ->onlyMethods(['getImporter'])
            ->getMock();
        $server->method('getImporter')->willReturn($importer);

        $device = new \Horde_ActiveSync_Device($state);
        $device->id = 'TESTDEVICE';
        $device->version = Horde_ActiveSync::VERSION_SIXTEENONE;
        $deviceRef = new ReflectionProperty(Horde_ActiveSync::class, '_device');
        $deviceRef->setAccessible(true);
        $deviceRef->setValue(null, $device);

        $handler = new Horde_ActiveSync_Request_MoveItems($server);
        $handler->setLogger($logger);

        return (object) [
            'handler' => $handler,
            'decoder' => $decoder,
            'input' => $input,
            'output' => $output,
            'importer' => $importer,
            'state' => $state,
        ];
    }

    protected function _writeWbxml($stream, callable $writer): void
    {
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($stream);
        $encoder->startWBXML();
        $writer($encoder);
        rewind($stream);
    }

    /**
     * @return array{status: ?int, srcMsgId: ?string}
     */
    protected function _decodeMoveItemsResponse(string $wbxml): array
    {
        $stream = fopen('php://memory', 'wb+');
        fwrite($stream, $wbxml);
        rewind($stream);

        $decoder = new Horde_ActiveSync_Wbxml_Decoder($stream);
        $decoder->readWbxmlHeader();
        $decoder->getElementStartTag(Horde_ActiveSync_Request_MoveItems::MOVES);
        $decoder->getElementStartTag(Horde_ActiveSync_Request_MoveItems::RESPONSE);

        $response = [
            'status' => null,
            'srcMsgId' => null,
        ];

        while (($child = $decoder->peek()) !== false
            && $child[Horde_ActiveSync_Wbxml::EN_TYPE] === Horde_ActiveSync_Wbxml::EN_TYPE_STARTTAG
        ) {
            $tag = $child[Horde_ActiveSync_Wbxml::EN_TAG];
            $decoder->getElementStartTag($tag);
            $content = $decoder->getElementContent();
            $decoder->getElementEndTag();

            if ($tag === Horde_ActiveSync_Request_MoveItems::STATUS) {
                $response['status'] = (int) $content;
            } elseif ($tag === Horde_ActiveSync_Request_MoveItems::SRCMSGID) {
                $response['srcMsgId'] = $content;
            }
        }

        $decoder->getElementEndTag();
        $decoder->getElementEndTag();
        fclose($stream);

        return $response;
    }

    protected function _readStream($stream): string
    {
        rewind($stream);
        return stream_get_contents($stream);
    }
}
