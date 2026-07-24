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
use Horde_Imap_Client_Exception;
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

    /**
     * A batched status prefetch must be consumed by subsequent ping() calls
     * without any further per-mailbox STATUS round trips.
     */
    public function testPrefetchStatusConsumedByPing()
    {
        $imap_client = $this->getMockBuilder(Horde_Imap_Client_Socket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['status'])
            ->getMock();
        // Exactly ONE status call for both folders (the batched prefetch).
        $imap_client->expects($this->once())
            ->method('status')
            ->with($this->isType('array'))
            ->willReturn([
                'INBOX' => [
                    Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 600,
                    'uidnext' => 101,
                    'messages' => 11,
                ],
                'Sent' => [
                    Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 500,
                    'uidnext' => 100,
                    'messages' => 10,
                ],
            ]);

        $adapter = new Horde_ActiveSync_Imap_Adapter([
            'factory' => $this->_imapFactoryFixture($imap_client),
        ]);
        $adapter->prefetchStatus(['INBOX', 'Sent']);

        // INBOX: prefetched modseq/uidnext advanced -> changes.
        $inbox = $this->_pingReadyFolder('INBOX');
        $this->assertTrue($adapter->ping($inbox));

        // Sent: prefetched status identical to watermark -> no changes.
        $sent = $this->_pingReadyFolder('Sent');
        $this->assertFalse($adapter->ping($sent));
    }

    /**
     * Prefetched entries are consumed exactly once; a second ping() of the
     * same folder must issue a fresh per-mailbox STATUS.
     */
    public function testPrefetchStatusConsumeOnce()
    {
        $imap_client = $this->getMockBuilder(Horde_Imap_Client_Socket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['status'])
            ->getMock();
        $quiet = [
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 500,
            'uidnext' => 100,
            'messages' => 10,
        ];
        // One batched call, then one per-mailbox call for the second ping.
        $imap_client->expects($this->exactly(2))
            ->method('status')
            ->willReturnCallback(function ($mbox) use ($quiet) {
                return is_array($mbox)
                    ? ['INBOX' => $quiet, 'Sent' => $quiet]
                    : $quiet;
            });

        $adapter = new Horde_ActiveSync_Imap_Adapter([
            'factory' => $this->_imapFactoryFixture($imap_client),
        ]);
        $adapter->prefetchStatus(['INBOX', 'Sent']);

        $inbox = $this->_pingReadyFolder('INBOX');
        $this->assertFalse($adapter->ping($inbox));
        $this->assertFalse($adapter->ping($inbox));
    }

    /**
     * A failing batched prefetch must degrade to the per-mailbox STATUS
     * path without surfacing an error.
     */
    public function testPrefetchStatusFallbackOnError()
    {
        $imap_client = $this->getMockBuilder(Horde_Imap_Client_Socket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['status'])
            ->getMock();
        $imap_client->expects($this->exactly(2))
            ->method('status')
            ->willReturnCallback(function ($mbox) {
                if (is_array($mbox)) {
                    throw new Horde_Imap_Client_Exception('LIST-STATUS failed');
                }
                return [
                    Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 600,
                    'uidnext' => 101,
                    'messages' => 11,
                ];
            });

        $adapter = new Horde_ActiveSync_Imap_Adapter([
            'factory' => $this->_imapFactoryFixture($imap_client),
        ]);
        $adapter->prefetchStatus(['INBOX', 'Sent']);

        $inbox = $this->_pingReadyFolder('INBOX');
        $this->assertTrue($adapter->ping($inbox));
    }

    /**
     * Build a folder with SYNC state and PING watermark at modseq 500,
     * uidnext 100, 10 messages.
     */
    protected function _pingReadyFolder($serverid)
    {
        $folder = new Horde_ActiveSync_Folder_Imap($serverid, Horde_ActiveSync::CLASS_EMAIL);
        $folder->setStatus([
            Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 1,
            Horde_ActiveSync_Folder_Imap::UIDNEXT => 100,
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 500,
            Horde_ActiveSync_Folder_Imap::MESSAGES => 10,
        ]);
        $folder->updateState();
        $folder->acknowledgePingStatus([
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 500,
            'uidnext' => 100,
            'messages' => 10,
        ]);

        return $folder;
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
