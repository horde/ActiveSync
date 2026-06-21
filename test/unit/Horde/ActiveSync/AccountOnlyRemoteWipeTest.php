<?php

/**
 * Unit tests for per-user account-only remote wipe state handling.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @category  Horde
 * @package   ActiveSync
 */

namespace Horde\ActiveSync;

use PHPUnit\Framework\TestCase;
use Horde_ActiveSync;
use Horde_ActiveSync_Device;
use Horde_ActiveSync_Exception;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_State_Sql;
use Horde_Db_Adapter_Pdo_Sqlite;
use Horde_Db_Migration_Migrator;
use Horde_Log_Handler_Null;

class AccountOnlyRemoteWipeTest extends TestCase
{
    /** @var Horde_ActiveSync_State_Sql */
    protected $state;

    /** @var Horde_Db_Adapter_Pdo_Sqlite */
    protected $db;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension is not loaded');
        }

        $migrationDir = dirname(__DIR__, 4) . '/migration/Horde/ActiveSync';
        $this->db = new Horde_Db_Adapter_Pdo_Sqlite([
            'dbname' => ':memory:',
            'charset' => 'utf-8',
        ]);

        $migrator = new Horde_Db_Migration_Migrator(
            $this->db,
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null()),
            [
                'migrationsPath' => $migrationDir,
                'schemaTableName' => 'horde_activesync_schema_info',
            ]
        );
        $migrator->up();

        $this->state = new Horde_ActiveSync_State_Sql(['db' => $this->db]);
    }

    public function testLoadDeviceInfoWithoutUserDefaultsAccountOnlyStatusToNa()
    {
        $this->_saveDeviceUser('dev123', 'alice@example.com');

        $device = $this->state->loadDeviceInfo('dev123');

        $this->assertEquals(Horde_ActiveSync::RWSTATUS_NA, $device->accountOnlyRwstatus);
        $this->assertEquals(0, $device->policykey);
    }

    public function testGetAccountOnlyRWStatusReturnsNaWhenUnset()
    {
        $this->_saveDeviceUser('dev123', 'alice@example.com');
        $this->state->loadDeviceInfo('dev123');

        $this->assertEquals(
            Horde_ActiveSync::RWSTATUS_NA,
            $this->state->getAccountOnlyRWStatus('dev123')
        );
    }

    public function testSetAccountOnlyRWStatusPendingClearsOnlyTargetUserPolicyKey()
    {
        $this->_saveDeviceUser('dev123', 'alice@example.com', 100);
        $this->_saveDeviceUser('dev123', 'bob@example.com', 200);

        $this->state->setAccountOnlyRWStatus(
            'dev123',
            'alice@example.com',
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING
        );

        $alice = $this->state->loadDeviceInfo('dev123', 'alice@example.com');
        $this->assertEquals(
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING,
            $alice->accountOnlyRwstatus
        );
        $this->assertEquals(0, $alice->policykey);

        $bob = $this->state->loadDeviceInfo('dev123', 'bob@example.com');
        $this->assertEquals(Horde_ActiveSync::RWSTATUS_NA, $bob->accountOnlyRwstatus);
        $this->assertEquals(200, $bob->policykey);
    }

    public function testSetAccountOnlyRWStatusWipedPersistsPerUser()
    {
        $this->_saveDeviceUser('dev123', 'alice@example.com');
        $this->_saveDeviceUser('dev123', 'bob@example.com');

        $this->state->setAccountOnlyRWStatus(
            'dev123',
            'alice@example.com',
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_WIPED
        );

        $this->state->loadDeviceInfo('dev123', 'alice@example.com');
        $this->assertEquals(
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_WIPED,
            $this->state->getAccountOnlyRWStatus('dev123', true)
        );

        $bob = $this->state->loadDeviceInfo('dev123', 'bob@example.com');
        $this->assertEquals(Horde_ActiveSync::RWSTATUS_NA, $bob->accountOnlyRwstatus);
    }

    public function testSetAccountOnlyRWStatusUpdatesLoadedDeviceCache()
    {
        $this->_saveDeviceUser('dev123', 'alice@example.com');
        $this->state->loadDeviceInfo('dev123', 'alice@example.com');

        $this->state->setAccountOnlyRWStatus(
            'dev123',
            'alice@example.com',
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING
        );

        $this->assertEquals(
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING,
            $this->state->getAccountOnlyRWStatus('dev123')
        );
    }

    public function testListDevicesIncludesAccountOnlyRwstatus()
    {
        $this->_saveDeviceUser('dev123', 'alice@example.com');
        $this->state->setAccountOnlyRWStatus(
            'dev123',
            'alice@example.com',
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING
        );

        $devices = $this->state->listDevices();
        $this->assertCount(1, $devices);
        $this->assertEquals(
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING,
            $devices[0]['device_accountonly_rwstatus']
        );
    }

    public function testSetDeviceRWStatusRejectsAccountOnlyPending()
    {
        $this->expectException(Horde_ActiveSync_Exception::class);
        $this->expectExceptionMessage('setAccountOnlyRWStatus()');

        $this->state->setDeviceRWStatus(
            'dev123',
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING
        );
    }

    public function testSetDeviceRWStatusRejectsAccountOnlyWiped()
    {
        $this->expectException(Horde_ActiveSync_Exception::class);
        $this->expectExceptionMessage('setAccountOnlyRWStatus()');

        $this->state->setDeviceRWStatus(
            'dev123',
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_WIPED
        );
    }

    protected function _saveDeviceUser($devId, $user, $policykey = 456)
    {
        $device = new Horde_ActiveSync_Device($this->state);
        $device->rwstatus = Horde_ActiveSync::RWSTATUS_NA;
        $device->deviceType = 'Test Device';
        $device->userAgent = 'Horde Tests';
        $device->id = $devId;
        $device->user = $user;
        $device->policykey = $policykey;
        $device->supported = [];
        $device->save();
    }
}
