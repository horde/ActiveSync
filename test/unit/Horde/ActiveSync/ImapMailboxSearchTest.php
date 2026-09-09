<?php

/**
 * Unit tests for mailbox Search IMAP queries.
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
use Horde_ActiveSync_Imap_Adapter;
use Horde_ActiveSync_Interface_ImapFactory;
use Horde_ActiveSync_Message_Mail;
use Horde_ActiveSync_Request_Search;
use Horde_Date;
use Horde_Imap_Client_Exception;
use Horde_Imap_Client_Ids;
use Horde_Imap_Client_Mailbox;
use Horde_Imap_Client_Socket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Horde_ActiveSync_Imap_Adapter::class)]
class ImapMailboxSearchTest extends TestCase
{
    public function testQueryMailboxSearchesInboxFirst()
    {
        $order = [];
        $imap = $this->_imapClient($order);
        $adapter = $this->_adapter($imap, ['Sent', 'INBOX']);

        $adapter->queryMailbox($this->_freetextQuery(), [], false);

        $this->assertSame(['INBOX', 'Sent'], $order);
    }

    public function testQueryMailboxDoesNotDateChunkWithoutProgressCallback()
    {
        $order = [];
        $imap = $this->_imapClient($order);
        $adapter = $this->_adapter($imap, ['INBOX', 'Sent']);

        $adapter->queryMailbox($this->_freetextQuery(), [], false);

        $this->assertCount(2, $order);
    }

    public function testQueryMailboxDateChunksWhenProgressCallbackProvided()
    {
        $order = [];
        $queries = [];
        $imap = $this->_imapClient($order, $queries);
        $adapter = $this->_adapter($imap, ['INBOX']);
        $progressCalls = 0;

        $adapter->queryMailbox(
            $this->_freetextQuery(),
            [
                'progress' => static function () use (&$progressCalls) {
                    $progressCalls++;
                },
            ],
            false
        );

        $this->assertCount(5, $queries);
        $this->assertSame(5, $progressCalls);
        $this->assertNotNull($queries[0]['date']);
        $this->assertNotNull($queries[4]['date']);
    }

    public function testQueryMailboxDoesNotDateChunkWhenClientSendsDate()
    {
        $order = [];
        $queries = [];
        $imap = $this->_imapClient($order, $queries);
        $adapter = $this->_adapter($imap, ['INBOX']);

        $query = [[
            'op' => Horde_ActiveSync_Request_Search::SEARCH_AND,
            'value' => [
                'FolderType' => Horde_ActiveSync::CLASS_EMAIL,
                Horde_ActiveSync_Request_Search::SEARCH_FREETEXT => 'train',
                'subquery' => [[
                    'op' => Horde_ActiveSync_Request_Search::SEARCH_GREATERTHAN,
                    'value' => [
                        Horde_ActiveSync_Message_Mail::POOMMAIL_DATERECEIVED => new Horde_Date('2026-01-01'),
                    ],
                ]],
            ],
        ]];

        $adapter->queryMailbox(
            $query,
            ['progress' => static function () {}],
            false
        );

        $this->assertCount(1, $queries);
    }

    public function testQueryMailboxContinuesAfterImapErrorOnOneMailbox()
    {
        $imap = $this->getMockBuilder(Horde_Imap_Client_Socket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['search'])
            ->getMock();
        $imap->method('search')
            ->willReturnCallback(function ($mbox) {
                if (strcasecmp((string) $mbox, 'INBOX') === 0) {
                    throw new Horde_Imap_Client_Exception('timed out');
                }

                return [
                    'count' => 1,
                    'match' => new Horde_Imap_Client_Ids([42]),
                ];
            });

        $adapter = $this->_adapter($imap, ['INBOX', 'Sent']);
        $results = $adapter->queryMailbox($this->_freetextQuery(), [], false);

        $this->assertSame(
            [['uniqueid' => 'Sent:42', 'searchfolderid' => 'Sent']],
            $results
        );
    }

    protected function _freetextQuery(): array
    {
        return [[
            'op' => Horde_ActiveSync_Request_Search::SEARCH_AND,
            'value' => [
                'FolderType' => Horde_ActiveSync::CLASS_EMAIL,
                Horde_ActiveSync_Request_Search::SEARCH_FREETEXT => 'train station',
            ],
        ]];
    }

    /**
     * @param string[]                    $order    Filled with mailbox names.
     * @param array<int, array>|null       $queries  Optional query snapshots.
     */
    protected function _imapClient(array &$order = [], ?array &$queries = null)
    {
        $imap = $this->getMockBuilder(Horde_Imap_Client_Socket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['search'])
            ->getMock();
        $imap->method('search')
            ->willReturnCallback(function ($mbox, $query) use (&$order, &$queries) {
                $order[] = (string) $mbox;
                if ($queries !== null) {
                    $ref = new ReflectionClass($query);
                    $prop = $ref->getProperty('_search');
                    $prop->setAccessible(true);
                    $queries[] = $prop->getValue($query);
                }

                return [
                    'count' => 0,
                    'match' => new Horde_Imap_Client_Ids([]),
                ];
            });

        return $imap;
    }

    /**
     * @param string[] $mailboxes
     */
    protected function _adapter($imap, array $mailboxes): Horde_ActiveSync_Imap_Adapter
    {
        $boxes = [];
        foreach ($mailboxes as $name) {
            $boxes[$name] = ['ob' => new Horde_Imap_Client_Mailbox($name)];
        }

        $factory = new class ($imap, $boxes) implements Horde_ActiveSync_Interface_ImapFactory {
            private $_imap;
            private $_boxes;

            public function __construct($imap, array $boxes)
            {
                $this->_imap = $imap;
                $this->_boxes = $boxes;
            }

            public function getImapOb()
            {
                return $this->_imap;
            }

            public function getMailboxes($force = false)
            {
                return $this->_boxes;
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

        return new Horde_ActiveSync_Imap_Adapter(['factory' => $factory]);
    }
}
