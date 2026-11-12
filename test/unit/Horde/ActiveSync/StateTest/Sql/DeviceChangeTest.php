<?php

/**
 * Unit tests for sync state ownership after a device change.
 *
 * When an account is moved to a new phone, the client keeps its sync keys
 * but reports a new DeviceId. horde_activesync_state has the sync key as its
 * only primary key, so two device ids advancing the same key series cannot
 * both store their state: the one falling behind used to repeat its SYNC
 * forever (duplicate key, HTTP 500). These tests cover the three guards that
 * keep the state of two devices apart
 * (@see \Horde_ActiveSync_State_Sql::_isOwnStateRow()).
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */

namespace Horde\ActiveSync\StateTest\Sql;

use Horde\ActiveSync\Test\Helpers\DbHelper;
use PHPUnit\Framework\TestCase;
use Horde_ActiveSync;
use Horde_ActiveSync_Exception;
use Horde_ActiveSync_Exception_StateGone;
use Horde_ActiveSync_Folder_Collection;
use Horde_ActiveSync_Folder_Imap;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_State_Sql;
use Horde_Db_Exception;
use Horde_Db_Value_Binary;
use Horde_Log_Handler_Null;
use ReflectionClass;

class DeviceChangeTest extends TestCase
{
    private const SERIES = '{a1b2c3d4-0000-0000-0000-000000000000}';
    private const FOLDER_ID = 'Ftest';
    private const USER = 'user@example.com';
    private const OLD_DEVICE = 'old-device';
    private const NEW_DEVICE = 'new-device';

    /**
     * Guard 1: the new device presents a sync key that was issued to the
     * old device. It must be told the state is gone (SYNC answers with a
     * key mismatch) instead of adopting the old device's key series.
     */
    public function testChangedDeviceCannotLoadSyncKeyOfOtherDevice()
    {
        $db = $this->_db();
        $this->_insertRow($db, 23, self::OLD_DEVICE);

        $state = $this->_newState($db, self::NEW_DEVICE);
        try {
            $state->loadState(
                $this->_collection(),
                self::SERIES . '23',
                Horde_ActiveSync::REQUEST_TYPE_SYNC,
                self::FOLDER_ID
            );
            $this->fail('Expected the foreign sync key to be rejected.');
        } catch (Horde_ActiveSync_Exception_StateGone $e) {
            // expected
        }

        $this->assertFalse($this->_getProperty($state, '_collectionLockHeld'));
        $this->assertFalse($db->transactionStarted());
        // The old device's state is untouched.
        $this->assertSame(self::OLD_DEVICE, $this->_owner($db, 23));
    }

    /**
     * Read-only loads (PING) are exempt: peeking at the copied row keeps
     * PING working until the next SYNC for the folder is reset.
     */
    public function testReadonlyLoadMayPeekAtSyncKeyOfOtherDevice()
    {
        $db = $this->_db();
        $this->_insertRow($db, 23, self::OLD_DEVICE);

        $state = $this->_newState($db, self::NEW_DEVICE);
        $state->loadState(
            $this->_collection(),
            self::SERIES . '23',
            Horde_ActiveSync::REQUEST_TYPE_SYNC,
            self::FOLDER_ID,
            ['readonly' => true]
        );

        $this->assertInstanceOf(
            Horde_ActiveSync_Folder_Collection::class,
            $this->_getProperty($state, '_folder')
        );
        $this->assertFalse($this->_getProperty($state, '_collectionLockHeld'));
    }

    /**
     * Guard 2: the new device already continued the old device's series
     * (key 24 is its row). The old device, still at key 23, can never store
     * key 24 and must be reset - without losing the new device's state.
     */
    public function testDeviceIsResetWhenAnotherDeviceContinuedItsKeySeries()
    {
        $db = $this->_db();
        $this->_insertRow($db, 23, self::OLD_DEVICE);
        $this->_insertRow($db, 24, self::NEW_DEVICE);

        $state = $this->_newState($db, self::OLD_DEVICE);
        try {
            $state->loadState(
                $this->_collection(),
                self::SERIES . '23',
                Horde_ActiveSync::REQUEST_TYPE_SYNC,
                self::FOLDER_ID
            );
            $this->fail('Expected the taken-over key series to be rejected.');
        } catch (Horde_ActiveSync_Exception_StateGone $e) {
            // expected
        }
        $this->assertFalse($this->_getProperty($state, '_collectionLockHeld'));

        // The client now restarts the folder with sync key 0.
        $state->loadState(
            $this->_collection(),
            0,
            Horde_ActiveSync::REQUEST_TYPE_SYNC,
            self::FOLDER_ID
        );

        $this->assertNull($this->_owner($db, 23));
        $this->assertSame(self::NEW_DEVICE, $this->_owner($db, 24));
    }

