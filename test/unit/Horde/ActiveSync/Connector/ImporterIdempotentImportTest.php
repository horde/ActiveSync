<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @package ActiveSync
 * @subpackage UnitTests
 */

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for idempotent email Sync import (Issue #85).
 */
class Horde_ActiveSync_Connector_ImporterIdempotentImportTest extends TestCase
{
    public function testDraftModifyRetryDoesNotCallChangeMessageAgain(): void
    {
        $synckey = '{uuid}5';
        $oldUid = '100';
        $newUid = '101';
        $message = $this->_draftMailMessage('Draft body', 'Meeting notes');

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->expects($this->once())
            ->method('getAppliedPIMChange')
            ->with($oldUid, $synckey)
            ->willReturn(['id' => $newUid]);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->expects($this->never())->method('changeMessage');

        $importer = $this->_importer($state, $driver);
        $stat = $importer->importMessageChange(
            $oldUid,
            $message,
            $this->createMock(Horde_ActiveSync_Device::class),
            false,
            Horde_ActiveSync::CLASS_EMAIL,
            $synckey
        );

        $this->assertSame($newUid, $stat['id']);
        $this->assertSame(bin2hex('Meeting notes'), $stat['conversationid']);
        $this->assertArrayHasKey('conversationindex', $stat);
    }

    public function testDraftModifyRecordsMapBeforeMailmapUpdate(): void
    {
        $synckey = '{uuid}5';
        $oldUid = '100';
        $newUid = '101';
        $message = $this->_draftMailMessage('Edited', 'Subject');
        $callOrder = [];

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getAppliedPIMChange')->willReturn(null);
        $state->expects($this->once())
            ->method('recordAppliedPIMChange')
            ->with($oldUid, $this->callback(function ($stat) use ($newUid) {
                return is_array($stat) && ($stat['id'] ?? null) == $newUid;
            }), $synckey)
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'record';
            });
        $state->expects($this->exactly(2))
            ->method('updateState')
            ->willReturnCallback(function ($type) use (&$callOrder) {
                $callOrder[] = 'update:' . $type;
            });

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('getUser')->willReturn('alice@example.com');
        $driver->expects($this->once())
            ->method('changeMessage')
            ->willReturn([
                'id' => $newUid,
                'mod' => 0,
                'flags' => [],
                'atchash' => [],
            ]);

        $importer = $this->_importer($state, $driver);
        $stat = $importer->importMessageChange(
            $oldUid,
            $message,
            $this->createMock(Horde_ActiveSync_Device::class),
            false,
            Horde_ActiveSync::CLASS_EMAIL,
            $synckey
        );

        $this->assertSame($newUid, $stat['id']);
        $this->assertSame(
            [
                'record',
                'update:' . Horde_ActiveSync::CHANGE_TYPE_DRAFT,
                'update:' . Horde_ActiveSync::CHANGE_TYPE_DELETE,
            ],
            $callOrder
        );
    }

    public function testDraftAddDuplicateClientIdSkipsChangeMessage(): void
    {
        $clientid = 'client-draft-1';
        $uid = '200';
        $message = $this->_draftMailMessage('New draft', 'Hello');

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('isDuplicatePIMAddition')
            ->with($clientid)
            ->willReturn($uid);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->expects($this->never())->method('changeMessage');

        $importer = $this->_importer($state, $driver);
        $result = $importer->importMessageChange(
            false,
            $message,
            $this->createMock(Horde_ActiveSync_Device::class),
            $clientid,
            Horde_ActiveSync::CLASS_EMAIL,
            '{uuid}5'
        );

        $this->assertSame($uid, $result);
    }

    public function testRemoveAlreadyMappedAsDeletedSkipsDriver(): void
    {
        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('isMailMapChangeApplied')
            ->with('100', Horde_ActiveSync::CHANGE_TYPE_DELETE)
            ->willReturn(true);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->expects($this->never())->method('deleteMessage');
        $driver->method('getSyncStamp')->willReturn(1);

        $importer = $this->_importer($state, $driver);
        $deleted = $importer->importMessageDeletion(
            ['100'],
            Horde_ActiveSync::CLASS_EMAIL
        );

        $this->assertSame(['100'], $deleted);
    }

    public function testMoveRetryReturnsPriorMapping(): void
    {
        $synckey = '{uuid}5';
        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getCurrentSyncKey')->willReturn($synckey);
        $state->method('getAppliedMailMove')
            ->with('100', $synckey)
            ->willReturn('999');

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->expects($this->never())->method('moveMessage');
        $driver->method('getSyncStamp')->willReturn(1);

        $collections = $this->createMock(Horde_ActiveSync_Collections::class);
        $collections->method('getCollectionClass')
            ->willReturn(Horde_ActiveSync::CLASS_EMAIL);
        $collections->method('getBackendIdForFolderUid')
            ->willReturn('Trash');

        $as = $this->createMock(Horde_ActiveSync::class);
        $as->driver = $driver;
        $as->method('getCollectionsObject')->willReturn($collections);

        $importer = new Horde_ActiveSync_Connector_Importer($as);
        $importer->setLogger(new Horde_Log_Logger(new Horde_Log_Handler_Null()));
        $importer->init($state, 'folder-uid', 0);

        $ref = new ReflectionClass($importer);
        $folderId = $ref->getProperty('_folderId');
        $folderId->setAccessible(true);
        $folderId->setValue($importer, 'INBOX/Drafts');

        $result = $importer->importMessageMove(['100'], 'trash-uid');

        $this->assertSame(['100' => '999'], $result['results']);
        $this->assertSame([], $result['missing']);
    }

    public function testFlagModifyRetryUnderSameSyncKeySkipsDriver(): void
    {
        $synckey = '{uuid}5';
        $message = new Horde_ActiveSync_Message_Mail([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);
        $message->read = Horde_ActiveSync_Message_Mail::FLAG_READ_SEEN;

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('isMailMapChangeApplied')
            ->with('50', Horde_ActiveSync::CHANGE_TYPE_FLAGS, $synckey)
            ->willReturn(true);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->expects($this->never())->method('changeMessage');

        $importer = $this->_importer($state, $driver);
        $stat = $importer->importMessageChange(
            '50',
            $message,
            $this->createMock(Horde_ActiveSync_Device::class),
            false,
            Horde_ActiveSync::CLASS_EMAIL,
            $synckey
        );

        $this->assertSame('50', $stat['id']);
    }

    /**
     * @return Horde_ActiveSync_Connector_Importer
     */
    protected function _importer($state, $driver)
    {
        $collections = $this->createMock(Horde_ActiveSync_Collections::class);
        $collections->method('getBackendIdForFolderUid')
            ->willReturn('INBOX/Drafts');

        $as = $this->createMock(Horde_ActiveSync::class);
        $as->driver = $driver;
        $as->method('getCollectionsObject')->willReturn($collections);

        $importer = new Horde_ActiveSync_Connector_Importer($as);
        $importer->setLogger(new Horde_Log_Logger(new Horde_Log_Handler_Null()));
        $importer->init($state, 'folder-uid', 0);

        return $importer;
    }

    protected function _draftMailMessage(string $body, string $subject): Horde_ActiveSync_Message_Mail
    {
        $message = new Horde_ActiveSync_Message_Mail([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);
        $message->to = 'alice@example.com';
        $message->subject = $subject;
        $airsyncBody = new Horde_ActiveSync_Message_AirSyncBaseBody([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);
        $airsyncBody->type = Horde_ActiveSync::BODYPREF_TYPE_PLAIN;
        $airsyncBody->data = $body;
        $message->airsyncbasebody = $airsyncBody;

        return $message;
    }
}
