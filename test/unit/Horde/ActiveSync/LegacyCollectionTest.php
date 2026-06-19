<?php

/**
 * Unit tests for deprecated CreateCollection/DeleteCollection/MoveCollection
 * command handling.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use Horde_ActiveSync_Collections;
use Horde_ActiveSync_Connector_Importer;
use Horde_ActiveSync_Driver_Base;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Message_Folder;
use Horde_ActiveSync_Request_FolderCreate;
use Horde_ActiveSync_Request_LegacyCollection;
use Horde_ActiveSync_State_Base;
use Horde_ActiveSync_Wbxml;
use Horde_ActiveSync_Wbxml_Decoder;
use Horde_ActiveSync_Wbxml_Encoder;
use Horde_Controller_Request_Mock;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Horde_ActiveSync_Request_LegacyCollection::class)]
class LegacyCollectionTest extends TestCase
{
    private const SYNCKEY = '{00000000-0000-0000-0000-000000000001}1';
    private const NEWSYNCKEY = '{00000000-0000-0000-0000-000000000001}2';

    protected function tearDown(): void
    {
        $deviceRef = new ReflectionProperty(Horde_ActiveSync::class, '_device');
        $deviceRef->setAccessible(true);
        $deviceRef->setValue(null, null);
        parent::tearDown();
    }
    public function testLegacyCreateCollectionReturnsFolderEnvelopeWithServerEntryId()
    {
        $folder = new Horde_ActiveSync_Message_Folder();
        $folder->serverid = 'F99';

        $fixture = $this->_createHandler('CreateCollection');
        $this->_mockSuccessfulFolderCreate($fixture, $folder, 'Archive');

        $this->_writeWbxml($fixture->input, function (Horde_ActiveSync_Wbxml_Encoder $encoder) {
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_FOLDER);
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_SYNCKEY);
            $encoder->content(self::SYNCKEY);
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_PARENTID);
            $encoder->content('0');
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_DISPLAYNAME);
            $encoder->content('Archive');
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_TYPE);
            $encoder->content('12');
            $encoder->endTag();
            $encoder->endTag();
        });
        $fixture->decoder->readWbxmlHeader();

        $this->assertTrue($fixture->handler->handle());

        $response = $this->_decodeLegacyFolderResponse(
            $this->_readStream($fixture->output)
        );
        $this->assertSame(Horde_ActiveSync_Request_FolderCreate::STATUS_SUCCESS, $response['status']);
        $this->assertSame(self::NEWSYNCKEY, $response['syncKey']);
        $this->assertSame('F99', $response['serverEntryId']);
    }

    public function testLegacyDeleteCollectionReturnsStatusAndSyncKeyOnly()
    {
        $fixture = $this->_createHandler('DeleteCollection');
        $this->_mockSuccessfulFolderDelete($fixture, 'F42');

        $this->_writeWbxml($fixture->input, function (Horde_ActiveSync_Wbxml_Encoder $encoder) {
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_FOLDER);
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_SYNCKEY);
            $encoder->content(self::SYNCKEY);
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_SERVERENTRYID);
            $encoder->content('F42');
            $encoder->endTag();
            $encoder->endTag();
        });
        $fixture->decoder->readWbxmlHeader();

        $this->assertTrue($fixture->handler->handle());

        $response = $this->_decodeLegacyFolderResponse(
            $this->_readStream($fixture->output)
        );
        $this->assertSame(Horde_ActiveSync_Request_FolderCreate::STATUS_SUCCESS, $response['status']);
        $this->assertSame(self::NEWSYNCKEY, $response['syncKey']);
        $this->assertNull($response['serverEntryId']);
    }

    public function testLegacyMoveCollectionUpdatesFolderHierarchy()
    {
        $folder = new Horde_ActiveSync_Message_Folder();
        $folder->serverid = 'F42';

        $fixture = $this->_createHandler('MoveCollection');
        $this->_mockSuccessfulFolderUpdate($fixture, $folder);

        $this->_writeWbxml($fixture->input, function (Horde_ActiveSync_Wbxml_Encoder $encoder) {
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_FOLDER);
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_SYNCKEY);
            $encoder->content(self::SYNCKEY);
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_SERVERENTRYID);
            $encoder->content('F42');
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_PARENTID);
            $encoder->content('0');
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_DISPLAYNAME);
            $encoder->content('Moved Folder');
            $encoder->endTag();
            $encoder->endTag();
        });
        $fixture->decoder->readWbxmlHeader();

        $this->assertTrue($fixture->handler->handle());

        $response = $this->_decodeLegacyFolderResponse(
            $this->_readStream($fixture->output)
        );
        $this->assertSame(Horde_ActiveSync_Request_FolderCreate::STATUS_SUCCESS, $response['status']);
        $this->assertSame(self::NEWSYNCKEY, $response['syncKey']);
        $this->assertNull($response['serverEntryId']);
    }

    public function testCreateCollectionAcceptsModernFolderCreateBody()
    {
        $folder = new Horde_ActiveSync_Message_Folder();
        $folder->serverid = 'F100';

        $fixture = $this->_createHandler('CreateCollection');
        $this->_mockSuccessfulFolderCreate($fixture, $folder, 'Modern Body');

        $this->_writeWbxml($fixture->input, function (Horde_ActiveSync_Wbxml_Encoder $encoder) {
            $encoder->startTag(Horde_ActiveSync_Request_FolderCreate::FOLDERCREATE);
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_SYNCKEY);
            $encoder->content(self::SYNCKEY);
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_PARENTID);
            $encoder->content('0');
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_DISPLAYNAME);
            $encoder->content('Modern Body');
            $encoder->endTag();
            $encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_TYPE);
            $encoder->content('12');
            $encoder->endTag();
            $encoder->endTag();
        });
        $fixture->decoder->readWbxmlHeader();

        $this->assertTrue($fixture->handler->handle());

        $response = $this->_decodeModernFolderCreateResponse(
            $this->_readStream($fixture->output)
        );
        $this->assertSame(Horde_ActiveSync_Request_FolderCreate::STATUS_SUCCESS, $response['status']);
        $this->assertSame(self::NEWSYNCKEY, $response['syncKey']);
        $this->assertSame('F100', $response['serverEntryId']);
    }

    public function testLegacyCollectionHandlerClassIsAvailableForRouting()
    {
        $this->assertTrue(class_exists('Horde_ActiveSync_Request_LegacyCollection'));
    }

    /**
     * @return object{
     *   server: Horde_ActiveSync,
     *   handler: Horde_ActiveSync_Request_LegacyCollection,
     *   input: resource,
     *   output: resource,
     *   collections: Horde_ActiveSync_Collections&\PHPUnit\Framework\MockObject\MockObject,
     *   importer: Horde_ActiveSync_Connector_Importer&\PHPUnit\Framework\MockObject\MockObject,
     *   state: Horde_ActiveSync_State_Base&\PHPUnit\Framework\MockObject\MockObject
     * }
     */
    protected function _createHandler(string $cmd): object
    {
        $logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());
        $input = fopen('php://memory', 'wb+');
        $decoder = new Horde_ActiveSync_Wbxml_Decoder($input);

        $output = fopen('php://memory', 'wb+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($output);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $collections = $this->createMock(Horde_ActiveSync_Collections::class);
        $importer = $this->createMock(Horde_ActiveSync_Connector_Importer::class);

        $request = new Horde_Controller_Request_Mock([
            'get' => [
                'Cmd' => $cmd,
                'DeviceId' => 'TESTDEVICE',
            ],
            'server' => [
                'HTTP_MS_ASPROTOCOLVERSION' => Horde_ActiveSync::VERSION_TWELVEONE,
            ],
        ]);

        $server = $this->getMockBuilder(Horde_ActiveSync::class)
            ->setConstructorArgs([$driver, $decoder, $encoder, $state, $request])
            ->onlyMethods(['getCollectionsObject', 'getImporter'])
            ->getMock();
        $server->method('getCollectionsObject')->willReturn($collections);
        $server->method('getImporter')->willReturn($importer);

        $device = new \Horde_ActiveSync_Device($state);
        $device->id = 'TESTDEVICE';
        $device->version = Horde_ActiveSync::VERSION_TWELVEONE;
        $deviceRef = new ReflectionProperty(Horde_ActiveSync::class, '_device');
        $deviceRef->setAccessible(true);
        $deviceRef->setValue(null, $device);

        $handler = new Horde_ActiveSync_Request_LegacyCollection($server);
        $handler->setLogger($logger);

        return (object) [
            'server' => $server,
            'handler' => $handler,
            'decoder' => $decoder,
            'input' => $input,
            'output' => $output,
            'collections' => $collections,
            'importer' => $importer,
            'state' => $state,
        ];
    }

    /**
     * @param object{collections: Horde_ActiveSync_Collections&\PHPUnit\Framework\MockObject\MockObject, importer: Horde_ActiveSync_Connector_Importer&\PHPUnit\Framework\MockObject\MockObject, state: Horde_ActiveSync_State_Base&\PHPUnit\Framework\MockObject\MockObject} $fixture
     */
    protected function _mockSuccessfulFolderCreate(object $fixture, Horde_ActiveSync_Message_Folder $folder, string $displayName): void
    {
        $fixture->collections->expects($this->once())
            ->method('initHierarchySync')
            ->with(self::SYNCKEY);
        $fixture->importer->expects($this->once())
            ->method('init')
            ->with($fixture->state);
        $fixture->importer->expects($this->once())
            ->method('importFolderChange')
            ->with(false, $displayName, '0', '12')
            ->willReturn($folder);
        $fixture->collections->expects($this->once())
            ->method('updateFolderInHierarchy')
            ->with($folder, true);
        $fixture->collections->expects($this->once())->method('save');
        $fixture->state->expects($this->once())->method('setNewSyncKey')->with(self::NEWSYNCKEY);
        $fixture->state->expects($this->once())->method('save');
    }

    /**
     * @param object{collections: Horde_ActiveSync_Collections&\PHPUnit\Framework\MockObject\MockObject, importer: Horde_ActiveSync_Connector_Importer&\PHPUnit\Framework\MockObject\MockObject, state: Horde_ActiveSync_State_Base&\PHPUnit\Framework\MockObject\MockObject} $fixture
     */
    protected function _mockSuccessfulFolderDelete(object $fixture, string $folderId): void
    {
        $fixture->collections->expects($this->once())
            ->method('initHierarchySync')
            ->with(self::SYNCKEY);
        $fixture->importer->expects($this->once())
            ->method('init')
            ->with($fixture->state);
        $fixture->importer->expects($this->once())
            ->method('importFolderDeletion')
            ->with($folderId);
        $fixture->collections->expects($this->once())
            ->method('deleteFolderFromHierarchy')
            ->with($folderId);
        $fixture->collections->expects($this->once())->method('save');
        $fixture->state->expects($this->once())->method('setNewSyncKey')->with(self::NEWSYNCKEY);
        $fixture->state->expects($this->once())->method('save');
    }

    /**
     * @param object{collections: Horde_ActiveSync_Collections&\PHPUnit\Framework\MockObject\MockObject, importer: Horde_ActiveSync_Connector_Importer&\PHPUnit\Framework\MockObject\MockObject, state: Horde_ActiveSync_State_Base&\PHPUnit\Framework\MockObject\MockObject} $fixture
     */
    protected function _mockSuccessfulFolderUpdate(object $fixture, Horde_ActiveSync_Message_Folder $folder): void
    {
        $fixture->collections->expects($this->once())
            ->method('initHierarchySync')
            ->with(self::SYNCKEY);
        $fixture->importer->expects($this->once())
            ->method('init')
            ->with($fixture->state);
        $fixture->importer->expects($this->once())
            ->method('importFolderChange')
            ->with('F42', 'Moved Folder', '0', false)
            ->willReturn($folder);
        $fixture->collections->expects($this->once())
            ->method('updateFolderInHierarchy')
            ->with($folder, true);
        $fixture->collections->expects($this->once())->method('save');
        $fixture->state->expects($this->once())->method('setNewSyncKey')->with(self::NEWSYNCKEY);
        $fixture->state->expects($this->once())->method('save');
    }

    /**
     * @param callable(Horde_ActiveSync_Wbxml_Encoder): void $writer
     */
    protected function _writeWbxml($stream, callable $writer): void
    {
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($stream);
        $encoder->startWBXML();
        $writer($encoder);
        rewind($stream);
    }

    /**
     * @return array{status: ?int, syncKey: ?string, serverEntryId: ?string}
     */
    protected function _decodeLegacyFolderResponse(string $wbxml): array
    {
        $stream = fopen('php://memory', 'wb+');
        fwrite($stream, $wbxml);
        rewind($stream);

        $decoder = new Horde_ActiveSync_Wbxml_Decoder($stream);
        $decoder->readWbxmlHeader();
        $decoder->getElementStartTag(Horde_ActiveSync::FOLDERHIERARCHY_FOLDER);

        $response = [
            'status' => null,
            'syncKey' => null,
            'serverEntryId' => null,
        ];

        while (($child = $decoder->peek()) !== false
            && $child[Horde_ActiveSync_Wbxml::EN_TYPE] === Horde_ActiveSync_Wbxml::EN_TYPE_STARTTAG
        ) {
            $tag = $child[Horde_ActiveSync_Wbxml::EN_TAG];
            $decoder->getElementStartTag($tag);
            $content = $decoder->getElementContent();
            $decoder->getElementEndTag();

            if ($tag === Horde_ActiveSync::FOLDERHIERARCHY_STATUS) {
                $response['status'] = (int) $content;
            } elseif ($tag === Horde_ActiveSync::FOLDERHIERARCHY_SYNCKEY) {
                $response['syncKey'] = $content;
            } elseif ($tag === Horde_ActiveSync::FOLDERHIERARCHY_SERVERENTRYID) {
                $response['serverEntryId'] = $content;
            }
        }

        $decoder->getElementEndTag();
        fclose($stream);

        return $response;
    }

    /**
     * @return array{status: ?int, syncKey: ?string, serverEntryId: ?string}
     */
    protected function _decodeModernFolderCreateResponse(string $wbxml): array
    {
        $stream = fopen('php://memory', 'wb+');
        fwrite($stream, $wbxml);
        rewind($stream);

        $decoder = new Horde_ActiveSync_Wbxml_Decoder($stream);
        $decoder->readWbxmlHeader();
        $decoder->getElementStartTag(Horde_ActiveSync_Request_FolderCreate::FOLDERCREATE);

        $response = [
            'status' => null,
            'syncKey' => null,
            'serverEntryId' => null,
        ];

        while (($child = $decoder->peek()) !== false
            && $child[Horde_ActiveSync_Wbxml::EN_TYPE] === Horde_ActiveSync_Wbxml::EN_TYPE_STARTTAG
        ) {
            $tag = $child[Horde_ActiveSync_Wbxml::EN_TAG];
            $decoder->getElementStartTag($tag);
            $content = $decoder->getElementContent();
            $decoder->getElementEndTag();

            if ($tag === Horde_ActiveSync::FOLDERHIERARCHY_STATUS) {
                $response['status'] = (int) $content;
            } elseif ($tag === Horde_ActiveSync::FOLDERHIERARCHY_SYNCKEY) {
                $response['syncKey'] = $content;
            } elseif ($tag === Horde_ActiveSync::FOLDERHIERARCHY_SERVERENTRYID) {
                $response['serverEntryId'] = $content;
            }
        }

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