    /**
     * A next key owned by the same device is a repeated request (the
     * client never received the response), not a device change.
     */
    public function testOwnNextKeyDoesNotResetDevice()
    {
        $db = $this->_db();
        $this->_insertRow($db, 23, self::OLD_DEVICE);
        $this->_insertRow($db, 24, self::OLD_DEVICE);

        $state = $this->_newState($db, self::OLD_DEVICE);
        $state->loadState(
            $this->_collection(),
            self::SERIES . '23',
            Horde_ActiveSync::REQUEST_TYPE_SYNC,
            self::FOLDER_ID
        );

        $state->setNewSyncKey(self::SERIES . '24');
        $this->_setProperty($state, '_thisSyncStamp', 9);
        $state->save();

        $this->assertSame(
            '9',
            (string) $db->selectValue(
                'SELECT sync_mod FROM horde_activesync_state WHERE sync_key = ?',
                [self::SERIES . '24']
            )
        );
        $this->assertFalse($db->transactionStarted());
    }

    /**
     * Guard 3: a save must never overwrite the row of another device.
     */
    public function testSaveDoesNotOverwriteStateOfOtherDevice()
    {
        $db = $this->_db();
        $this->_insertRow($db, 24, self::NEW_DEVICE, 7);

        $state = $this->_newState($db, self::OLD_DEVICE);
        $this->_primeForSave($state, self::SERIES . '24');

        try {
            $state->save();
            $this->fail('Expected the save over a foreign row to fail.');
        } catch (Horde_ActiveSync_Exception $e) {
            // expected
        }

        $this->assertFalse($db->transactionStarted());
        $this->assertSame(self::NEW_DEVICE, $this->_owner($db, 24));
        $this->assertSame(
            '7',
            (string) $db->selectValue(
                'SELECT sync_mod FROM horde_activesync_state WHERE sync_key = ?',
                [self::SERIES . '24']
            )
        );
    }

    /**
     * PostgreSQL aborts the transaction when the INSERT fails; any further
     * statement is rejected (SQLSTATE 25P02) until it is rolled back. A
     * parallel request of the same device having inserted the row first
     * must still be recovered: roll back, start over, update.
     */
    public function testSaveRecoversFromConcurrentInsertInAbortedTransaction()
    {
        $db = $this->_abortingDb([
            'sync_devid' => self::OLD_DEVICE,
            'sync_user' => self::USER,
            'sync_folderid' => self::FOLDER_ID,
        ]);
        $db->expects($this->exactly(2))->method('beginDbTransaction');
        $db->expects($this->exactly(2))->method('updateBlob');
        $db->expects($this->once())->method('commitDbTransaction');

        $state = $this->_newState($db, self::OLD_DEVICE);
        $this->_primeForSave($state, self::SERIES . '24');

        $state->save();

        $this->assertSame(1, $this->_rollbacks);
    }

    /**
     * Same aborted transaction, but the existing row belongs to another
     * device: no second UPDATE, the save fails and nothing is committed.
     */
    public function testSaveLeavesForeignRowAloneInAbortedTransaction()
    {
        $db = $this->_abortingDb([
            'sync_devid' => self::NEW_DEVICE,
            'sync_user' => self::USER,
            'sync_folderid' => self::FOLDER_ID,
        ]);
        $db->expects($this->once())->method('updateBlob');
        $db->expects($this->never())->method('commitDbTransaction');

        $state = $this->_newState($db, self::OLD_DEVICE);
        $this->_primeForSave($state, self::SERIES . '24');

        try {
            $state->save();
            $this->fail('Expected the save over a foreign row to fail.');
        } catch (Horde_ActiveSync_Exception $e) {
            // expected
        }

        // Once to leave the aborted transaction, once for the failed save.
        $this->assertSame(2, $this->_rollbacks);
    }

    /**
     * An INSERT that failed although no row exists under the key is a
     * genuine failure and must surface.
     */
    public function testSaveReportsInsertFailureWithoutExistingRow()
    {
        $db = $this->_abortingDb(null);
        $db->expects($this->once())->method('updateBlob');
        $db->expects($this->never())->method('commitDbTransaction');

        $state = $this->_newState($db, self::OLD_DEVICE);
        $this->_primeForSave($state, self::SERIES . '24');

        $this->expectException(Horde_ActiveSync_Exception::class);
        $state->save();
    }

    /**
     * Number of rollbacks seen by the adapter from _abortingDb().
     *
     * @var integer
     */
    protected $_rollbacks = 0;

