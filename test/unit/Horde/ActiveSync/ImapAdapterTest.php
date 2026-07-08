<?php

/**
 * Unit tests for Horde_ActiveSync_Folder_Imap
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @package ActiveSync
 */

namespace Horde\ActiveSync;

use Horde\ActiveSync\Test\Support\Bug13711Fixtures;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Horde_ActiveSync;
use Horde_ActiveSync_Folder_Imap;
use Horde_ActiveSync_Imap_Adapter;
use Horde_ActiveSync_Interface_ImapFactory;
use Horde_Imap_Client_Socket;

#[CoversNothing]
class ImapAdapterTest extends TestCase
{
    /**
     * BigFamily-style: stale SYNC modseq must not re-trigger PING once the
     * PING watermark has caught up.
     */
    public function testPingUsesPingWatermarkNotSyncModseq()
    {
        $imap_client = $this->getMockBuilder(Horde_Imap_Client_Socket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['status'])
            ->getMock();
        $serverStatus = [
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 6000,
            'uidnext' => 8200,
            'messages' => 7932,
        ];
        $imap_client->expects($this->once())
            ->method('status')
            ->willReturn($serverStatus);

        $imap_factory = $this->_imapFactoryFixture($imap_client);
        $adapter = new Horde_ActiveSync_Imap_Adapter(['factory' => $imap_factory]);

        $folder = new Horde_ActiveSync_Folder_Imap('INBOX/BigFamily', Horde_ActiveSync::CLASS_EMAIL);
        $folder->setStatus([
            Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 1267430887,
            Horde_ActiveSync_Folder_Imap::UIDNEXT => 8200,
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 5864,
            Horde_ActiveSync_Folder_Imap::MESSAGES => 7932,
        ]);
        $folder->updateState();
        $folder->acknowledgePingStatus($serverStatus);

        $this->assertFalse($adapter->ping($folder));
        $this->assertEquals(5864, $folder->modseq());
        $this->assertEquals(6000, $folder->pingModseq());
    }

    public function testPingThrowsAuthenticationFailureWhenImapClientIsNull()
    {
        $adapter = new Horde_ActiveSync_Imap_Adapter([
            'factory' => $this->_imapFactoryFixture(null),
        ]);
        $folder = new Horde_ActiveSync_Folder_Imap('INBOX', Horde_ActiveSync::CLASS_EMAIL);

        $this->expectException(\Horde_Exception_AuthenticationFailure::class);

        $adapter->ping($folder);
    }

    public function testBug13711()
    {
        Bug13711Fixtures::setActiveSyncProtocolVersion('14.1');

        $fixtures = Bug13711Fixtures::calendarOnlyFetchSequence();
        $imap_client = $this->getMockBuilder(Horde_Imap_Client_Socket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetch'])
            ->getMock();
        $imap_client->expects($this->exactly(count($fixtures)))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(...$fixtures);

        $imap_factory = $this->_imapFactoryFixture($imap_client);
        $adapter = new Horde_ActiveSync_Imap_Adapter(['factory' => $imap_factory]);

        $messages = $adapter->getMessages(
            'INBOX',
            [462],
            [
                'protocolversion' => 14.1,
                'bodyprefs' => [
                    'wanted' => Horde_ActiveSync::BODYPREF_TYPE_MIME,
                    Horde_ActiveSync::BODYPREF_TYPE_MIME => [
                        'type' => Horde_ActiveSync::BODYPREF_TYPE_MIME,
                        'truncationsize' => 200000,
                    ],
                ],
                'mimesupport' => Horde_ActiveSync::MIME_SUPPORT_ALL,
            ]
        );

        $this->assertCount(1, $messages);
        $this->assertEquals(
            'urn:content-classes:calendarmessage',
            $messages[0]->contentclass
        );
        $this->assertEquals(
            'MAC > Fahrstuhl > Beschriftung > Muster',
            $messages[0]->subject
        );
    }

    protected function _imapFactoryFixture($imap_client)
    {
        return new class ($imap_client) implements Horde_ActiveSync_Interface_ImapFactory {
            private $_imap;

            public function __construct($imap)
            {
                $this->_imap = $imap;
            }

            public function getImapOb()
            {
                return $this->_imap;
            }

            public function getMailboxes($force = false)
            {
                return [];
            }

            public function getSpecialMailboxes()
            {
                return [];
            }

            public function getMsgFlags()
            {
                return [];
            }
        };
    }
}
