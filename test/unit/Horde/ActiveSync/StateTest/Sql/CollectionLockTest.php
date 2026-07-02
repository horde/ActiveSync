<?php

/**
 * Unit tests for collection-level locking on horde_activesync_collection_lock.
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
use Horde_ActiveSync_Exception_TemporaryFailure;
use Horde_ActiveSync_Folder_Collection;
use Horde_ActiveSync_State_Sql;
use Horde_ActiveSync_Folder_Imap;
use Horde_ActiveSync_Log_Logger;
use Horde_Db_Adapter_Pdo_Sqlite;
use Horde_Log_Handler_Null;
use ReflectionClass;

/**
 * tables() is provided by the schema object via __call() on real adapters,
 * so it must be declared explicitly for mocking.
 */
interface DbAdapterWithTables extends \Horde_Db_Adapter
{
    public function tables();
}

class CollectionLockTest extends TestCase
{
    public function testAcquireAndReleaseCollectionLockOnSqlite()
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

        $state = $this->_newState($db);
        $this->_setProperty($state, '_type', Horde_ActiveSync::REQUEST_TYPE_SYNC);
        $this->_setProperty($state, '_collection', ['id' => 'Ftest']);

        $acquire = $this->_method($state, '_acquireCollectionLock');
        $acquire->invoke($state);

        $this->assertTrue($this->_getProperty($state, '_collectionLockHeld'));

        $release = $this->_method($state, '_releaseCollectionLock');
        $release->invoke($state, true);

        $this->assertFalse($this->_getProperty($state, '_collectionLockHeld'));
        $row = $db->selectOne(
            'SELECT lock_token, lock_time FROM horde_activesync_collection_lock'
            . ' WHERE sync_user = ? AND sync_devid = ? AND sync_folderid = ?',
            ['user@example.com', 'device', 'Ftest']
        );
        $this->assertNull($row['lock_token']);
        $this->assertNull($row['lock_time']);
    }

    public function testSaveCommitsCollectionLockTransaction()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->method('transactionStarted')->willReturn(true);
        $db->expects($this->once())->method('updateBlob')->willReturn(true);
        $db->expects($this->once())->method('commitDbTransaction');
        $db->expects($this->never())->method('rollbackDbTransaction');

        $state = $this->_newState($db);
        $this->_primeForSave($state);
        $this->_setProperty($state, '_stateRowLockTxnOwner', false);
        $this->_setProperty($state, '_collectionLockHeld', true);
        $this->_setProperty($state, '_collectionLockTxnOwner', true);
        $this->_setProperty($state, '_collectionLockToken', 42);
        $this->_setProperty($state, '_collectionLockFolderId', 'Ftest');
        $db->expects($this->once())->method('update')->willReturn(1);

        $state->save();
        $this->assertFalse($this->_getProperty($state, '_collectionLockHeld'));
        $this->assertFalse($this->_getProperty($state, '_stateRowLockHeld'));
    }

    public function testHeldLockThrowsTemporaryFailure()
    {
        $db = $this->createMock(DbAdapterWithTables::class);
        $db->method('tables')->willReturn(['horde_activesync_collection_lock']);
        $db->method('transactionStarted')->willReturn(false);
        $db->method('selectValue')->willReturn(1);
        $db->method('selectOne')->willReturn([
            'lock_token' => 42,
            'lock_time' => time(),
        ]);
        $db->expects($this->atLeastOnce())->method('rollbackDbTransaction');

        $state = $this->_newState($db);
        $this->_setProperty($state, '_type', Horde_ActiveSync::REQUEST_TYPE_SYNC);
        $this->_setProperty($state, '_collection', ['id' => 'Ftest']);

        $this->expectException(Horde_ActiveSync_Exception_TemporaryFailure::class);
        $this->_method($state, '_acquireCollectionLock')->invoke($state);
    }

    public function testLoadStateResetWithEmptyCollectionEmitsNoWarnings()
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

        $state = $this->_newState($db);

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;
            return true;
        }, E_WARNING | E_NOTICE);
        try {
            // Error-recovery resets pass an empty collection array.
            $state->loadState([], null, Horde_ActiveSync::REQUEST_TYPE_SYNC, 'Ftest');
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
        $this->assertInstanceOf(
            Horde_ActiveSync_Folder_Collection::class,
            $this->_getProperty($state, '_folder')
        );
        $this->assertFalse($this->_getProperty($state, '_collectionLockHeld'));
    }

    public function testSkipsCollectionLockWhenTableMissing()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension is not loaded');
        }

        $db = new Horde_Db_Adapter_Pdo_Sqlite([
            'dbname' => ':memory:',
            'charset' => 'utf-8',
        ]);

        $state = $this->_newState($db);
        $acquire = $this->_method($state, '_acquireCollectionLock');
        $acquire->invoke($state);

        $this->assertFalse($this->_getProperty($state, '_collectionLockHeld'));
    }

    protected function _newState($db)
    {
        $state = new Horde_ActiveSync_State_Sql(['db' => $db]);

        $device = (object) [
            'id' => 'device',
            'user' => 'user@example.com',
        ];

        $this->_setProperty(
            $state,
            '_logger',
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );
        $this->_setProperty($state, '_deviceInfo', $device);

        return $state;
    }

    protected function _primeForSave(Horde_ActiveSync_State_Sql $state)
    {
        $folder = new Horde_ActiveSync_Folder_Imap('INBOX', Horde_ActiveSync::CLASS_EMAIL);
        $this->_setProperty($state, '_type', Horde_ActiveSync::REQUEST_TYPE_SYNC);
        $this->_setProperty($state, '_folder', $folder);
        $this->_setProperty($state, '_syncKey', '{test-lock}2');
        $this->_setProperty($state, '_collection', [
            'id' => 'Ftest',
            'class' => Horde_ActiveSync::CLASS_EMAIL,
        ]);
        $this->_setProperty($state, '_thisSyncStamp', 100);
        $this->_setProperty($state, '_changes', null);
        $this->_setProperty($state, '_stateRowLockHeld', true);
    }

    protected function _setProperty($object, $name, $value)
    {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    protected function _getProperty($object, $name)
    {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    protected function _method($object, $name)
    {
        $ref = new ReflectionClass($object);
        $method = $ref->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}
