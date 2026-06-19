<?php

/**
 * Unit tests for Horde_ActiveSync_Connector_Exporter_Sync.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use Horde_ActiveSync_Connector_Exporter_Sync;
use Horde_ActiveSync_Driver_Base;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Request_Sync;
use Horde_ActiveSync_State_Base;
use Horde_ActiveSync_Wbxml;
use Horde_ActiveSync_Wbxml_Decoder;
use Horde_ActiveSync_Wbxml_Encoder;
use Horde_Controller_Request_Mock;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_ActiveSync_Connector_Exporter_Sync::class)]
class SyncTest extends TestCase
{
    /**
     * MS-ASCMD Sync Replies Remove must echo ServerEntryId when the client
     * sent ServerEntryId. iOS maild crashes if ClientEntryId is used instead
     * (STATUS 8 / object not found on permanent delete from Trash).
     */
    public function testMissingRemoveReplyUsesServerEntryIdAndStatusNotFound()
    {
        $replies = $this->_decodeRemoveReplies(
            $this->_encodeMissingRemove(['173417'])
        );

        $this->assertCount(1, $replies);
        $this->assertSame('173417', $replies[0]['serverEntryId']);
        $this->assertNull($replies[0]['clientEntryId']);
        $this->assertSame(
            Horde_ActiveSync_Request_Sync::STATUS_NOTFOUND,
            $replies[0]['status']
        );
    }

    public function testMissingRemoveReplyEmittedForEachMissingId()
    {
        $replies = $this->_decodeRemoveReplies(
            $this->_encodeMissingRemove(['173417', '173418'])
        );

        $this->assertCount(2, $replies);
        $this->assertSame('173417', $replies[0]['serverEntryId']);
        $this->assertSame('173418', $replies[1]['serverEntryId']);
        $this->assertSame(
            Horde_ActiveSync_Request_Sync::STATUS_NOTFOUND,
            $replies[0]['status']
        );
        $this->assertSame(
            Horde_ActiveSync_Request_Sync::STATUS_NOTFOUND,
            $replies[1]['status']
        );
    }

    /**
     * @param list<string> $missing
     */
    protected function _encodeMissingRemove(array $missing): string
    {
        $logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());
        $output = fopen('php://memory', 'wb+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($output);
        $encoder->setLogger($logger);
        $encoder->startWBXML();

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $input = fopen('php://memory', 'wb+');
        $decoder = new Horde_ActiveSync_Wbxml_Decoder($input);
        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $request = new Horde_Controller_Request_Mock(['server' => []]);

        $server = new Horde_ActiveSync(
            $driver,
            $decoder,
            $encoder,
            $state,
            $request
        );
        $exporter = new Horde_ActiveSync_Connector_Exporter_Sync($server, $encoder);
        $exporter->missingRemove(['missing' => $missing]);

        rewind($output);
        $encoded = stream_get_contents($output);
        fclose($output);

        return $encoded;
    }

    /**
     * @return list<array{serverEntryId: ?string, clientEntryId: ?string, status: ?int}>
     */
    protected function _decodeRemoveReplies(string $wbxml): array
    {
        $stream = fopen('php://memory', 'wb+');
        fwrite($stream, $wbxml);
        rewind($stream);

        $decoder = new Horde_ActiveSync_Wbxml_Decoder($stream);
        $decoder->readWbxmlHeader();

        $replies = [];
        while (($next = $decoder->peek()) !== false
            && $next[Horde_ActiveSync_Wbxml::EN_TYPE] === Horde_ActiveSync_Wbxml::EN_TYPE_STARTTAG
            && $next[Horde_ActiveSync_Wbxml::EN_TAG] === Horde_ActiveSync::SYNC_REMOVE
        ) {
            $decoder->getElementStartTag(Horde_ActiveSync::SYNC_REMOVE);
            $reply = [
                'serverEntryId' => null,
                'clientEntryId' => null,
                'status' => null,
            ];

            while (($child = $decoder->peek()) !== false
                && $child[Horde_ActiveSync_Wbxml::EN_TYPE] === Horde_ActiveSync_Wbxml::EN_TYPE_STARTTAG
            ) {
                $tag = $child[Horde_ActiveSync_Wbxml::EN_TAG];
                $decoder->getElementStartTag($tag);
                $content = $decoder->getElementContent();
                $decoder->getElementEndTag();

                if ($tag === Horde_ActiveSync::SYNC_SERVERENTRYID) {
                    $reply['serverEntryId'] = $content;
                } elseif ($tag === Horde_ActiveSync::SYNC_CLIENTENTRYID) {
                    $reply['clientEntryId'] = $content;
                } elseif ($tag === Horde_ActiveSync::SYNC_STATUS) {
                    $reply['status'] = (int) $content;
                }
            }

            $decoder->getElementEndTag();
            $replies[] = $reply;
        }

        fclose($stream);

        return $replies;
    }
}
