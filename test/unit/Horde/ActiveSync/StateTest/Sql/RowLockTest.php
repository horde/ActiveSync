<?php

/**
 * Unit tests for horde_activesync_state row reads and save transactions.
 *
 * No Horde_Db transaction may stay open between a mutating load and
 * save(): the request hands control to the backend in between, and a
 * failed backend statement would abort the shared transaction on
 * PostgreSQL (SQLSTATE 25P02).
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
use Horde_ActiveSync_Exception;
use Horde_ActiveSync_Exception_StateGone;
use Horde_ActiveSync_State_Sql;
use Horde_ActiveSync_Folder_Imap;
use Horde_ActiveSync_Log_Logger;
use Horde_Db_Exception;
use Horde_Log_Handler_Null;
use ReflectionClass;

class RowLockTest extends TestCase
{
    public function testReadStateRowSelectsForUpdateAndCommitsImmediately()
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
        $db->expects($this->once())->method('commitDbTransaction');
        $db->expects($this->never())->method('rollbackDbTransaction');

        $state = $this->_newState($db);
        $this->_setProperty($state, '_syncKey', '{test-lock}1');
        $this->_setProperty($state, '_collection', ['id' => 'Ftest']);

        $row = $this->_method($state, '_readStateRow')->invoke($state);

        $this->assertSame('device', $row['sync_devid']);
    }

    public function testReadStateRowJoinsOpenTransactionWithoutClosingIt()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->method('transactionStarted')->willReturn(true);
        $db->expects($this->never())->method('beginDbTransaction');
        $db->expects($this->never())->method('commitDbTransaction');
        $db->expects($this->never())->method('rollbackDbTransaction');
        $db->method('selectOne')->willReturn([
            'sync_data' => '',
            'sync_devid' => 'device',
            'sync_mod' => 1,
            'sync_pending' => '',
        ]);

        $state = $this->_newState($db);
        $this->_setProperty($state, '_syncKey', '{test-lock}1');
        $this->_setProperty($state, '_collection', ['id' => 'Ftest']);

        $this->_method($state, '_readStateRow')->invoke($state);
    }

    public function testReadStateRowRollsBackOnQueryFailure()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->method('transactionStarted')->willReturn(false);
        $db->expects($this->once())->method('beginDbTransaction');
        $db->method('selectOne')
            ->willThrowException(new Horde_Db_Exception('SQLSTATE[25P02]'));
        $db->expects($this->once())->method('rollbackDbTransaction');
        $db->expects($this->never())->method('commitDbTransaction');

        $state = $this->_newState($db);
        $this->_setProperty($state, '_syncKey', '{test-lock}1');
        $this->_setProperty($state, '_collection', ['id' => 'Ftest']);

        $this->expectException(Horde_ActiveSync_Exception::class);
        $this->_method($state, '_readStateRow')->invoke($state);
    }

    public function testReadStateRowCommitsBeforeReportingMissingState()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->method('transactionStarted')->willReturn(false);
        $db->expects($this->once())->method('beginDbTransaction');
        $db->method('selectOne')->willReturn(null);
        $db->expects($this->once())->method('commitDbTransaction');
        $db->expects($this->never())->method('rollbackDbTransaction');

        $state = $this->_newState($db);
        $this->_setProperty($state, '_syncKey', '{test-lock}1');
        $this->_setProperty($state, '_collection', ['id' => 'Ftest']);

        $this->expectException(Horde_ActiveSync_Exception_StateGone::class);
        $this->_method($state, '_readStateRow')->invoke($state);
    }

    public function testSaveRunsInItsOwnShortTransaction()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->method('transactionStarted')->willReturn(false);
        $db->expects($this->once())->method('beginDbTransaction');
        $db->expects($this->once())->method('updateBlob')->willReturn(true);
        $db->expects($this->once())->method('commitDbTransaction');
        $db->expects($this->never())->method('rollbackDbTransaction');

        $state = $this->_newState($db);
        $this->_primeForSave($state);

        $state->save();
    }

    public function testSaveJoinsCallerTransaction()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->method('transactionStarted')->willReturn(true);
        $db->expects($this->never())->method('beginDbTransaction');
        $db->expects($this->once())->method('updateBlob')->willReturn(true);
        $db->expects($this->never())->method('commitDbTransaction');
        $db->expects($this->never())->method('rollbackDbTransaction');

        $state = $this->_newState($db);
        $this->_primeForSave($state);

        $state->save();
    }

    public function testSaveRollsBackItsOwnTransactionOnFailure()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->method('transactionStarted')->willReturn(false);
        $db->expects($this->once())->method('beginDbTransaction');
        $db->method('updateBlob')
            ->willThrowException(new Horde_Db_Exception('SQLSTATE[25P02]'));
        $db->expects($this->once())->method('rollbackDbTransaction');
        $db->expects($this->never())->method('commitDbTransaction');

        $state = $this->_newState($db);
        $this->_primeForSave($state);

        $this->expectException(Horde_ActiveSync_Exception::class);
        $state->save();
    }

    public function testUpdateSyncStampBelowThresholdTouchesNoTransaction()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->expects($this->never())->method('update');
        $db->expects($this->never())->method('beginDbTransaction');
        $db->expects($this->never())->method('commitDbTransaction');
        $db->expects($this->never())->method('rollbackDbTransaction');

        $state = $this->_newState($db);
        $this->_setProperty($state, '_syncKey', '{test-lock}3');
        $this->_setProperty($state, '_collection', ['id' => 'Ftest']);
        $this->_setProperty($state, '_lastSyncStamp', 10);
        $this->_setProperty($state, '_thisSyncStamp', 11);

        $state->updateSyncStamp();
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
    }

    protected function _setProperty($object, $name, $value)
    {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    protected function _method($object, $name)
    {
        $ref = new ReflectionClass($object);
        $method = $ref->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}
