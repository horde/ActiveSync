<?php

/**
 * In-memory stand-in for a legacy MongoCollection, for state driver tests
 * that must run without a MongoDB server.
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
    if (!class_exists('MongoException', false)) {
        class MongoException extends Exception {}
    }
}

namespace Horde\ActiveSync\StateTest\Mongo {

    use LogicException;
    use MongoException;

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
                        throw new LogicException('Unsupported operator ' . $operator);
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

}
