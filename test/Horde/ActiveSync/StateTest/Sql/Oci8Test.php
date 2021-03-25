<?php
/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/gpl GPLv2
 * @category   Horde
 * @package    Horde_ActiveSync
 * @subpackage UnitTests
 */
namespace Horde\ActiveSync\StateTest\Sql;
use Horde\ActiveSync\StateTest\Sql\TestBase;

class Oci8Test extends TestBase
{
    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('oci8')) {
            self::$reason = 'No oci8 extension.';
            return;
        }
        $config = self::getConfig('ACTIVESYNC_SQL_OCI8_TEST_CONFIG', dirname(__FILE__) . '/../..');
        if ($config && !empty($config['activesync']['sql']['oci8'])) {
            self::$db = new Horde_Db_Adapter_Oci8($config['activesync']['sql']['oci8']);
            parent::setUpBeforeClass();
        } else {
            self::$reason = 'No oci8 configuration';
        }
    }

}