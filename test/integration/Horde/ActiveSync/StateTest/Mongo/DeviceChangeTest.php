<?php

/**
 * Unit tests for sync state ownership after a device change (Mongo driver).
 *
 * When an account is moved to a new phone, the client keeps its sync keys
 * but reports a new DeviceId. HAS_state uses the sync key as document _id,
 * so two device ids advancing the same key series cannot both store their
 * state. These tests cover the guards that keep the state of two devices
 * apart (@see \Horde_ActiveSync_State_Mongo::_isOwnStateRow()).
 *
 * No MongoDB server is involved: the driver runs against an in-memory
 * stand-in for the legacy MongoCollection API (InMemoryMongoCollection).
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
    if (!class_exists('MongoException', false)) {
        class MongoException extends Exception {}
    }
}

namespace Horde\ActiveSync\StateTest\Mongo {

    use MongoException;
    use PHPUnit\Framework\TestCase;
    use Horde_ActiveSync;
    use Horde_ActiveSync_Exception;
    use Horde_ActiveSync_Exception_StateGone;
    use Horde_ActiveSync_Folder_Imap;
    use Horde_ActiveSync_Log_Logger;
    use Horde_ActiveSync_State_Mongo;
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
         * old device. It must be told the state is gone instead of locking
         * and adopting the old device's document.
         */
        public function testChangedDeviceCannotLoadSyncKeyOfOtherDevice()
        {
            $collection = new InMemoryMongoCollection();
            $this->_insertDoc($collection, 23, self::OLD_DEVICE);

            $state = $this->_newState($collection, self::NEW_DEVICE, 23);
            try {
                $this->_loadState($state);
                $this->fail('The sync key of another device must not be loadable.');
            } catch (Horde_ActiveSync_Exception_StateGone $e) {
            }

            $this->assertFalse($this->_getProperty($state, '_stateRowLockHeld'));
            $this->assertArrayNotHasKey(
                Horde_ActiveSync_State_Mongo::SYNC_LOCK,
                $collection->docs[self::SERIES . '23'],
                'The document of the other device must not be locked.'
            );
        }

        /**
         * The device that owns the key still loads it.
         */
        public function testOwnSyncKeyIsLoaded()
        {
            $collection = new InMemoryMongoCollection();
            $this->_insertDoc($collection, 23, self::OLD_DEVICE);

            $state = $this->_newState($collection, self::OLD_DEVICE, 23);
            $this->_loadState($state);

            $this->assertTrue($this->_getProperty($state, '_stateRowLockHeld'));
            $this->assertSame(1000, $this->_getProperty($state, '_lastSyncStamp'));
        }

        /**
         * Read-only loads (PING) are exempt from guard 1: peeking at the
         * document the key was copied from keeps PING working until the
         * client's next SYNC is reset.
         */
        public function testReadonlyLoadMayPeekAtSyncKeyOfOtherDevice()
        {
            $collection = new InMemoryMongoCollection();
            $this->_insertDoc($collection, 23, self::OLD_DEVICE);

            $state = $this->_newState($collection, self::NEW_DEVICE, 23);
            $this->_setProperty($state, '_loadReadonly', true);
            $this->_loadState($state);

            $this->assertSame(1000, $this->_getProperty($state, '_lastSyncStamp'));
        }

        /**
         * Guard 2: the old device comes back with its last key, but the new
         * device has already stored the next key of that series. The old
         * device could never save its next state, so it is reset before the
         * document lock is taken.
         */
        public function testDeviceIsResetWhenAnotherDeviceContinuedItsKeySeries()
        {
            $collection = new InMemoryMongoCollection();
            $this->_insertDoc($collection, 23, self::OLD_DEVICE);
            $this->_insertDoc($collection, 24, self::NEW_DEVICE);

            $state = $this->_newState($collection, self::OLD_DEVICE, 23);
            try {
                $this->_loadState($state);
                $this->fail('A key series continued by another device must reset the device.');
            } catch (Horde_ActiveSync_Exception_StateGone $e) {
            }

            $this->assertFalse($this->_getProperty($state, '_stateRowLockHeld'));
            $this->assertArrayNotHasKey(
                Horde_ActiveSync_State_Mongo::SYNC_LOCK,
                $collection->docs[self::SERIES . '23'],
                'No document lock may be left behind by the rejected load.'
            );
        }

        /**
         * A next key stored by the same device (the client repeats a request
         * whose response it never received) must not reset the device.
         */
        public function testOwnNextKeyDoesNotResetDevice()
        {
            $collection = new InMemoryMongoCollection();
            $this->_insertDoc($collection, 23, self::OLD_DEVICE);
            $this->_insertDoc($collection, 24, self::OLD_DEVICE);

            $state = $this->_newState($collection, self::OLD_DEVICE, 23);
            $this->_loadState($state);

            $this->assertTrue($this->_getProperty($state, '_stateRowLockHeld'));
        }

        /**
         * Guard 3: saving never removes or replaces a document that belongs
         * to another device.
         */
        public function testSaveDoesNotOverwriteStateOfOtherDevice()
        {
            $collection = new InMemoryMongoCollection();
            $this->_insertDoc($collection, 24, self::NEW_DEVICE);
            $before = $collection->docs[self::SERIES . '24'];

            $state = $this->_newState($collection, self::OLD_DEVICE, 24);
            $this->_primeForSave($state);
            try {
                $state->save();
                $this->fail('Saving over the state of another device must fail.');
            } catch (Horde_ActiveSync_Exception $e) {
            }

            $this->assertSame($before, $collection->docs[self::SERIES . '24']);
        }

        /**
         * The previous response was not accepted by the client and the same
         * device stores the key again: its own document is replaced.
         */
        public function testSaveReplacesOwnExistingState()
        {
            $collection = new InMemoryMongoCollection();
            $this->_insertDoc($collection, 24, self::OLD_DEVICE);

            $state = $this->_newState($collection, self::OLD_DEVICE, 24);
            $this->_primeForSave($state);
            $state->save();

            $doc = $collection->docs[self::SERIES . '24'];
            $this->assertSame(self::OLD_DEVICE, $doc[Horde_ActiveSync_State_Mongo::SYNC_DEVID]);
            $this->assertSame(2000, $doc[Horde_ActiveSync_State_Mongo::SYNC_MOD]);
        }

        /**
         * An insert that fails although no document exists for the key is a
         * storage error and is reported as such.
         */
        public function testSaveReportsInsertFailureWithoutExistingState()
        {
            $collection = new InMemoryMongoCollection();
            $collection->failInsert = true;

            $state = $this->_newState($collection, self::OLD_DEVICE, 24);
            $this->_primeForSave($state);

            $this->expectException(Horde_ActiveSync_Exception::class);
            try {
                $state->save();
            } finally {
                $this->assertSame([], $collection->docs);
            }
        }

        protected function _newState(InMemoryMongoCollection $collection, $deviceId, $counter)
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
                'id' => $deviceId,
                'user' => self::USER,
            ]);
            $this->_setProperty($state, '_type', Horde_ActiveSync::REQUEST_TYPE_SYNC);
            $this->_setProperty($state, '_syncKey', self::SERIES . $counter);
            $this->_setProperty($state, '_collection', [
                'id' => self::FOLDER_ID,
                'class' => Horde_ActiveSync::CLASS_EMAIL,
            ]);

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

        protected function _insertDoc(InMemoryMongoCollection $collection, $counter, $deviceId)
        {
            $key = self::SERIES . $counter;
            $collection->insert([
                Horde_ActiveSync_State_Mongo::MONGO_ID => $key,
                Horde_ActiveSync_State_Mongo::SYNC_KEY => $key,
                Horde_ActiveSync_State_Mongo::SYNC_DATA => serialize(
                    new Horde_ActiveSync_Folder_Imap('INBOX', Horde_ActiveSync::CLASS_EMAIL)
                ),
                Horde_ActiveSync_State_Mongo::SYNC_DEVID => $deviceId,
                Horde_ActiveSync_State_Mongo::SYNC_MOD => 1000,
                Horde_ActiveSync_State_Mongo::SYNC_FOLDERID => self::FOLDER_ID,
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

    /**
     * In-memory stand-in for the parts of the legacy MongoCollection API the
     * state driver uses on HAS_state. Documents are keyed by _id, and a
     * second insert for the same _id fails like MongoDB's unique _id index.
     *
     * Supported query syntax: field equality, $or, $exists and $lt.
     */
    class InMemoryMongoCollection
    {
        /**
         * Stored documents, keyed by _id.
         *
         * @var array
         */
        public $docs = [];

        /**
         * Make every insert fail, whether or not the _id exists.
         *
         * @var boolean
         */
        public $failInsert = false;

        public function count($query = [])
        {
            return count($this->_find($query));
        }

        public function findOne($query = [], $fields = [])
        {
            $ids = $this->_find($query);

            return $ids ? $this->_project($this->docs[$ids[0]], $fields) : null;
        }

        public function findAndModify($query, $update = [], $fields = [], $options = [])
        {
            $ids = $this->_find($query);
            if (!$ids) {
                return null;
            }
            $before = $this->docs[$ids[0]];
            $this->_apply($ids[0], $update);

            return $this->_project($before, $fields);
        }

        public function update($query, $update, $options = [])
        {
            $ids = $this->_find($query);
            if ($ids) {
                $this->_apply($ids[0], $update);
            }

            return ['ok' => 1, 'n' => $ids ? 1 : 0];
        }

        public function insert($document)
        {
            $id = $document['_id'];
            if ($this->failInsert) {
                throw new MongoException('insert failed');
            }
            if (isset($this->docs[$id])) {
                throw new MongoException('E11000 duplicate key error', 11000);
            }
            $this->docs[$id] = $document;

            return ['ok' => 1];
        }

        public function remove($query = [])
        {
            $ids = $this->_find($query);
            foreach ($ids as $id) {
                unset($this->docs[$id]);
            }

            return ['ok' => 1, 'n' => count($ids)];
        }

        private function _find(array $query)
        {
            $ids = [];
            foreach ($this->docs as $id => $doc) {
                if ($this->_matches($doc, $query)) {
                    $ids[] = $id;
                }
            }

            return $ids;
        }

        private function _matches(array $doc, array $query)
        {
            foreach ($query as $field => $condition) {
                if ($field === '$or') {
                    $any = false;
                    foreach ($condition as $alternative) {
                        $any = $any || $this->_matches($doc, $alternative);
                    }
                    if (!$any) {
                        return false;
                    }
                    continue;
                }

                $exists = array_key_exists($field, $doc);
                if (!is_array($condition)) {
                    // Like MongoDB, null also matches a missing field.
                    if (($exists ? $doc[$field] : null) !== $condition) {
                        return false;
                    }
                    continue;
                }
                foreach ($condition as $operator => $operand) {
                    if ($operator === '$exists') {
                        $ok = ($exists === (bool) $operand);
                    } elseif ($operator === '$lt') {
                        $ok = $exists && $doc[$field] < $operand;
                    } else {
                        throw new \LogicException('Unsupported operator ' . $operator);
                    }
                    if (!$ok) {
                        return false;
                    }
                }
            }

            return true;
        }

        private function _apply($id, array $update)
        {
            foreach ($update['$set'] ?? [] as $field => $value) {
                $this->docs[$id][$field] = $value;
            }
            foreach (array_keys($update['$unset'] ?? []) as $field) {
                unset($this->docs[$id][$field]);
            }
        }

        private function _project(array $doc, array $fields)
        {
            if (!$fields) {
                return $doc;
            }

            return array_intersect_key($doc, $fields + ['_id' => true]);
        }
    }

    class InMemoryMongoDb
    {
        private $_collection;

        public function __construct(InMemoryMongoCollection $collection)
        {
            $this->_collection = $collection;
        }

        public function selectCollection($name)
        {
            return $this->_collection;
        }
    }

}
