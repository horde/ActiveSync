<?php

/**
 * Unit tests for the HAS_state document lock across a load/save cycle.
 *
 * _loadState() locks the document of the sync key the state is loaded from.
 * Before that lock is released the instance moves on: setNewSyncKey() sets
 * the key of the state to be saved, and loadState() sets the next key and
 * collection. These tests cover that the lock keeps addressing the document
 * it was taken on
 * (@see \Horde_ActiveSync_State_Mongo::$_stateLockSyncKey), and that the
 * FOLDERSYNC state, which has no collection id, can be loaded.
 *
 * No MongoDB server is involved: the driver runs against an in-memory
 * stand-in for the legacy MongoCollection API
 * (@see InMemoryMongoCollection).
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

    use PHPUnit\Framework\TestCase;
    use Horde_ActiveSync;
    use Horde_ActiveSync_Exception;
    use Horde_ActiveSync_Folder_Imap;
    use Horde_ActiveSync_Log_Logger;
    use Horde_ActiveSync_State_Mongo;
    use Horde_Log_Handler_Null;
    use ReflectionClass;

    class LockedDocumentTest extends TestCase
    {
        private const SERIES = '{a1b2c3d4-0000-0000-0000-000000000000}';
        private const OTHER_SERIES = '{e5f6a7b8-0000-0000-0000-000000000000}';
        private const FOLDER_ID = 'Ftest';
        private const OTHER_FOLDER_ID = 'Fother';
        private const USER = 'user@example.com';
        private const DEVICE = 'device';

        /**
         * The normal SYNC cycle: load key N, answer with key N+1, save. The
         * new key is a new document, and the lock on document N is released.
         */
        public function testSaveUnderNewSyncKeyInsertsDocumentAndReleasesLock()
        {
            $collection = new InMemoryMongoCollection();
            $this->_insertDoc($collection, self::SERIES . '23', self::FOLDER_ID);

            $state = $this->_newState($collection, self::SERIES . '23', self::FOLDER_ID);
            $this->_loadState($state);
            $this->_primeForSave($state);
            $state->setNewSyncKey(self::SERIES . '24');
            $state->save();

            $this->assertArrayHasKey(self::SERIES . '24', $collection->docs);
            $new = $collection->docs[self::SERIES . '24'];
            $this->assertSame(2000, $new[Horde_ActiveSync_State_Mongo::SYNC_MOD]);
            $this->assertArrayNotHasKey(Horde_ActiveSync_State_Mongo::SYNC_LOCK, $new);

            $old = $collection->docs[self::SERIES . '23'];
            $this->assertSame(1000, $old[Horde_ActiveSync_State_Mongo::SYNC_MOD]);
            $this->assertArrayNotHasKey(Horde_ActiveSync_State_Mongo::SYNC_LOCK, $old);
            $this->assertFalse($this->_getProperty($state, '_stateRowLockHeld'));
        }

        /**
         * A state saved under the key it was loaded with updates the locked
         * document in place and releases the lock with the same write.
         */
        public function testSaveUnderLoadedSyncKeyUpdatesLockedDocument()
        {
            $collection = new InMemoryMongoCollection();
            $this->_insertDoc($collection, self::SERIES . '23', self::FOLDER_ID);

            $state = $this->_newState($collection, self::SERIES . '23', self::FOLDER_ID);
            $this->_loadState($state);
            $this->_primeForSave($state);
            $state->save();

            $this->assertCount(1, $collection->docs);
            $doc = $collection->docs[self::SERIES . '23'];
            $this->assertSame(2000, $doc[Horde_ActiveSync_State_Mongo::SYNC_MOD]);
            $this->assertArrayNotHasKey(Horde_ActiveSync_State_Mongo::SYNC_LOCK, $doc);
            $this->assertFalse($this->_getProperty($state, '_stateRowLockHeld'));
        }

        /**
         * A save that fails under a new sync key still releases the lock on
         * the document the state was loaded from.
         */
        public function testFailedSaveUnderNewSyncKeyReleasesLock()
        {
            $collection = new InMemoryMongoCollection();
            $this->_insertDoc($collection, self::SERIES . '23', self::FOLDER_ID);

            $state = $this->_newState($collection, self::SERIES . '23', self::FOLDER_ID);
            $this->_loadState($state);
            $this->_primeForSave($state);
            $state->setNewSyncKey(self::SERIES . '24');
            $collection->failInsert = true;
            try {
                $state->save();
                $this->fail('A failed insert must fail the save.');
            } catch (Horde_ActiveSync_Exception $e) {
            }

            $this->assertArrayNotHasKey(self::SERIES . '24', $collection->docs);
            $this->assertArrayNotHasKey(
                Horde_ActiveSync_State_Mongo::SYNC_LOCK,
                $collection->docs[self::SERIES . '23']
            );
            $this->assertFalse($this->_getProperty($state, '_stateRowLockHeld'));
        }

        /**
         * Loading the state of another folder (PING polls one folder after
         * the other) releases the lock on the previously loaded document,
         * although the current key and collection already are the new ones.
         */
        public function testLoadingAnotherFolderReleasesPreviousLock()
        {
            $collection = new InMemoryMongoCollection();
            $this->_insertDoc($collection, self::SERIES . '23', self::FOLDER_ID);
            $this->_insertDoc($collection, self::OTHER_SERIES . '7', self::OTHER_FOLDER_ID);

            $state = $this->_newState($collection, self::SERIES . '23', self::FOLDER_ID);
            $this->_loadState($state);
            $this->assertArrayHasKey(
                Horde_ActiveSync_State_Mongo::SYNC_LOCK,
                $collection->docs[self::SERIES . '23']
            );

            // What loadState() does before it calls _loadState().
            $this->_setProperty($state, '_syncKey', self::OTHER_SERIES . '7');
            $this->_setProperty($state, '_collection', [
                'id' => self::OTHER_FOLDER_ID,
                'class' => Horde_ActiveSync::CLASS_EMAIL,
            ]);
            $this->_loadState($state);

            $this->assertArrayNotHasKey(
                Horde_ActiveSync_State_Mongo::SYNC_LOCK,
                $collection->docs[self::SERIES . '23']
            );
            $this->assertArrayHasKey(
                Horde_ActiveSync_State_Mongo::SYNC_LOCK,
                $collection->docs[self::OTHER_SERIES . '7']
            );
        }

        /**
         * The FOLDERSYNC state has no collection id; it is stored and found
         * under the FOLDERSYNC marker.
         */
        public function testFolderSyncStateIsLoadedAndSavedUnderNewSyncKey()
        {
            $collection = new InMemoryMongoCollection();
            $this->_insertDoc(
                $collection,
                self::SERIES . '5',
                Horde_ActiveSync::REQUEST_TYPE_FOLDERSYNC,
                serialize([])
            );

            $state = $this->_newState($collection, self::SERIES . '5', null);
            $this->_setProperty($state, '_type', Horde_ActiveSync::REQUEST_TYPE_FOLDERSYNC);
            $this->_loadState($state);
            $this->assertTrue($this->_getProperty($state, '_stateRowLockHeld'));

            $this->_setProperty($state, '_thisSyncStamp', 2000);
            $state->setNewSyncKey(self::SERIES . '6');
            $state->save();

            $this->assertSame(
                Horde_ActiveSync::REQUEST_TYPE_FOLDERSYNC,
                $collection->docs[self::SERIES . '6'][Horde_ActiveSync_State_Mongo::SYNC_FOLDERID]
            );
            $this->assertArrayNotHasKey(
                Horde_ActiveSync_State_Mongo::SYNC_LOCK,
                $collection->docs[self::SERIES . '5']
            );
        }

        protected function _newState(InMemoryMongoCollection $collection, $syncKey, $folderId)
        {
            $ref = new ReflectionClass(Horde_ActiveSync_State_Mongo::class);
            $state = $ref->newInstanceWithoutConstructor();
            $this->_setProperty($state, '_db', new InMemoryMongoDb($collection));
            $this->_setProperty(
                $state,
                '_logger',
                new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
            );
            // A plain object: a mocked Horde_ActiveSync_Device swallows the
            // magic property writes and would report id and user as null.
            $this->_setProperty($state, '_deviceInfo', (object) [
                'id' => self::DEVICE,
                'user' => self::USER,
            ]);
            $this->_setProperty($state, '_type', Horde_ActiveSync::REQUEST_TYPE_SYNC);
            $this->_setProperty($state, '_syncKey', $syncKey);
            $this->_setProperty(
                $state,
                '_collection',
                $folderId === null
                    ? []
                    : ['id' => $folderId, 'class' => Horde_ActiveSync::CLASS_EMAIL]
            );

            return $state;
        }

        protected function _primeForSave(Horde_ActiveSync_State_Mongo $state)
        {
            $this->_setProperty(
                $state,
                '_folder',
                new Horde_ActiveSync_Folder_Imap('INBOX', Horde_ActiveSync::CLASS_EMAIL)
            );
            $this->_setProperty($state, '_thisSyncStamp', 2000);
            $this->_setProperty($state, '_changes', null);
        }

        protected function _loadState(Horde_ActiveSync_State_Mongo $state)
        {
            $ref = new ReflectionClass($state);
            $method = $ref->getMethod('_loadState');
            $method->setAccessible(true);
            $method->invoke($state);
        }

        protected function _insertDoc(InMemoryMongoCollection $collection, $key, $folderId, $data = null)
        {
            $collection->insert([
                Horde_ActiveSync_State_Mongo::MONGO_ID => $key,
                Horde_ActiveSync_State_Mongo::SYNC_KEY => $key,
                Horde_ActiveSync_State_Mongo::SYNC_DATA => $data ?? serialize(
                    new Horde_ActiveSync_Folder_Imap('INBOX', Horde_ActiveSync::CLASS_EMAIL)
                ),
                Horde_ActiveSync_State_Mongo::SYNC_DEVID => self::DEVICE,
                Horde_ActiveSync_State_Mongo::SYNC_MOD => 1000,
                Horde_ActiveSync_State_Mongo::SYNC_FOLDERID => $folderId,
                Horde_ActiveSync_State_Mongo::SYNC_USER => self::USER,
                Horde_ActiveSync_State_Mongo::SYNC_PENDING => '',
                Horde_ActiveSync_State_Mongo::SYNC_TIMESTAMP => 1,
            ]);
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
    }

}