    /**
     * Mock adapter behaving like PostgreSQL around a failed INSERT: the
     * first UPDATE matches no row, the INSERT fails, and from then on every
     * statement fails with SQLSTATE 25P02 until the transaction is rolled
     * back.
     *
     * @param array|null $owner  The row returned by the owner lookup.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function _abortingDb(?array $owner)
    {
        $aborted = false;
        $updates = 0;
        $this->_rollbacks = 0;
        $failIfAborted = function () use (&$aborted) {
            if ($aborted) {
                throw new Horde_Db_Exception(
                    'SQLSTATE[25P02]: In failed sql transaction'
                );
            }
        };

        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->method('transactionStarted')->willReturn(false);
        $db->method('updateBlob')->willReturnCallback(
            function () use ($failIfAborted, &$updates) {
                $failIfAborted();
                return $updates++ ? 1 : 0;
            }
        );
        $db->method('insertBlob')->willReturnCallback(
            function () use (&$aborted) {
                $aborted = true;
                throw new Horde_Db_Exception('SQLSTATE[23505]: Unique violation');
            }
        );
        $db->method('selectOne')->willReturnCallback(
            function () use ($failIfAborted, $owner) {
                $failIfAborted();
                return $owner;
            }
        );
        $db->method('rollbackDbTransaction')->willReturnCallback(
            function () use (&$aborted) {
                $aborted = false;
                $this->_rollbacks++;
            }
        );

        return $db;
    }

    protected function _db()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension is not loaded');
        }

        $db = DbHelper::createSqliteDb([
            'migrations' => [[
                'migrationsPath' => dirname(__DIR__, 6) . '/migration/Horde/ActiveSync',
                'schemaTableName' => 'horde_activesync_schema_info',
            ]],
        ]);

        // The SQLite schema produced by the migrations ends up without the
        // primary key on sync_key that PostgreSQL has. That constraint is
        // what makes a shared key series fail, so add it back here.
        $db->execute(
            'CREATE UNIQUE INDEX test_state_sync_key'
            . ' ON horde_activesync_state (sync_key)'
        );

        return $db;
    }

    protected function _collection()
    {
        return [
            'id' => self::FOLDER_ID,
            'class' => Horde_ActiveSync::CLASS_CONTACTS,
            'serverid' => self::FOLDER_ID,
        ];
    }

    protected function _insertRow($db, $counter, $device, $mod = 1)
    {
        $folder = new Horde_ActiveSync_Folder_Collection(
            self::FOLDER_ID,
            Horde_ActiveSync::CLASS_CONTACTS
        );
        $db->insertBlob('horde_activesync_state', [
            'sync_key' => self::SERIES . $counter,
            'sync_data' => new Horde_Db_Value_Binary(serialize($folder)),
            'sync_devid' => $device,
            'sync_mod' => $mod,
            'sync_folderid' => self::FOLDER_ID,
            'sync_user' => self::USER,
            'sync_pending' => '',
            'sync_timestamp' => time(),
        ]);
    }

    /**
     * Return the device owning the state row of a key, or null if the row
     * does not exist.
     */
    protected function _owner($db, $counter)
    {
        $devid = $db->selectValue(
            'SELECT sync_devid FROM horde_activesync_state WHERE sync_key = ?',
            [self::SERIES . $counter]
        );

        return $devid ? (string) $devid : null;
    }

    protected function _newState($db, $device)
    {
        $state = new Horde_ActiveSync_State_Sql(['db' => $db]);
        $this->_setProperty(
            $state,
            '_logger',
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );
        $this->_setProperty($state, '_deviceInfo', (object) [
            'id' => $device,
            'user' => self::USER,
        ]);

        return $state;
    }

    protected function _primeForSave(Horde_ActiveSync_State_Sql $state, $syncKey)
    {
        $folder = new Horde_ActiveSync_Folder_Imap('INBOX', Horde_ActiveSync::CLASS_EMAIL);
        $this->_setProperty($state, '_type', Horde_ActiveSync::REQUEST_TYPE_SYNC);
        $this->_setProperty($state, '_folder', $folder);
        $this->_setProperty($state, '_syncKey', $syncKey);
        $this->_setProperty($state, '_collection', [
            'id' => self::FOLDER_ID,
            'class' => Horde_ActiveSync::CLASS_EMAIL,
        ]);
        $this->_setProperty($state, '_thisSyncStamp', 100);
        $this->_setProperty($state, '_changes', null);
    }

    protected function _getProperty($object, $name)
    {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    protected function _setProperty($object, $name, $value)
    {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
