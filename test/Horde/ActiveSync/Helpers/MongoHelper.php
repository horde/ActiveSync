<?php

/**
 * Test helper for creating MongoDB test instances.
 *
 * Replaces Horde_Test_Factory_Mongo functionality.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Test\Helpers;

use Horde_Mongo_Client;
use Exception;

class MongoHelper
{
    public const DEFAULT_DB = 'horde_mongo_testdb';

    /**
     * Create a connector to a temporary MongoDB instance.
     *
     * @param array $params Configuration:
     *   - config: (array) Configuration for Horde_Mongo_Client
     *   - dbname: (string) Database name to use
     *
     * @return Horde_Mongo_Client|null The DB object
     */
    public static function createMongoClient(array $params = []): ?Horde_Mongo_Client
    {
        if (!(extension_loaded('mongo') || extension_loaded('mongodb'))
            || !class_exists('Horde_Mongo_Client')
            || empty($params['config'])) {
            return null;
        }

        try {
            $mongo = new Horde_Mongo_Client($params['config']);
            $mongo->dbname = $params['dbname'] ?? self::DEFAULT_DB;

            // Test connection
            $mongo->selectDB($mongo->dbname);

            return $mongo;
        } catch (Exception $e) {
            return null;
        }
    }
}
