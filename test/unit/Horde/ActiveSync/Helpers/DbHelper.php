<?php

/**
 * Test helper for creating test databases.
 *
 * Replaces Horde_Test_Factory_Db functionality.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Test\Helpers;

use Horde_Db_Adapter_Pdo_Sqlite;
use Horde_Db_Migration_Migrator;
use Exception;

class DbHelper
{
    /**
     * Create an in-memory SQLite database for testing.
     *
     * @param array $params Configuration parameters:
     *   - migrations: Array with 'migrationsPath' and 'schemaTableName' keys
     *
     * @return Horde_Db_Adapter_Pdo_Sqlite
     */
    public static function createSqliteDb(array $params = []): Horde_Db_Adapter_Pdo_Sqlite
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new Exception('PDO SQLite extension is not loaded');
        }

        $db = new Horde_Db_Adapter_Pdo_Sqlite([
            'dbname' => ':memory:',
            'charset' => 'utf-8',
        ]);

        // Run migrations if specified
        if (!empty($params['migrations'])) {
            $logger = LogHelper::createMockLogger();

            foreach ($params['migrations'] as $migration) {
                $migrator = new Horde_Db_Migration_Migrator(
                    $db,
                    $logger,
                    [
                        'migrationsPath' => $migration['migrationsPath'],
                        'schemaTableName' => $migration['schemaTableName'],
                    ]
                );
                $migrator->up();
            }
        }

        return $db;
    }
}
