<?php

/**
 * @author Michael J Rubinsky <mrubinsk@horde.org>
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */

namespace Horde\ActiveSync\StateTest\Sql\Pdo;

use PHPUnit\Framework\Attributes\CoversNothing;
use Horde\ActiveSync\StateTest\Sql\TestBase;
use Horde_Db_Adapter_Pdo_Sqlite;

#[CoversNothing]
class SqliteTest extends TestBase
{
    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::$reason = 'Sqlite not available';
            return;
        }
        self::$db = new Horde_Db_Adapter_Pdo_Sqlite([
            'dbname' => ':memory:',
            'charset' => 'utf-8',
        ]);
        parent::setUpBeforeClass();
    }
}
