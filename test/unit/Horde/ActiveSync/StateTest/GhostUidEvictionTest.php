<?php

/**
 * Unit tests for deferred "ghost" item eviction through the change pipeline.
 *
 * Ghost uids recorded in the IMAP folder state (mail the client still holds
 * although it is gone from IMAP and untracked in state) must be exported as
 * synthetic deletions by Horde_ActiveSync_State_Base::getChanges() and
 * cleared once the deletion is acknowledged as exported.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\StateTest;

use Horde_ActiveSync;
use Horde_ActiveSync_Folder_Imap;
use Horde_ActiveSync_State_Base;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Horde_ActiveSync_State_Base::class)]
class GhostUidEvictionTest extends TestCase
{
    public function testGhostUidInjectedAsDeletion()
    {
        $state = $this->_state(['999'], [100, 101]);

        $this->_injectGhostUidDeletions($state);

        $this->assertEquals(
            [[
                'id' => '999',
                'type' => Horde_ActiveSync::CHANGE_TYPE_DELETE,
            ]],
            $this->_changes($state)
        );
    }

    public function testGhostUidAppendedToExistingChanges()
    {
        $existing = [
            ['id' => 101, 'type' => Horde_ActiveSync::CHANGE_TYPE_FLAGS, 'flags' => ['read' => 1]],
        ];
        $state = $this->_state(['999'], [100, 101], $existing);

        $this->_injectGhostUidDeletions($state);

        $changes = $this->_changes($state);
        $this->assertCount(2, $changes);
        $this->assertEquals(
            ['id' => '999', 'type' => Horde_ActiveSync::CHANGE_TYPE_DELETE],
            $changes[1]
        );
    }

    public function testGhostUidNotInjectedTwiceWhenDeletionAlreadyPending()
    {
        $existing = [
            ['id' => '999', 'type' => Horde_ActiveSync::CHANGE_TYPE_DELETE],
        ];
        $state = $this->_state(['999'], [100, 101], $existing);

        $this->_injectGhostUidDeletions($state);

        $this->assertCount(1, $this->_changes($state));
    }

    public function testTrackedUidIsDroppedFromGhostListWithoutInjection()
    {
        // The uid is tracked in folder state (again): the regular diff
        // engine owns its lifecycle.
        $state = $this->_state([101], [100, 101]);

        $this->_injectGhostUidDeletions($state);

        $this->assertEquals([], $this->_changes($state));
        $this->assertEquals([], $this->_folder($state)->ghostUids());
    }

    public function testNoInjectionIntoBareUidInitialSyncBatch()
    {
        // Initial sync exports bare uid lists; change hashes must not be
        // mixed into that structure.
        $state = $this->_state(['999'], [], [100, 101, 102]);

        $this->_injectGhostUidDeletions($state);

        $this->assertEquals([100, 101, 102], $this->_changes($state));
        $this->assertEquals(['999'], $this->_folder($state)->ghostUids());
    }

    public function testExportedDeletionClearsGhostUid()
    {
        $state = $this->_state(['999'], [100, 101]);

        $ref = new ReflectionClass($state);
        $method = $ref->getMethod('_acknowledgeExportedChange');
        $method->setAccessible(true);
        $method->invoke(
            $state,
            Horde_ActiveSync::CHANGE_TYPE_DELETE,
            ['id' => '999']
        );

        $this->assertEquals([], $this->_folder($state)->ghostUids());
    }

    public function testExportedChangeDoesNotClearGhostUid()
    {
        $state = $this->_state(['999'], [100, 101]);

        $ref = new ReflectionClass($state);
        $method = $ref->getMethod('_acknowledgeExportedChange');
        $method->setAccessible(true);
        $method->invoke(
            $state,
            Horde_ActiveSync::CHANGE_TYPE_CHANGE,
            ['id' => 100]
        );

        $this->assertEquals(['999'], $this->_folder($state)->ghostUids());
    }

    /**
     * Build a state fixture with an IMAP folder state.
     *
     * @param array $ghostUids  Recorded ghost uids.
     * @param array $stateUids  Uids tracked in the folder state.
     * @param array $changes    Preexisting change set.
     *
     * @return Horde_ActiveSync_State_Base
     */
    protected function _state(
        array $ghostUids,
        array $stateUids,
        array $changes = []
    ) {
        $folder = new Horde_ActiveSync_Folder_Imap(
            'INBOX',
            Horde_ActiveSync::CLASS_EMAIL
        );
        if (count($stateUids)) {
            $folder->setStatus([
                Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 100,
                Horde_ActiveSync_Folder_Imap::UIDNEXT => max($stateUids) + 1,
                Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 200,
            ]);
            $folder->setChanges($stateUids);
            $folder->updateState();
        }
        $folder->addGhostUids($ghostUids);

        $state = $this->getMockForAbstractClass(
            Horde_ActiveSync_State_Base::class,
            [[]]
        );

        $ref = new ReflectionClass(Horde_ActiveSync_State_Base::class);
        foreach ([
            '_folder' => $folder,
            '_changes' => $changes,
            '_collection' => [
                'id' => 'F1',
                'class' => Horde_ActiveSync::CLASS_EMAIL,
            ],
        ] as $property => $value) {
            $prop = $ref->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($state, $value);
        }

        return $state;
    }

    protected function _injectGhostUidDeletions($state)
    {
        $ref = new ReflectionClass($state);
        $method = $ref->getMethod('_injectGhostUidDeletions');
        $method->setAccessible(true);
        $method->invoke($state);
    }

    protected function _changes($state)
    {
        $ref = new ReflectionClass(Horde_ActiveSync_State_Base::class);
        $prop = $ref->getProperty('_changes');
        $prop->setAccessible(true);

        return $prop->getValue($state);
    }

    protected function _folder($state)
    {
        return $state->getFolderState();
    }
}
