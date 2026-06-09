<?php

namespace {
    if (!interface_exists('Horde_Mongo_Collection_Index', false)) {
        interface Horde_Mongo_Collection_Index
        {
            public function checkMongoIndices();
            public function createMongoIndices();
        }
    }
}

namespace Horde\ActiveSync\StateTest\Mongo {

/**
 * Unit tests for document-level locking on HAS_state loads.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */

use Horde_Test_Case as TestCase;
use Horde_ActiveSync;
use Horde_ActiveSync_State_Mongo;

/**
 * @coversNothing
 */
class RowLockTest extends TestCase
{
    public function testAcquireStateRowLockUsesFindAndModify()
    {
        $collection = new MongoCollectionTestDouble();
        $collection->countResult = 1;
        $collection->findAndModifyResult = [
            Horde_ActiveSync_State_Mongo::SYNC_DATA => '',
            Horde_ActiveSync_State_Mongo::SYNC_DEVID => 'device',
            Horde_ActiveSync_State_Mongo::SYNC_MOD => 1,
            Horde_ActiveSync_State_Mongo::SYNC_PENDING => '',
        ];

        $state = $this->_newState($collection);
        $this->_setProperty($state, '_syncKey', '{test-lock}1');
        $this->_setProperty($state, '_collection', ['id' => 'Ftest']);

        $acquire = $this->_method($state, '_acquireStateRowLock');
        $acquire->invoke($state);

        $this->assertTrue($this->_getProperty($state, '_stateRowLockHeld'));
        $this->assertNotNull($this->_getProperty($state, '_stateLockToken'));
        $this->assertSame(1, $collection->countCalls);
        $this->assertSame(1, $collection->findAndModifyCalls);
    }

    public function testSaveReleasesDocumentLock()
    {
        $collection = new MongoCollectionTestDouble();
        $collection->updateResult = ['ok' => 1, 'n' => 1];

        $state = $this->_newState($collection);
        $this->_primeForSave($state);

        $state->save();

        $this->assertFalse($this->_getProperty($state, '_stateRowLockHeld'));
        $this->assertSame(1, $collection->updateCalls);
        $this->assertArrayHasKey(
            Horde_ActiveSync_State_Mongo::SYNC_LOCK,
            $collection->lastUpdateQuery
        );
        $this->assertArrayHasKey(
            Horde_ActiveSync_State_Mongo::SYNC_LOCK,
            $collection->lastUpdateDoc['$unset']
        );
    }

    public function testUpdateSyncStampReleasesUnusedLock()
    {
        $collection = new MongoCollectionTestDouble();
        $collection->updateResult = ['ok' => 1, 'n' => 1];

        $state = $this->_newState($collection);
        $this->_setProperty($state, '_syncKey', '{test-lock}3');
        $this->_setProperty($state, '_collection', ['id' => 'Ftest']);
        $this->_setProperty($state, '_lastSyncStamp', 10);
        $this->_setProperty($state, '_thisSyncStamp', 11);
        $this->_setProperty($state, '_stateRowLockHeld', true);
        $this->_setProperty($state, '_stateLockToken', 12345);

        $state->updateSyncStamp();

        $this->assertFalse($this->_getProperty($state, '_stateRowLockHeld'));
        $this->assertSame(1, $collection->updateCalls);
        $this->assertSame(
            ['$unset' => [Horde_ActiveSync_State_Mongo::SYNC_LOCK => '']],
            $collection->lastUpdateDoc
        );
    }

    protected function _newState(MongoCollectionTestDouble $collection)
    {
        $db = new MongoDbTestDouble($collection);
        $ref = new \ReflectionClass(Horde_ActiveSync_State_Mongo::class);
        $state = $ref->newInstanceWithoutConstructor();
        $this->_setProperty($state, '_db', $db);

        $device = $this->getMockBuilder('Horde_ActiveSync_Device')
            ->disableOriginalConstructor()
            ->getMock();
        $device->id = 'device';
        $device->user = 'user@example.com';

        $this->_setProperty(
            $state,
            '_logger',
            new \Horde_ActiveSync_Log_Logger(new \Horde_Log_Handler_Null())
        );
        $this->_setProperty($state, '_deviceInfo', $device);

        return $state;
    }

    protected function _primeForSave(Horde_ActiveSync_State_Mongo $state)
    {
        $folder = new \Horde_ActiveSync_Folder_Imap('INBOX', Horde_ActiveSync::CLASS_EMAIL);
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
        $this->_setProperty($state, '_stateLockToken', 99999);
    }

    protected function _setProperty($object, $name, $value)
    {
        $ref = new \ReflectionClass($object);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    protected function _getProperty($object, $name)
    {
        $ref = new \ReflectionClass($object);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    protected function _method($object, $name)
    {
        $ref = new \ReflectionClass($object);
        $method = $ref->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}

class MongoCollectionTestDouble
{
    public $countResult = 0;
    public $findAndModifyResult = [];
    public $updateResult = ['ok' => 1, 'n' => 1];
    public $countCalls = 0;
    public $findAndModifyCalls = 0;
    public $updateCalls = 0;
    public $lastUpdateQuery = [];
    public $lastUpdateDoc = [];

    public function count($query)
    {
        ++$this->countCalls;
        return $this->countResult;
    }

    public function findAndModify($query, $update, $fields = [], $options = [])
    {
        ++$this->findAndModifyCalls;
        return $this->findAndModifyResult;
    }

    public function update($query, $update, $options = [])
    {
        ++$this->updateCalls;
        $this->lastUpdateQuery = $query;
        $this->lastUpdateDoc = $update;
        return $this->updateResult;
    }

    public function insert($document)
    {
    }

    public function remove($query)
    {
    }
}

class MongoDbTestDouble
{
    private $_collection;

    public function __construct(MongoCollectionTestDouble $collection)
    {
        $this->_collection = $collection;
    }

    public function selectCollection($name)
    {
        return $this->_collection;
    }
}

}
