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

        // SyncReplies must echo the client's ServerId, not the new IMAP UID.
        $this->assertSame($oldUid, $stat['id']);
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

        $this->assertSame($oldUid, $stat['id']);
        $this->assertSame(
            [
                'record',
                'update:' . Horde_ActiveSync::CHANGE_TYPE_DRAFT,
                'update:' . Horde_ActiveSync::CHANGE_TYPE_DELETE,
            ],
            $callOrder
        );
    }

    public function testDraftModifyReplyUsesClientServerIdWhenSubjectEmpty(): void
    {
        $synckey = '{uuid}5';
        $oldUid = '100';
        $newUid = '101';
        $message = $this->_draftMailMessage('Body only', '');

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getAppliedPIMChange')->willReturn(null);
        $state->expects($this->once())
            ->method('recordAppliedPIMChange')
            ->with($oldUid, $this->callback(function ($stat) use ($newUid) {
                return is_array($stat) && ($stat['id'] ?? null) == $newUid;
            }), $synckey);
        $state->expects($this->exactly(2))->method('updateState');

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('getUser')->willReturn('alice@example.com');
        $driver->expects($this->once())
            ->method('changeMessage')
            ->willReturn([
                'id' => $newUid,
                'mod' => 0,
                'flags' => [],
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

        $this->assertSame($oldUid, $stat['id']);
        $this->assertSame(bin2hex('draft:' . $oldUid), $stat['conversationid']);
        $this->assertNotSame('', $stat['conversationid']);
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

    public function testNotesRemoveDoesNotQueryMailMap(): void
    {
        $noteUid = '6a5a2daf-881c-4a66-b8cc-574e00000000';

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->expects($this->never())->method('isMailMapChangeApplied');
        $state->expects($this->once())
            ->method('updateState')
            ->with(
                Horde_ActiveSync::CHANGE_TYPE_DELETE,
                $this->callback(function ($change) use ($noteUid) {
                    return ($change['id'] ?? null) === $noteUid;
                }),
                Horde_ActiveSync::CHANGE_ORIGIN_PIM,
                'alice@example.com'
            );

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('getUser')->willReturn('alice@example.com');
        $driver->method('getSyncStamp')->willReturn(1);
        $driver->expects($this->once())
            ->method('deleteMessage')
            ->with($this->anything(), [$noteUid])
            ->willReturn([$noteUid]);

        $importer = $this->_importer($state, $driver);
        $deleted = $importer->importMessageDeletion(
            [$noteUid],
            Horde_ActiveSync::CLASS_NOTES
        );

        $this->assertSame([$noteUid], $deleted);
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

    public function testDraftModifyRecordsDraftUidAlias(): void
    {
        $synckey = '{uuid}5';
        $oldUid = '100';
        $newUid = '101';
        $message = $this->_draftMailMessage('Edited', 'Subject');

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getAppliedPIMChange')->willReturn(null);
        $state->expects($this->once())
            ->method('recordDraftUidAlias')
            ->with($oldUid, $newUid);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('getUser')->willReturn('alice@example.com');
        $driver->method('changeMessage')
            ->willReturn(['id' => $newUid, 'mod' => 0, 'flags' => []]);

        $importer = $this->_importer($state, $driver);
        $stat = $importer->importMessageChange(
            $oldUid,
            $message,
            $this->createMock(Horde_ActiveSync_Device::class),
            false,
            Horde_ActiveSync::CLASS_EMAIL,
            $synckey
        );

        $this->assertSame($oldUid, $stat['id']);
    }

    public function testDraftModifyResolvesAliasedServerIdForBackend(): void
    {
        $synckey = '{uuid}7';
        $clientId = '100';
        $liveUid = '150';
        $newUid = '201';
        $message = $this->_draftMailMessage('Second edit', 'Subject');
        $updates = [];

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getAppliedPIMChange')->willReturn(null);
        $state->method('getDraftUidForClientId')
            ->with($clientId)
            ->willReturn($liveUid);
        // The alias moves along to the newest UID under the same client id.
        $state->expects($this->once())
            ->method('recordDraftUidAlias')
            ->with($clientId, $newUid);
        $state->method('updateState')
            ->willReturnCallback(function ($type, $change) use (&$updates) {
                $updates[] = [$type, $change['id'] ?? null];
            });

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('getUser')->willReturn('alice@example.com');
        // The backend must operate on the live UID, not the stale client id.
        $driver->expects($this->once())
            ->method('changeMessage')
            ->with('INBOX/Drafts', $liveUid, $message, $this->anything())
            ->willReturn(['id' => $newUid, 'mod' => 0, 'flags' => []]);

        $importer = $this->_importer($state, $driver);
        $stat = $importer->importMessageChange(
            $clientId,
            $message,
            $this->createMock(Horde_ActiveSync_Device::class),
            false,
            Horde_ActiveSync::CLASS_EMAIL,
            $synckey
        );

        // Reply stays on the client's ServerId.
        $this->assertSame($clientId, $stat['id']);
        // Mirror suppression: draft row for the new UID, delete row for the
        // UID that was actually removed from IMAP (the live one).
        $this->assertSame(
            [
                [Horde_ActiveSync::CHANGE_TYPE_DRAFT, $newUid],
                [Horde_ActiveSync::CHANGE_TYPE_DELETE, $liveUid],
            ],
            $updates
        );
    }

    public function testRemoveResolvesAliasedServerIdAndReportsClientId(): void
    {
        $clientId = '100';
        $liveUid = '150';

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getDraftUidForClientId')
            ->with($clientId)
            ->willReturn($liveUid);
        $state->method('isMailMapChangeApplied')->willReturn(false);
        $state->expects($this->once())
            ->method('updateState')
            ->with(
                Horde_ActiveSync::CHANGE_TYPE_DELETE,
                $this->callback(function ($change) use ($liveUid) {
                    return ($change['id'] ?? null) === $liveUid;
                }),
                Horde_ActiveSync::CHANGE_ORIGIN_PIM,
                'alice@example.com'
            );
        $state->expects($this->once())
            ->method('removeDraftUidAliases')
            ->with([$liveUid]);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('getUser')->willReturn('alice@example.com');
        $driver->method('getSyncStamp')->willReturn(1);
        $driver->expects($this->once())
            ->method('deleteMessage')
            ->with($this->anything(), [$liveUid])
            ->willReturn([$liveUid]);

        $importer = $this->_importer($state, $driver);
        $deleted = $importer->importMessageDeletion(
            [$clientId],
            Horde_ActiveSync::CLASS_EMAIL
        );

        // The client is told its own ServerId was deleted.
        $this->assertSame([$clientId], $deleted);
    }

    public function testReadFlagResolvesAliasedServerId(): void
    {
        $clientId = '100';
        $liveUid = '150';

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getDraftUidForClientId')
            ->with($clientId)
            ->willReturn($liveUid);
        $state->expects($this->once())
            ->method('updateState')
            ->with(
                Horde_ActiveSync::CHANGE_TYPE_FLAGS,
                $this->callback(function ($change) use ($liveUid) {
                    return ($change['id'] ?? null) === $liveUid;
                }),
                Horde_ActiveSync::CHANGE_ORIGIN_PIM,
                'alice@example.com'
            );

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('getUser')->willReturn('alice@example.com');
        $driver->expects($this->once())
            ->method('setReadFlag')
            ->with('INBOX/Drafts', $liveUid, 1);

        $importer = $this->_importer($state, $driver);
        $importer->importMessageReadFlag($clientId, 1);
    }

    public function testNoOpDraftModifySkipsRewrite(): void
    {
        $clientId = '100';
        // Same content modulo line endings and trailing whitespace.
        $message = $this->_draftMailMessage("Line1\r\nLine2\n", 'Subject');

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getAppliedPIMChange')->willReturn(null);
        $state->expects($this->never())->method('recordAppliedPIMChange');
        $state->expects($this->never())->method('recordDraftUidAlias');
        $state->expects($this->never())->method('updateState');

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->expects($this->once())
            ->method('fetch')
            ->with(
                'INBOX/Drafts',
                $clientId,
                $this->callback(function ($collection) {
                    $pref = $collection['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_PLAIN] ?? null;
                    return is_array($pref) && $pref['truncationsize'] === 0;
                })
            )
            ->willReturn($this->_draftMailMessage("Line1\nLine2", 'Subject'));
        $driver->expects($this->never())->method('changeMessage');

        $importer = $this->_importer($state, $driver);
        $stat = $importer->importMessageChange(
            $clientId,
            $message,
            $this->createMock(Horde_ActiveSync_Device::class),
            false,
            Horde_ActiveSync::CLASS_EMAIL,
            '{uuid}5'
        );

        $this->assertSame($clientId, $stat['id']);
        $this->assertSame(bin2hex('Subject'), $stat['conversationid']);
        $this->assertArrayHasKey('conversationindex', $stat);
    }

    public function testDraftModifyWithChangedBodyStillRewrites(): void
    {
        $clientId = '100';
        $newUid = '101';
        $message = $this->_draftMailMessage('Edited body', 'Subject');

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getAppliedPIMChange')->willReturn(null);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('getUser')->willReturn('alice@example.com');
        $driver->method('fetch')
            ->willReturn($this->_draftMailMessage('Original body', 'Subject'));
        $driver->expects($this->once())
            ->method('changeMessage')
            ->willReturn(['id' => $newUid, 'mod' => 0, 'flags' => []]);

        $importer = $this->_importer($state, $driver);
        $stat = $importer->importMessageChange(
            $clientId,
            $message,
            $this->createMock(Horde_ActiveSync_Device::class),
            false,
            Horde_ActiveSync::CLASS_EMAIL,
            '{uuid}5'
        );

        $this->assertSame($clientId, $stat['id']);
    }

    public function testDraftModifyWithAttachmentInstructionsSkipsNoOpCheck(): void
    {
        $clientId = '100';
        $message = $this->_draftMailMessage('Body', 'Subject');
        $atc = new Horde_ActiveSync_Message_AirSyncBaseAttachment([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);
        $message->airsyncbaseattachments = [$atc];

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getAppliedPIMChange')->willReturn(null);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('getUser')->willReturn('alice@example.com');
        // Attachment add/remove always changes the message: no comparison
        // fetch, straight to the rewrite.
        $driver->expects($this->never())->method('fetch');
        $driver->expects($this->once())
            ->method('changeMessage')
            ->willReturn(['id' => '101', 'mod' => 0, 'flags' => []]);

        $importer = $this->_importer($state, $driver);
        $stat = $importer->importMessageChange(
            $clientId,
            $message,
            $this->createMock(Horde_ActiveSync_Device::class),
            false,
            Horde_ActiveSync::CLASS_EMAIL,
            '{uuid}5'
        );

        $this->assertSame($clientId, $stat['id']);
    }

    public function testDraftModifyFetchFailureFallsBackToRewrite(): void
    {
        $clientId = '100';
        $message = $this->_draftMailMessage('Body', 'Subject');

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getAppliedPIMChange')->willReturn(null);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('getUser')->willReturn('alice@example.com');
        $driver->method('fetch')
            ->willThrowException(new Horde_Exception_NotFound());
        $driver->expects($this->once())
            ->method('changeMessage')
            ->willReturn(['id' => '101', 'mod' => 0, 'flags' => []]);

        $importer = $this->_importer($state, $driver);
        $stat = $importer->importMessageChange(
            $clientId,
            $message,
            $this->createMock(Horde_ActiveSync_Device::class),
            false,
            Horde_ActiveSync::CLASS_EMAIL,
            '{uuid}5'
        );

        $this->assertSame($clientId, $stat['id']);
    }

    public function testNoOpDraftModifyComparesAgainstAliasedLiveUid(): void
    {
        $clientId = '100';
        $liveUid = '150';
        $message = $this->_draftMailMessage('Body', 'Subject');

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getAppliedPIMChange')->willReturn(null);
        $state->method('getDraftUidForClientId')
            ->with($clientId)
            ->willReturn($liveUid);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->expects($this->once())
            ->method('fetch')
            ->with('INBOX/Drafts', $liveUid, $this->anything())
            ->willReturn($this->_draftMailMessage('Body', 'Subject'));
        $driver->expects($this->never())->method('changeMessage');

        $importer = $this->_importer($state, $driver);
        $stat = $importer->importMessageChange(
            $clientId,
            $message,
            $this->createMock(Horde_ActiveSync_Device::class),
            false,
            Horde_ActiveSync::CLASS_EMAIL,
            '{uuid}7'
        );

        $this->assertSame($clientId, $stat['id']);
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
