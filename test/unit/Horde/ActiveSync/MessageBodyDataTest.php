<?php

/**
 * Unit tests for Horde_ActiveSync_Imap_MessageBodyData
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @package ActiveSync
 */

namespace Horde\ActiveSync;

use Horde\ActiveSync\Test\Support\Bug13711Fixtures;
use Horde_ActiveSync;
use Horde_ActiveSync_Imap_MessageBodyData;
use Horde_ActiveSync_Mime;
use Horde_Imap_Client_Data_Fetch;
use Horde_Imap_Client_Fetch_Results;
use Horde_Imap_Client_Mailbox;
use Horde_Imap_Client_Socket;
use Horde_Mime_Part;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class MessageBodyDataTest extends TestCase
{
    /**
     * Dovecot may return no data when BODY[].SIZE is combined with BODY[]
     * for deeply nested MIME parts. Ensure we retry without sizes.
     */
    public function testFetchFallbackWithoutBodyPartSize()
    {
        $empty = new Horde_Imap_Client_Fetch_Results();
        $fetch_data = new Horde_Imap_Client_Data_Fetch();
        $fetch_data->setUid(1576);
        $fetch_data->setBodyPart('1', 'plain text body', '8bit');
        $populated = new Horde_Imap_Client_Fetch_Results();
        $populated[$fetch_data->getUid()] = $fetch_data;

        $imap_client = $this->getMockBuilder(Horde_Imap_Client_Socket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetch'])
            ->getMock();
        $imap_client->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($empty, $populated);

        $plain = new Horde_Mime_Part();
        $plain->setType('text/plain');
        $plain->setContents('plain text body');
        $mime = new Horde_ActiveSync_Mime($plain);

        $mbd = new Horde_ActiveSync_Imap_MessageBodyData(
            [
                'imap' => $imap_client,
                'mime' => $mime,
                'uid' => 1576,
                'mbox' => new Horde_Imap_Client_Mailbox('INBOX'),
            ],
            [
                'protocolversion' => 16.0,
                'bodyprefs' => [
                    Horde_ActiveSync::BODYPREF_TYPE_PLAIN => [
                        'truncationsize' => 500,
                    ],
                ],
            ]
        );

        $this->assertNotEmpty($mbd->plain);
        $this->assertEquals(15, $mbd->plain['size']);
    }

    public function testReturnProperlyTruncatedHtml()
    {
        $imap_client = $this->getMockBuilder(Horde_Imap_Client_Socket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetch'])
            ->getMock();
        $imap_client->expects($this->once())
            ->method('fetch')
            ->willReturn(Bug13711Fixtures::htmlFetchResults());

        $mbd = new Horde_ActiveSync_Imap_MessageBodyData(
            [
                'imap' => $imap_client,
                'mime' => Bug13711Fixtures::htmlMime(),
                'uid' => 1,
                'mbox' => new Horde_Imap_Client_Mailbox('INBOX'),
            ],
            [
                'protocolversion' => 14.1,
                'bodyprefs' => [
                    Horde_ActiveSync::BODYPREF_TYPE_HTML => [
                        'truncationsize' => 10240,
                        'allornone' => 0,
                    ],
                    Horde_ActiveSync::BODYPREF_TYPE_PLAIN => [
                        'truncationsize' => 10240,
                        'allornone' => 0,
                    ],
                ],
            ]
        );

        $this->assertEquals(10240, $mbd->html['body']->length(true));
        $this->assertTrue($mbd->html['truncated']);
    }

    public function testReturnHtmlNoTruncation()
    {
        $imap_client = $this->getMockBuilder(Horde_Imap_Client_Socket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetch'])
            ->getMock();
        $imap_client->expects($this->once())
            ->method('fetch')
            ->willReturn(Bug13711Fixtures::htmlFetchResults());

        $mbd = new Horde_ActiveSync_Imap_MessageBodyData(
            [
                'imap' => $imap_client,
                'mime' => Bug13711Fixtures::htmlMime(),
                'uid' => 1,
                'mbox' => new Horde_Imap_Client_Mailbox('INBOX'),
            ],
            [
                'protocolversion' => 14.1,
                'bodyprefs' => [
                    Horde_ActiveSync::BODYPREF_TYPE_HTML => [
                        'truncationsize' => false,
                        'allornone' => 0,
                    ],
                    Horde_ActiveSync::BODYPREF_TYPE_PLAIN => [
                        'truncationsize' => false,
                        'allornone' => 0,
                    ],
                ],
            ]
        );

        $this->assertEquals(26844, $mbd->html['body']->length(true));
        $this->assertFalse($mbd->html['truncated']);
    }
}
