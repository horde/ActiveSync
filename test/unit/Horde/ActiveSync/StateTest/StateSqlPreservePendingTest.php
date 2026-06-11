<?php

/**
 * Unit tests for PING checkpoint saves preserving sync_pending.
 *
 * @category Horde
 * @package ActiveSync
 */

namespace Horde\ActiveSync;

use PHPUnit\Framework\TestCase;
use Horde_ActiveSync;
use Horde_ActiveSync_Folder_Imap;
use Horde_ActiveSync_State_Sql;
use Horde_Db_Value_Binary;
use Horde_ActiveSync_Log_Logger;
use Horde_Log_Handler_Null;
use ReflectionClass;

class StateSqlPreservePendingTest extends TestCase
{
    public function testPreservePendingKeepsSyncPendingBlob()
    {
        $pendingBlob = serialize([['id' => 100, 'type' => 1]]);
        $folder = new Horde_ActiveSync_Folder_Imap('INBOX/BigFamily', Horde_ActiveSync::CLASS_EMAIL);
        $folder->setStatus([
            Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 100,
            Horde_ActiveSync_Folder_Imap::UIDNEXT => 200,
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 5864,
            Horde_ActiveSync_Folder_Imap::MESSAGES => 50,
        ]);
        $folder->updateState();
        $folder->acknowledgePingStatus([
            Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 100,
            Horde_ActiveSync_Folder_Imap::UIDNEXT => 200,
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 6000,
            Horde_ActiveSync_Folder_Imap::MESSAGES => 50,
        ]);

        $db = $this->_mockDbForSave(function ($params) use ($pendingBlob) {
            return $params['sync_pending'] === $pendingBlob
                && $params['sync_data'] instanceof Horde_Db_Value_Binary;
        });

        $state = new Horde_ActiveSync_State_Sql(['db' => $db]);
        $this->_primeSyncState($state, $folder, $pendingBlob);

        $state->save(['preservePending' => true]);
    }

    public function testLoadRejectsCorruptEmptyArraySyncData()
    {
        $state = $this->_stateForNormalizeTest();

        $normalize = $this->_method($state, '_normalizeSyncFolderData');
        $this->expectException('Horde_ActiveSync_Exception_StaleState');
        $normalize->invoke($state, [], 'a:0:{}');
    }

    public function testLoadRejectsSyncPendingShapedSyncData()
    {
        $state = $this->_stateForNormalizeTest();
        $pending = serialize([['id' => 100, 'type' => 1]]);

        $normalize = $this->_method($state, '_normalizeSyncFolderData');
        $this->expectException('Horde_ActiveSync_Exception_StaleState');
        $normalize->invoke($state, unserialize($pending), $pending);
    }

    public function testLoadAllowsMissingSyncDataBlob()
    {
        $state = $this->_stateForNormalizeTest();

        $normalize = $this->_method($state, '_normalizeSyncFolderData');
        $this->assertFalse($normalize->invoke($state, false, ''));
    }

    public function testSaveRejectsInvalidEmailFolderState()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->expects($this->never())->method('updateBlob');

        $state = new Horde_ActiveSync_State_Sql(['db' => $db]);
        $this->_primeSyncState(
            $state,
            new Horde_ActiveSync_Folder_Imap('INBOX', Horde_ActiveSync::CLASS_EMAIL),
            ''
        );

        $ref = new ReflectionClass($state);
        $folder = $ref->getProperty('_folder');
        $folder->setAccessible(true);
        $folder->setValue($state, []);

        $this->expectException('Horde_ActiveSync_Exception_StaleState');
        $state->save();
    }

    public function testSaveWithoutPreservePendingClearsSyncPending()
    {
        $folder = new Horde_ActiveSync_Folder_Imap('INBOX', Horde_ActiveSync::CLASS_EMAIL);

        $db = $this->_mockDbForSave(function ($params) {
            return $params['sync_pending'] === ''
                && $params['sync_data'] instanceof Horde_Db_Value_Binary;
        });

        $state = new Horde_ActiveSync_State_Sql(['db' => $db]);
        $this->_primeSyncState($state, $folder, serialize([['id' => 1, 'type' => 1]]));

        $state->save();
    }

    protected function _stateForNormalizeTest()
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();

        $state = new Horde_ActiveSync_State_Sql(['db' => $db]);
        $ref = new ReflectionClass($state);
        foreach ([
            '_type' => Horde_ActiveSync::REQUEST_TYPE_SYNC,
            '_syncKey' => '{test}99',
            '_collection' => [
                'id' => 'Fea62ac31',
                'class' => Horde_ActiveSync::CLASS_EMAIL,
                'serverid' => 'INBOX',
            ],
        ] as $prop => $value) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue($state, $value);
        }

        $logger = $ref->getProperty('_logger');
        $logger->setAccessible(true);
        $logger->setValue(
            $state,
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );

        return $state;
    }

    protected function _method($state, $name)
    {
        $ref = new ReflectionClass($state);
        $method = $ref->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    protected function _mockDbForSave(callable $pendingCheck)
    {
        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->method('transactionStarted')->willReturn(false);
        $db->expects($this->once())->method('beginDbTransaction');
        $db->expects($this->once())
            ->method('updateBlob')
            ->with(
                $this->equalTo('horde_activesync_state'),
                $this->callback($pendingCheck),
                $this->anything()
            )
            ->willReturn(true);
        $db->expects($this->once())->method('commitDbTransaction');

        return $db;
    }

    protected function _primeSyncState(
        Horde_ActiveSync_State_Sql $state,
        Horde_ActiveSync_Folder_Imap $folder,
        $pendingBlob
    ) {
        $device = $this->getMockBuilder('Horde_ActiveSync_Device')
            ->disableOriginalConstructor()
            ->getMock();
        $device->id = 'device';
        $device->user = 'user@example.com';

        $ref = new ReflectionClass($state);
        foreach ([
            '_type' => Horde_ActiveSync::REQUEST_TYPE_SYNC,
            '_folder' => $folder,
            '_syncKey' => '{test}1',
            '_deviceInfo' => $device,
            '_collection' => ['id' => 'Fea62ac31', 'class' => Horde_ActiveSync::CLASS_EMAIL],
            '_thisSyncStamp' => 100,
            '_changes' => null,
            '_syncPendingBlob' => $pendingBlob,
        ] as $prop => $value) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue($state, $value);
        }

        $logger = $ref->getProperty('_logger');
        $logger->setAccessible(true);
        $logger->setValue(
            $state,
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );
    }
}
