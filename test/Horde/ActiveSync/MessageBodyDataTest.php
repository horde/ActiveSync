<?php

/**
 * Unit tests for Horde_ActiveSync_Folder_Imap
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @package ActiveSync
 */

namespace Horde\ActiveSync;

use PHPUnit\Framework\Attributes\CoversNothing;
use Horde_Test_Case as TestCase;
use Horde\ActiveSync\Factory\TestServer;
use Horde_ActiveSync_Mime;
use Horde_Imap_Client_Data_Fetch;
use Horde_Imap_Client_Fetch_Results;
use Horde_Imap_Client_Socket;
use Horde_Mime_Part;

/**
 * @coversNothing
 */
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

        $mbd = new \Horde_ActiveSync_Imap_MessageBodyData(
            [
                'imap' => $imap_client,
                'mime' => $mime,
                'uid' => 1576,
                'mbox' => new \Horde_Imap_Client_Mailbox('INBOX'),
            ],
            [
                'protocolversion' => 16.0,
                'bodyprefs' => [
                    \Horde_ActiveSync::BODYPREF_TYPE_PLAIN => [
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
        $factory = new TestServer();
        $imap_client = $this->getMockBuilder('Horde_Imap_Client_Socket')->disableOriginalConstructor()->getMock();
        $imap_client->expects($this->any())
            ->method('fetch')
            ->will($this->_getFixturesFor13711());

        $imap_factory = new Horde_ActiveSync_Stub_ImapFactory();
        $imap_factory->fixture = $imap_client;
        $adapter = new Horde_ActiveSync_Imap_Adapter(['factory' => $imap_factory]);

        $this->markTestIncomplete("Can't use serialized Horde_Mime_Part");

        $horde_mime_fixture = 'TzoyMToiSG9yZGVfQWN0aXZlU3luY19NaW1lIjoyOntzOjg6IgAqAF9iYXNlIjtDOjE1OiJIb3JkZV9NaW1lX1BhcnQiOjI4MDp7YToyMDp7aTowO2k6MTtpOjE7czo0OiJ0ZXh0IjtpOjI7czo0OiJodG1sIjtpOjM7czoxNjoicXVvdGVkLXByaW50YWJsZSI7aTo0O2E6MDp7fWk6NTtzOjA6IiI7aTo2O3M6MDoiIjtpOjc7YToxOntzOjQ6InNpemUiO3M6NToiMzAzMzYiO31pOjg7YToxOntzOjc6ImNoYXJzZXQiO3M6NToidXRmLTgiO31pOjk7YTowOnt9aToxMDtzOjE6IjEiO2k6MTE7czoxOiIKIjtpOjEyO2E6MDp7fWk6MTM7TjtpOjE0O2k6MzAzMzY7aToxNTtOO2k6MTY7TjtpOjE3O2I6MDtpOjE4O2I6MDtpOjE5O047fX1zOjE4OiIAKgBfaGFzQXR0YWNobWVudHMiO047fQ==';
        $basePart = unserialize(base64_decode($horde_mime_fixture));

        $mbd = new Horde_ActiveSync_Imap_MessageBodyData(
            [
                'imap' => $imap_client,
                'mime' => $basePart,
                'uid' => 1,
                'mbox' => new Horde_Imap_Client_Mailbox('INBOX')],
            [
                'protocolversion' => 14.1,
                'bodyprefs' => [
                    Horde_ActiveSync::BODYPREF_TYPE_HTML => [
                        'truncationsize' => 10240,
                        'allornone' => 0],
                    Horde_ActiveSync::BODYPREF_TYPE_PLAIN => [
                        'truncationsize' => 10240,
                        'allornone' => 0],
                ],
            ]
        );

        $this->assertEquals(10240, $mbd->html['body']->length(true));
        $this->assertEquals(true, $mbd->html['truncated']);
    }

    public function testReturnHtmlNoTruncation()
    {
        $factory = new TestServer();
        $imap_client = $this->getMockBuilder('Horde_Imap_Client_Socket')->disableOriginalConstructor()->getMock();
        $imap_client->expects($this->any())
            ->method('fetch')
            ->will($this->_getFixturesFor13711());

        $imap_factory = new Horde_ActiveSync_Stub_ImapFactory();
        $imap_factory->fixture = $imap_client;
        $adapter = new Horde_ActiveSync_Imap_Adapter(['factory' => $imap_factory]);

        $this->markTestIncomplete("Can't use serialized Horde_Mime_Part");

        $horde_mime_fixture = 'TzoyMToiSG9yZGVfQWN0aXZlU3luY19NaW1lIjoyOntzOjg6IgAqAF9iYXNlIjtDOjE1OiJIb3JkZV9NaW1lX1BhcnQiOjI4MDp7YToyMDp7aTowO2k6MTtpOjE7czo0OiJ0ZXh0IjtpOjI7czo0OiJodG1sIjtpOjM7czoxNjoicXVvdGVkLXByaW50YWJsZSI7aTo0O2E6MDp7fWk6NTtzOjA6IiI7aTo2O3M6MDoiIjtpOjc7YToxOntzOjQ6InNpemUiO3M6NToiMzAzMzYiO31pOjg7YToxOntzOjc6ImNoYXJzZXQiO3M6NToidXRmLTgiO31pOjk7YTowOnt9aToxMDtzOjE6IjEiO2k6MTE7czoxOiIKIjtpOjEyO2E6MDp7fWk6MTM7TjtpOjE0O2k6MzAzMzY7aToxNTtOO2k6MTY7TjtpOjE3O2I6MDtpOjE4O2I6MDtpOjE5O047fX1zOjE4OiIAKgBfaGFzQXR0YWNobWVudHMiO047fQ==';
        $basePart = unserialize(base64_decode($horde_mime_fixture));

        $mbd = new Horde_ActiveSync_Imap_MessageBodyData(
            [
                'imap' => $imap_client,
                'mime' => $basePart,
                'uid' => 1,
                'mbox' => new Horde_Imap_Client_Mailbox('INBOX')],
            [
                'protocolversion' => 14.1,
                'bodyprefs' => [
                    Horde_ActiveSync::BODYPREF_TYPE_HTML => [
                        'truncationsize' => false,
                        'allornone' => 0],
                    Horde_ActiveSync::BODYPREF_TYPE_PLAIN => [
                        'truncationsize' => false,
                        'allornone' => 0],
                ],
            ]
        );

        $this->assertEquals(26844, $mbd->html['body']->length(true));
        $this->assertEquals(false, $mbd->html['truncated']);
    }

    protected function _getFixturesFor13711()
    {
        $fetch_ret = unserialize(base64_decode(file_get_contents(__DIR__ . '/fixtures/fixture_fetch')));
        return $this->onConsecutiveCalls($fetch_ret);
    }

}
