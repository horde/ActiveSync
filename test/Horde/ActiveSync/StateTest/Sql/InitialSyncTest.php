<?php

/**
 * Unit tests for CONDSTORE initial sync state handling.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */

namespace Horde\ActiveSync\StateTest\Sql;

use Horde_Test_Case as TestCase;
use Horde_ActiveSync;
use Horde_ActiveSync_Folder_Imap;
use Horde_ActiveSync_State_Sql;
use Horde_Db_Value_Binary;

/**
 * @coversNothing
 */
class InitialSyncTest extends TestCase
{
    public function testUpdateStateAcknowledgesExportedMessage()
    {
        $folder = new Horde_ActiveSync_Folder_Imap('INBOX/Horde', Horde_ActiveSync::CLASS_EMAIL);
        $folder->primeFolder(range(1, 10));
        $folder->setStatus([
            Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 100,
            Horde_ActiveSync_Folder_Imap::UIDNEXT => 11,
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 200,
        ]);
        $folder->updateState();

        $state = $this->_createState($folder, [
            ['id' => 506, 'type' => Horde_ActiveSync::CHANGE_TYPE_CHANGE],
            ['id' => 507, 'type' => Horde_ActiveSync::CHANGE_TYPE_CHANGE],
        ]);

        $state->updateState(
            Horde_ActiveSync::CHANGE_TYPE_CHANGE,
            ['id' => 506, 'type' => Horde_ActiveSync::CHANGE_TYPE_CHANGE]
        );

        $this->assertEquals([506], $folder->messages());
        $this->assertCount(1, $this->_getChanges($state));
    }

    /**
     * Initial sync stores bare UIDs in sync_pending (driver short-circuit).
     * The exporter normalizes to CHANGE_TYPE_CHANGE before updateState().
     */
    public function testInitialSyncBareUidAcknowledgesExportedMessage()
    {
        $folder = new Horde_ActiveSync_Folder_Imap('INBOX/Horde', Horde_ActiveSync::CLASS_EMAIL);
        $folder->primeFolder(range(1, 10));
        $folder->setStatus([
            Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 100,
            Horde_ActiveSync_Folder_Imap::UIDNEXT => 11,
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 200,
        ]);
        $folder->updateState();

        $state = $this->_createState($folder, [506, 507]);

        $state->updateState(
            Horde_ActiveSync::CHANGE_TYPE_CHANGE,
            [
                'id' => 506,
                'type' => Horde_ActiveSync::CHANGE_TYPE_CHANGE,
                'flags' => Horde_ActiveSync::FLAG_NEWMESSAGE,
            ]
        );

        $this->assertEquals([506], $folder->messages());
        $this->assertEquals([507], array_values($this->_getChanges($state)));
    }

    public function testSaveMarksInitialSyncCompleteWhenPendingEmpty()
    {
        $folder = new Horde_ActiveSync_Folder_Imap('INBOX/Horde', Horde_ActiveSync::CLASS_EMAIL);
        $folder->primeFolder(range(1, 3));
        $folder->setStatus([
            Horde_ActiveSync_Folder_Imap::UIDVALIDITY => 100,
            Horde_ActiveSync_Folder_Imap::UIDNEXT => 4,
            Horde_ActiveSync_Folder_Imap::HIGHESTMODSEQ => 200,
        ]);
        $folder->updateState();
        $folder->acknowledgeExportedMessage(1);
        $folder->acknowledgeExportedMessage(2);
        $folder->acknowledgeExportedMessage(3);

        $db = $this->getMockBuilder('Horde_Db_Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $db->method('transactionStarted')->willReturn(false);
        $db->expects($this->once())->method('beginDbTransaction');
        $db->expects($this->once())
            ->method('updateBlob')
            ->with(
                $this->equalTo('horde_activesync_state'),
                $this->callback(function ($params) {
                    $data = $params['sync_data'];
                    if ($data instanceof Horde_Db_Value_Binary) {
                        $folder = unserialize($data->value);
                    } else {
                        $folder = unserialize($data);
                    }
                    return $folder instanceof Horde_ActiveSync_Folder_Imap
                        && $folder->haveInitialSync;
                }),
                $this->anything()
            )
            ->willReturn(true);
        $db->expects($this->once())->method('commitDbTransaction');

        $state = $this->_createState($folder, null, $db);
        $state->save();
    }

    protected function _createState(
        Horde_ActiveSync_Folder_Imap $folder,
        array $changes = null,
        $db = null
    ) {
        if ($db === null) {
            $db = $this->getMockBuilder('Horde_Db_Adapter')
                ->disableOriginalConstructor()
                ->getMock();
        }

        $device = $this->getMockBuilder('Horde_ActiveSync_Device')
            ->disableOriginalConstructor()
            ->getMock();
        $device->id = 'device';
        $device->user = 'user@example.com';

        $state = new Horde_ActiveSync_State_Sql(['db' => $db]);
        $ref = new \ReflectionClass($state);
        foreach ([
            '_type' => Horde_ActiveSync::REQUEST_TYPE_SYNC,
            '_folder' => $folder,
            '_syncKey' => '{test}1',
            '_deviceInfo' => $device,
            '_collection' => [
                'id' => 'F92ed1990',
                'class' => Horde_ActiveSync::CLASS_EMAIL,
                'serverid' => 'INBOX/Horde',
            ],
            '_thisSyncStamp' => 100,
            '_changes' => $changes,
            '_syncPendingBlob' => null,
        ] as $prop => $value) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue($state, $value);
        }

        $logger = $ref->getProperty('_logger');
        $logger->setAccessible(true);
        $logger->setValue(
            $state,
            new \Horde_ActiveSync_Log_Logger(new \Horde_Log_Handler_Null())
        );

        return $state;
    }

    protected function _getChanges(Horde_ActiveSync_State_Sql $state)
    {
        $ref = new \ReflectionClass($state);
        $p = $ref->getProperty('_changes');
        $p->setAccessible(true);

        return $p->getValue($state);
    }
}
