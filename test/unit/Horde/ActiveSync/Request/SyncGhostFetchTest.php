<?php

/**
 * Unit tests for ghost item eviction on failed Sync Fetch requests.
 *
 * A "ghost" item is mail the client still holds although it was deleted
 * through another vector (IMAP client, webmail) and the server's folder
 * state no longer tracks it. Some clients (iOS Mail) never act on the
 * Fetch STATUS_NOTFOUND reply and retry the Fetch indefinitely; the server
 * self-heals by recording such ids in the folder state and exporting a
 * synthetic deletion through the regular change pipeline on a later
 * GetChanges Sync.
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
use Horde_ActiveSync_Connector_Exporter_Sync;
use Horde_ActiveSync_Folder_Imap;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Request_Sync;
use Horde_ActiveSync_State_Base;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Horde_ActiveSync_Request_Sync::class)]
class SyncGhostFetchTest extends TestCase
{
    public function testGhostFetchIdDetectedWhenUntrackedInFolderState()
    {
        $sync = $this->_syncRequest([100, 101, 102], ['999']);

        $this->assertSame(
            ['999'],
            $this->_getGhostFetchIds($sync, $this->_emailCollection(['999']))
        );
    }

    public function testFailedFetchOfTrackedIdIsNotEvicted()
    {
        // Item known to the folder state: the regular change diff engine is
        // responsible for its deletion (or the failure was transient).
        // Loose comparison must match the client's string id against the
        // integer uid in state.
        $sync = $this->_syncRequest([100, 101, 102], ['102']);

        $this->assertSame(
            [],
            $this->_getGhostFetchIds($sync, $this->_emailCollection(['102']))
        );
    }

    public function testMixedFailuresOnlyEvictUntrackedIds()
    {
        $sync = $this->_syncRequest([100, 101, 102], ['102', '999']);

        $this->assertSame(
            ['999'],
            $this->_getGhostFetchIds($sync, $this->_emailCollection(['102', '999']))
        );
    }

    public function testNoEvictionForNonEmailCollections()
    {
        $sync = $this->_syncRequest([100, 101, 102], ['999']);

        $collection = $this->_emailCollection(['999']);
        $collection['class'] = Horde_ActiveSync::CLASS_CALENDAR;

        $this->assertSame([], $this->_getGhostFetchIds($sync, $collection));
    }

    public function testNoEvictionWithoutImapFolderState()
    {
        $sync = $this->_syncRequest([], ['999'], false);

        $this->assertSame(
            [],
            $this->_getGhostFetchIds($sync, $this->_emailCollection(['999']))
        );
    }

    /**
     * Build a Sync request handler with mocked state and exporter.
     *
     * @param array $stateUids     Uids tracked in the IMAP folder state.
     * @param array $failedIds     Fetch ids that failed with NotFound.
     * @param boolean $imapState   Provide an IMAP folder state object.
     */
    protected function _syncRequest(
        array $stateUids,
        array $failedIds,
        $imapState = true
    ) {
        $ref = new ReflectionClass(Horde_ActiveSync_Request_Sync::class);
        $sync = $ref->newInstanceWithoutConstructor();

        if ($imapState) {
            $folder = new Horde_ActiveSync_Folder_Imap(
                'INBOX',
                Horde_ActiveSync::CLASS_EMAIL
            );
            if (count($stateUids)) {
                $flags = [];
                foreach ($stateUids as $uid) {
                    $flags[$uid] = ['read' => 0, 'flagged' => 0];
                }
                $folder->setChanges($stateUids, $flags);
                $folder->setStatus([
                    Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 100,
                    Horde_ActiveSync_Folder_Imap::UIDNEXT => max($stateUids) + 1,
                ]);
                $folder->updateState();
            }
            $folderState = $folder;
        } else {
            // FOLDERSYNC-style state.
            $folderState = [];
        }

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getFolderState')->willReturn($folderState);

        $exporter = $this->createMock(Horde_ActiveSync_Connector_Exporter_Sync::class);
        $exporter->method('getFailedFetchIds')->willReturn($failedIds);

        foreach ([
            '_state' => $state,
            '_logger' => new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null()),
        ] as $property => $value) {
            $prop = $ref->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($sync, $value);
        }

        return (object) [
            'request' => $sync,
            'exporter' => $exporter,
        ];
    }

    protected function _emailCollection(array $fetchids)
    {
        return [
            'id' => 'F1',
            'class' => Horde_ActiveSync::CLASS_EMAIL,
            'fetchids' => $fetchids,
        ];
    }

    protected function _getGhostFetchIds(object $fixture, array $collection)
    {
        $ref = new ReflectionClass($fixture->request);
        $method = $ref->getMethod('_getGhostFetchIds');
        $method->setAccessible(true);

        return $method->invoke(
            $fixture->request,
            $fixture->exporter,
            $collection
        );
    }
}
