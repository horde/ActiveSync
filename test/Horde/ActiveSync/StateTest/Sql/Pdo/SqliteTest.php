<?php
/**
 * @author Michael J Rubinsky <mrubinsk@horde.org>
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */
namespace Horde\ActiveSync\StateTest\Sql\Pdo;
use Horde\ActiveSync\StateTest\Sql\TestBase;
use \Horde_Test_Factory_Db;

class SqliteTest extends TestBase
{
    public static function setUpBeforeClass(): void
    {
        $factory_db = new Horde_Test_Factory_Db();
        
        if (class_exists('Horde_Db_Adapter_Pdo_Sqlite')) {
            self::$db = $factory_db->create();
            parent::setUpBeforeClass();
        } else {
            self::$reason = 'Sqlite not available';
        }
    }

}
