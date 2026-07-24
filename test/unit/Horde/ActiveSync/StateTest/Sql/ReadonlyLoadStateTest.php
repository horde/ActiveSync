<?php

/**
 * Unit tests for the read-only (PING/heartbeat polling) state load path.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */

namespace Horde\ActiveSync\StateTest\Sql;

use Horde\ActiveSync\Test\Helpers\DbHelper;
use PHPUnit\Framework\TestCase;
use Horde_ActiveSync;
use Horde_ActiveSync_Exception_StateGone;
use Horde_ActiveSync_Folder_Imap;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_State_Sql;
use Horde_Db_Value_Binary;
use Horde_Log_Handler_Null;
use ReflectionClass;

class ReadonlyLoadStateTest extends TestCase
{
    private const SYNCKEY = '{6a13541c-a3bc-448b-853c-915b00000000}14';
    private const FOLDER_ID = 'F1';

    /**
     * A read-only load must not take the collection or state row lock, and
     * a repeated load of the same synckey must be served from memory (no
     * database access) with a freshly rehydrated folder object.
     */
    public function testReadonlyLoadIsLockFreeAndMemoized()
    {
        [$state, $db] = $this->_stateWithRow();
        $collection = [
            'id' => self::FOLDER_ID,
            'class' => Horde_ActiveSync::CLASS_EMAIL,
            'serverid' => 'INBOX',
        ];

        $state->loadState(
            $collection,
            self::SYNCKEY,
            Horde_ActiveSync::REQUEST_TYPE_SYNC,
            self::FOLDER_ID,
            ['readonly' => true]
        );

        $this->assertFalse($this->_getProperty($state, '_collectionLockHeld'));
        $this->assertFalse($this->_getProperty($state, '_stateRowLockHeld'));
        $folderFirst = $this->_getProperty($state, '_folder');
        $this->assertInstanceOf(Horde_ActiveSync_Folder_Imap::class, $folderFirst);
        $this->assertEquals('INBOX', $folderFirst->serverid());

        // Remove the row: a successful repeat load proves the memo is used
        // and no SQL was issued.
        $db->delete(
            'DELETE FROM horde_activesync_state WHERE sync_key = ?',
            [self::SYNCKEY]
        );

        $state->loadState(
            $collection,
            self::SYNCKEY,
            Horde_ActiveSync::REQUEST_TYPE_SYNC,
            self::FOLDER_ID,
            ['readonly' => true]
        );
        $folderSecond = $this->_getProperty($state, '_folder');
        $this->assertEquals('INBOX', $folderSecond->serverid());
        // Rehydrated from the raw blob: pristine copy, not the same object.
        $this->assertNotSame($folderFirst, $folderSecond);
    }

    /**
     * A synckey change must bypass the memo and read fresh data.
     */
    public function testReadonlyLoadMissesMemoOnSynckeyChange()
    {
        [$state, $db] = $this->_stateWithRow();
        $collection = [
            'id' => self::FOLDER_ID,
            'class' => Horde_ActiveSync::CLASS_EMAIL,
            'serverid' => 'INBOX',
        ];

        $state->loadState(
            $collection,
            self::SYNCKEY,
            Horde_ActiveSync::REQUEST_TYPE_SYNC,
            self::FOLDER_ID,
            ['readonly' => true]
        );

        // A parallel SYNC advanced the state; the old row is gone and the
        // new synckey has no row yet in this fixture.
        $db->delete(
            'DELETE FROM horde_activesync_state WHERE sync_key = ?',
            [self::SYNCKEY]
        );

        $this->expectException(Horde_ActiveSync_Exception_StateGone::class);
        $state->loadState(
            $collection,
            '{6a13541c-a3bc-448b-853c-915b00000000}15',
            Horde_ActiveSync::REQUEST_TYPE_SYNC,
            self::FOLDER_ID,
            ['readonly' => true]
        );
    }

    /**
     * A mutating (non read-only) load must invalidate the memo and hit the
     * database again.
     */
    public function testMutatingLoadInvalidatesMemo()
    {
        [$state, $db] = $this->_stateWithRow();
        $collection = [
            'id' => self::FOLDER_ID,
            'class' => Horde_ActiveSync::CLASS_EMAIL,
            'serverid' => 'INBOX',
        ];

        $state->loadState(
            $collection,
            self::SYNCKEY,
            Horde_ActiveSync::REQUEST_TYPE_SYNC,
            self::FOLDER_ID,
            ['readonly' => true]
        );

        $db->delete(
            'DELETE FROM horde_activesync_state WHERE sync_key = ?',
            [self::SYNCKEY]
        );

        // The mutating load must NOT be served from the memo.
        $this->expectException(Horde_ActiveSync_Exception_StateGone::class);
        $state->loadState(
            $collection,
            self::SYNCKEY,
            Horde_ActiveSync::REQUEST_TYPE_SYNC,
            self::FOLDER_ID
        );
    }

    /**
     * Create a state object backed by SQLite with one persisted sync state
     * row for FOLDER_ID / SYNCKEY.
     *
     * @return array  [Horde_ActiveSync_State_Sql, Horde_Db_Adapter]
     */
    protected function _stateWithRow()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension is not loaded');
        }

        $migrationDir = dirname(__DIR__, 6) . '/migration/Horde/ActiveSync';
        $db = DbHelper::createSqliteDb([
            'migrations' => [[
                'migrationsPath' => $migrationDir,
                'schemaTableName' => 'horde_activesync_schema_info',
            ]],
        ]);

        $folder = new Horde_ActiveSync_Folder_Imap('INBOX', Horde_ActiveSync::CLASS_EMAIL);
        $folder->setStatus([
            Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 1,
            Horde_ActiveSync_Folder_Imap::UIDNEXT => 100,
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 500,
            Horde_ActiveSync_Folder_Imap::MESSAGES => 10,
        ]);
        $folder->updateState();

        $db->insertBlob('horde_activesync_state', [
            'sync_key' => self::SYNCKEY,
            'sync_data' => new Horde_Db_Value_Binary(serialize($folder)),
            'sync_devid' => 'device',
            'sync_mod' => 500,
            'sync_folderid' => self::FOLDER_ID,
            'sync_user' => 'user@example.com',
            'sync_pending' => '',
            'sync_timestamp' => time(),
        ]);

        $state = new Horde_ActiveSync_State_Sql(['db' => $db]);
        $this->_setProperty(
            $state,
            '_logger',
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );
        $this->_setProperty($state, '_deviceInfo', (object) [
            'id' => 'device',
            'user' => 'user@example.com',
        ]);

        return [$state, $db];
    }

    protected function _getProperty($object, $property)
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($object);
    }

    protected function _setProperty($object, $property, $value)
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
