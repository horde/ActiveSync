<?php

/**
 * Unit tests for mailmap idempotency helpers (integer UIDs + txn rollback).
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
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_State_Sql;
use Horde_Db_Exception;
use Horde_Log_Handler_Null;
use ReflectionClass;

class MailMapChangeAppliedTest extends TestCase
{
    public function testNonIntegerUidSkipsMailmapQuery(): void
    {
        $db = $this->createMock(\Horde_Db_Adapter::class);
        $db->expects($this->never())->method('selectValue');

        $state = $this->_newState($db);
        $this->assertFalse(
            $state->isMailMapChangeApplied(
                '6a5a2daf-881c-4a66-b8cc-574e00000000',
                Horde_ActiveSync::CHANGE_TYPE_DELETE
            )
        );
    }

    public function testIntegerUidQueriesMailmap(): void
    {
        $db = $this->createMock(\Horde_Db_Adapter::class);
        $db->expects($this->once())
            ->method('selectValue')
            ->willReturn(1);

        $state = $this->_newState($db);
        $this->assertTrue(
            $state->isMailMapChangeApplied(
                '100',
                Horde_ActiveSync::CHANGE_TYPE_DELETE
            )
        );
    }

    public function testDbFailureRollsBackOpenTransaction(): void
    {
        $db = $this->createMock(\Horde_Db_Adapter::class);
        $db->method('selectValue')
            ->willThrowException(new Horde_Db_Exception('SQLSTATE[22P02]'));
        $db->method('transactionStarted')->willReturn(true);
        $db->expects($this->once())->method('rollbackDbTransaction');

        $state = $this->_newState($db);

        $this->expectException(Horde_ActiveSync_Exception::class);
        $state->isMailMapChangeApplied(
            100,
            Horde_ActiveSync::CHANGE_TYPE_DELETE
        );
    }

    protected function _newState($db): Horde_ActiveSync_State_Sql
    {
        $state = new Horde_ActiveSync_State_Sql(['db' => $db]);
        $ref = new ReflectionClass($state);

        $logger = $ref->getProperty('_logger');
        $logger->setAccessible(true);
        $logger->setValue(
            $state,
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );

        $device = $ref->getProperty('_deviceInfo');
        $device->setAccessible(true);
        $device->setValue($state, (object) [
            'id' => 'device',
            'user' => 'alice@example.com',
        ]);

        $collection = $ref->getProperty('_collection');
        $collection->setAccessible(true);
        $collection->setValue($state, [
            'id' => 'Fmail',
            'serverid' => 'INBOX',
            'class' => Horde_ActiveSync::CLASS_EMAIL,
        ]);

        return $state;
    }
}
