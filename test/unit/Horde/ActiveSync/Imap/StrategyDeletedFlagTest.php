<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @package ActiveSync
 * @subpackage UnitTests
 */

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for native Exchange \Deleted semantics in the IMAP change
 * detection strategies (Issue #94): EAS cannot represent a message flagged
 * for deletion, so such messages are removed from (or never exported to)
 * the client instead of being synced as live mail.
 */
class Horde_ActiveSync_Imap_StrategyDeletedFlagTest extends TestCase
{
    public function testModseqTrackedMessageGainingDeletedFlagIsRemoved(): void
    {
        $folder = $this->_modseqFolder([52, 53]);

        $imap = $this->_imapMock();
        $imap->method('search')->willReturn([
            'count' => 1,
            'match' => new Horde_Imap_Client_Ids([52]),
        ]);
        $this->_mockBatchedFetch($imap, $this->_fetchResults([
            52 => [[Horde_Imap_Client::FLAG_DELETED], 8],
        ]));
        $imap->method('vanished')
            ->willReturn(new Horde_Imap_Client_Ids([]));

        $strategy = new Horde_ActiveSync_Imap_Strategy_Modseq(
            $this->_factory($imap),
            $this->_status(10),
            $folder,
            $this->_logger()
        );
        $result = $strategy->getChanges([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);

        $this->assertSame([52], $result->removed());
        $this->assertSame([], $result->changed());
        $this->assertSame([], $result->added());
    }

    public function testModseqUntrackedDeletedMessageIsNeverExported(): void
    {
        // 60 arrives already flagged \Deleted, 61 is a regular new message.
        $folder = $this->_modseqFolder([52]);

        $imap = $this->_imapMock();
        $imap->method('search')->willReturn([
            'count' => 2,
            'match' => new Horde_Imap_Client_Ids([60, 61]),
        ]);
        $this->_mockBatchedFetch($imap, $this->_fetchResults([
            60 => [[Horde_Imap_Client::FLAG_DELETED], 8],
            61 => [[], 9],
        ]));
        $imap->method('vanished')
            ->willReturn(new Horde_Imap_Client_Ids([]));

        $strategy = new Horde_ActiveSync_Imap_Strategy_Modseq(
            $this->_factory($imap),
            $this->_status(10),
            $folder,
            $this->_logger()
        );
        $result = $strategy->getChanges([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);

        $this->assertSame([61], $result->added());
        $this->assertSame([], $result->removed());
        $this->assertArrayNotHasKey(60, $result->flags());
    }

    public function testModseqExpungedAndFlagDeletedRemovalsAreMerged(): void
    {
        $folder = $this->_modseqFolder([52, 53]);

        $imap = $this->_imapMock();
        $imap->method('search')->willReturn([
            'count' => 1,
            'match' => new Horde_Imap_Client_Ids([52]),
        ]);
        $this->_mockBatchedFetch($imap, $this->_fetchResults([
            52 => [[Horde_Imap_Client::FLAG_DELETED], 8],
        ]));
        $imap->method('vanished')
            ->willReturn(new Horde_Imap_Client_Ids([53]));

        $strategy = new Horde_ActiveSync_Imap_Strategy_Modseq(
            $this->_factory($imap),
            $this->_status(10),
            $folder,
            $this->_logger()
        );
        $result = $strategy->getChanges([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);

        $this->assertSame([53, 52], $result->removed());
    }

    public function testPlainTrackedDeletedMessageIsRemovedNotChanged(): void
    {
        $folder = $this->_plainFolder([52, 53]);

        $imap = $this->_imapMock();
        $imap->method('search')->willReturn([
            'count' => 2,
            'match' => new Horde_Imap_Client_Ids([52, 53]),
        ]);
        $this->_mockBatchedFetch($imap, $this->_fetchResults([
            52 => [[Horde_Imap_Client::FLAG_DELETED]],
            53 => [[Horde_Imap_Client::FLAG_SEEN]],
        ]));
        $imap->method('vanished')
            ->willReturn(new Horde_Imap_Client_Ids([]));

        $strategy = new Horde_ActiveSync_Imap_Strategy_Plain(
            $this->_factory($imap),
            $this->_status(0),
            $folder,
            $this->_logger()
        );
        $result = $strategy->getChanges([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);

        $this->assertSame([52], $result->removed());
        $this->assertNotContains(52, $result->changed());
        $this->assertArrayNotHasKey(52, $result->flags());
        // The unflagged message still syncs its flag change.
        $this->assertContains(53, $result->changed());
    }

    public function testInitialSyncSearchExcludesDeletedMessages(): void
    {
        $folder = new Horde_ActiveSync_Folder_Imap(
            'INBOX',
            Horde_ActiveSync::CLASS_EMAIL
        );

        $imap = $this->_imapMock();
        $imap->expects($this->once())
            ->method('search')
            ->with(
                $this->anything(),
                $this->callback(function ($query) {
                    return strpos((string) $query, 'UNDELETED') !== false;
                }),
                $this->anything()
            )
            ->willReturn([
                'count' => 2,
                'match' => new Horde_Imap_Client_Ids([52, 53]),
            ]);

        $strategy = new Horde_ActiveSync_Imap_Strategy_Initial(
            $this->_factory($imap),
            $this->_status(10),
            $folder,
            $this->_logger()
        );
        $result = $strategy->getChanges([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);

        // CONDSTORE priming path: the (UNDELETED-filtered) result set is
        // primed as the initial Add list.
        $this->assertSame([52, 53], $result->added());
    }

    /**
     * A folder tracking $uids on a CONDSTORE server at MODSEQ 5.
     */
    protected function _modseqFolder(array $uids): Horde_ActiveSync_Folder_Imap
    {
        $folder = new Horde_ActiveSync_Folder_Imap(
            'INBOX',
            Horde_ActiveSync::CLASS_EMAIL
        );
        $folder->setStatus([
            Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 1,
            Horde_ActiveSync_Folder_Imap::UIDNEXT => 55,
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 5,
            Horde_ActiveSync_Folder_Imap::MESSAGES => count($uids),
        ]);
        $folder->setChanges($uids);
        $folder->updateState();

        return $folder;
    }

    /**
     * A folder tracking $uids on a server without CONDSTORE support.
     */
    protected function _plainFolder(array $uids): Horde_ActiveSync_Folder_Imap
    {
        $folder = new Horde_ActiveSync_Folder_Imap(
            'INBOX',
            Horde_ActiveSync::CLASS_EMAIL
        );
        $folder->setStatus([
            Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 1,
            Horde_ActiveSync_Folder_Imap::UIDNEXT => 55,
            Horde_ActiveSync_Folder_Imap::MESSAGES => count($uids),
        ]);
        $flags = [];
        foreach ($uids as $uid) {
            $flags[$uid] = ['read' => 0, 'flagged' => 0];
        }
        $folder->setChanges($uids, $flags);
        $folder->updateState();

        return $folder;
    }

    /**
     * The IMAP status array handed to the strategy for the current poll.
     */
    protected function _status(int $modseq): array
    {
        return [
            Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 1,
            Horde_ActiveSync_Folder_Imap::UIDNEXT => 70,
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => $modseq,
            Horde_ActiveSync_Folder_Imap::MESSAGES => 2,
        ];
    }

    /**
     * @param array $messages  UID => [flags array, optional modseq].
     */
    protected function _fetchResults(array $messages): Horde_Imap_Client_Fetch_Results
    {
        $results = new Horde_Imap_Client_Fetch_Results();
        foreach ($messages as $uid => $spec) {
            $data = new Horde_Imap_Client_Data_Fetch();
            $data->setFlags($spec[0]);
            if (isset($spec[1])) {
                $data->setModSeq($spec[1]);
            }
            $results[$uid] = $data;
        }

        return $results;
    }

    /**
     * Mock a batched fetch: only requested ids are returned, an empty batch
     * yields empty results (like a real IMAP server).
     */
    protected function _mockBatchedFetch($imap, Horde_Imap_Client_Fetch_Results $all): void
    {
        $imap->method('fetch')->willReturnCallback(
            function ($mbox, $query, $options) use ($all) {
                $batch = new Horde_Imap_Client_Fetch_Results();
                foreach ($options['ids']->ids as $uid) {
                    if (isset($all[$uid])) {
                        $batch[$uid] = $all[$uid];
                    }
                }
                return $batch;
            }
        );
    }

    protected function _imapMock()
    {
        return $this->getMockBuilder(Horde_Imap_Client_Socket::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['search', 'fetch', 'vanished'])
            ->getMock();
    }

    protected function _factory($imap): Horde_ActiveSync_Interface_ImapFactory
    {
        $factory = $this->createMock(Horde_ActiveSync_Interface_ImapFactory::class);
        $factory->method('getImapOb')->willReturn($imap);

        return $factory;
    }

    protected function _logger(): Horde_Log_Logger
    {
        return new Horde_Log_Logger(new Horde_Log_Handler_Null());
    }
}
