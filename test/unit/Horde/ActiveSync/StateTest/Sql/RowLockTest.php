<?php

/**
 * Unit tests for row-level locking on horde_activesync_state loads.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */

namespace Horde\ActiveSync\StateTest\Sql;

use PHPUnit\Framework\TestCase;
use Horde_ActiveSync;
use Horde_ActiveSync_State_Sql;
use Horde_ActiveSync_Folder_Imap;
use Horde_ActiveSync_Log_Logger;
use Horde_Log_Handler_Null;
use ReflectionClass;

class RowLockTest extends TestCase
{
    public function testAcquireStateRowLockUsesForUpdate()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->method('transactionStarted')->willReturn(false);
        $db->expects($this->once())->method('beginDbTransaction');
        $db->expects($this->once())->method('addLock');
        $db->expects($this->once())
            ->method('selectOne')
            ->willReturn([
                'sync_data' => '',
                'sync_devid' => 'device',
                'sync_mod' => 1,
                'sync_pending' => '',
            ]);

        $state = $this->_newState($db);
        $this->_setProperty($state, '_syncKey', '{test-lock}1');
        $this->_setProperty($state, '_collection', ['id' => 'Ftest']);

        $acquire = $this->_method($state, '_acquireStateRowLock');
        $acquire->invoke($state);

        $this->assertTrue($this->_getProperty($state, '_stateRowLockHeld'));
        $this->assertTrue($this->_getProperty($state, '_stateRowLockTxnOwner'));
    }

    public function testSaveCommitsRowLockTransaction()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->method('transactionStarted')->willReturn(true);
        $db->expects($this->once())->method('updateBlob')->willReturn(true);
        $db->expects($this->once())->method('commitDbTransaction');
        $db->expects($this->never())->method('rollbackDbTransaction');

        $state = $this->_newState($db);
        $this->_primeForSave($state);

        $state->save();
        $this->assertFalse($this->_getProperty($state, '_stateRowLockHeld'));
    }

    public function testUpdateSyncStampRollsBackUnusedLock()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->expects($this->never())->method('update');
        $db->expects($this->once())->method('rollbackDbTransaction');

        $state = $this->_newState($db);
        $this->_setProperty($state, '_syncKey', '{test-lock}3');
        $this->_setProperty($state, '_collection', ['id' => 'Ftest']);
        $this->_setProperty($state, '_lastSyncStamp', 10);
        $this->_setProperty($state, '_thisSyncStamp', 11);
        $this->_setProperty($state, '_stateRowLockHeld', true);
        $this->_setProperty($state, '_stateRowLockTxnOwner', true);

        $state->updateSyncStamp();
        $this->assertFalse($this->_getProperty($state, '_stateRowLockHeld'));
    }

    protected function _newState($db)
    {
        $state = new Horde_ActiveSync_State_Sql(['db' => $db]);

        $device = $this->getMockBuilder('Horde_ActiveSync_Device')
            ->disableOriginalConstructor()
            ->getMock();
        $device->id = 'device';
        $device->user = 'user@example.com';

        $this->_setProperty(
            $state,
            '_logger',
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );
        $this->_setProperty($state, '_deviceInfo', $device);

        return $state;
    }

    protected function _primeForSave(Horde_ActiveSync_State_Sql $state)
    {
        $folder = new Horde_ActiveSync_Folder_Imap('INBOX', Horde_ActiveSync::CLASS_EMAIL);
        $this->_setProperty($state, '_type', Horde_ActiveSync::REQUEST_TYPE_SYNC);
        $this->_setProperty($state, '_folder', $folder);
        $this->_setProperty($state, '_syncKey', '{test-lock}2');
        $this->_setProperty($state, '_collection', [
            'id' => 'Ftest',
            'class' => Horde_ActiveSync::CLASS_EMAIL,
        ]);
        $this->_setProperty($state, '_thisSyncStamp', 100);
        $this->_setProperty($state, '_changes', null);
        $this->_setProperty($state, '_stateRowLockHeld', true);
        $this->_setProperty($state, '_stateRowLockTxnOwner', true);
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

    protected function _method($object, $name)
    {
        $ref = new ReflectionClass($object);
        $method = $ref->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}
