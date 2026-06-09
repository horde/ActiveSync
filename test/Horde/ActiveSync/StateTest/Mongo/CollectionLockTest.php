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
     * Unit tests for collection-level locking on HAS_collection_lock.
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
    class CollectionLockTest extends TestCase
    {
        public function testAcquireCollectionLockUsesFindAndModify()
        {
            $lockCollection = new MongoCollectionTestDouble();
            $lockCollection->countResult = 1;
            $lockCollection->findAndModifyResult = [
                Horde_ActiveSync_State_Mongo::MONGO_ID => "user@example.com\0device\0Ftest",
            ];

            $state = $this->_newState($lockCollection);
            $this->_setProperty($state, '_type', Horde_ActiveSync::REQUEST_TYPE_SYNC);
            $this->_setProperty($state, '_collection', ['id' => 'Ftest']);

            $acquire = $this->_method($state, '_acquireCollectionLock');
            $acquire->invoke($state);

            $this->assertTrue($this->_getProperty($state, '_collectionLockHeld'));
            $this->assertNotNull($this->_getProperty($state, '_collectionLockToken'));
            $this->assertSame(1, $lockCollection->countCalls);
            $this->assertSame(1, $lockCollection->findAndModifyCalls);
        }

        public function testSaveReleasesCollectionLock()
        {
            $lockCollection = new MongoCollectionTestDouble();
            $lockCollection->updateResult = ['ok' => 1, 'n' => 1];
            $stateCollection = new MongoCollectionTestDouble();
            $stateCollection->updateResult = ['ok' => 1, 'n' => 1];

            $state = $this->_newState($lockCollection, $stateCollection);
            $this->_primeForSave($state);
            $this->_setProperty($state, '_collectionLockHeld', true);
            $this->_setProperty($state, '_collectionLockToken', 4242);
            $this->_setProperty($state, '_collectionLockFolderId', 'Ftest');

            $state->save();

            $this->assertFalse($this->_getProperty($state, '_collectionLockHeld'));
            $this->assertFalse($this->_getProperty($state, '_stateRowLockHeld'));
            $this->assertSame(1, $lockCollection->updateCalls);
            $this->assertArrayHasKey(
                Horde_ActiveSync_State_Mongo::LOCK_TOKEN,
                $lockCollection->lastUpdateQuery
            );
            $this->assertSame(
                [
                    '$unset' => [
                        Horde_ActiveSync_State_Mongo::LOCK_TOKEN => '',
                        Horde_ActiveSync_State_Mongo::LOCK_TIME => '',
                    ],
                ],
                $lockCollection->lastUpdateDoc
            );
        }

        public function testUpdateSyncStampReleasesCollectionLock()
        {
            $lockCollection = new MongoCollectionTestDouble();
            $lockCollection->updateResult = ['ok' => 1, 'n' => 1];
            $stateCollection = new MongoCollectionTestDouble();

            $state = $this->_newState($lockCollection, $stateCollection);
            $this->_setProperty($state, '_syncKey', '{test-lock}3');
            $this->_setProperty($state, '_collection', ['id' => 'Ftest']);
            $this->_setProperty($state, '_lastSyncStamp', 10);
            $this->_setProperty($state, '_thisSyncStamp', 11);
            $this->_setProperty($state, '_stateRowLockHeld', true);
            $this->_setProperty($state, '_stateLockToken', 12345);
            $this->_setProperty($state, '_collectionLockHeld', true);
            $this->_setProperty($state, '_collectionLockToken', 67890);
            $this->_setProperty($state, '_collectionLockFolderId', 'Ftest');

            $state->updateSyncStamp();

            $this->assertFalse($this->_getProperty($state, '_collectionLockHeld'));
            $this->assertSame(1, $lockCollection->updateCalls);
            $this->assertSame(
                67890,
                $lockCollection->lastUpdateQuery[Horde_ActiveSync_State_Mongo::LOCK_TOKEN]
            );
        }

        protected function _newState(
            MongoCollectionTestDouble $lockCollection,
            ?MongoCollectionTestDouble $stateCollection = null
        ) {
            $db = new MongoDbTestDoubleWithLock(
                $lockCollection,
                $stateCollection ?: new MongoCollectionTestDouble()
            );
            $ref = new \ReflectionClass(Horde_ActiveSync_State_Mongo::class);
            $state = $ref->newInstanceWithoutConstructor();
            $this->_setProperty($state, '_db', $db);

            $device = (object) [
                'id' => 'device',
                'user' => 'user@example.com',
            ];

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

    class MongoDbTestDoubleWithLock
    {
        private $_lockCollection;
        private $_stateCollection;

        public function __construct(
            MongoCollectionTestDouble $lockCollection,
            MongoCollectionTestDouble $stateCollection
        ) {
            $this->_lockCollection = $lockCollection;
            $this->_stateCollection = $stateCollection;
        }

        public function selectCollection($name)
        {
            if ($name === Horde_ActiveSync_State_Mongo::COLLECTION_LOCK) {
                return $this->_lockCollection;
            }

            return $this->_stateCollection;
        }
    }

}
