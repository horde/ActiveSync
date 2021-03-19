<?php
/**
 * @author Michael J Rubinsky <mrubinsk@horde.org>
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */
namespace Horde\ActiveSync\StateTest\Sql\Pdo;
use Horde_ActiveSync_StateTest_Sql_Base as Base;
use \Horde_Test_Factory_Db;

class SqliteTest extends Base
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
