<?php

/**
 * Persistent SyncCache foldermap (survives clearFolders / FolderSync reset).
 *
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
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use Horde_ActiveSync_Collections;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Message_Folder;
use Horde_ActiveSync_State_Base;
use Horde_ActiveSync_SyncCache;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class FolderUidMapPersistenceTest extends TestCase
{
    protected function _newCache(array $stored = []): array
    {
        $stored = array_merge([
            'confirmed_synckeys' => [],
            'lasthbsyncstarted' => false,
            'lastsyncendnormal' => false,
            'timestamp' => time(),
            'wait' => false,
            'hbinterval' => false,
            'folders' => [],
            'foldermap' => [],
            'hierarchy' => false,
            'collections' => [],
            'pingheartbeat' => false,
        ], $stored);

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getSyncCache')->willReturn($stored);

        $cache = new Horde_ActiveSync_SyncCache(
            $state,
            'device',
            'user',
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );

        return [$cache, $state];
    }

    public function testClearFoldersPreservesFolderMap()
    {
        [$cache] = $this->_newCache([
            'folders' => [
                'Faaaaaaaa' => [
                    'class' => 'Email',
                    'serverid' => 'INBOX',
                    'type' => Horde_ActiveSync::FOLDER_TYPE_INBOX,
                ],
            ],
            'foldermap' => [
                'INBOX' => 'Faaaaaaaa',
            ],
        ]);

        $cache->clearFolders();

        $this->assertSame([], $cache->getFolders());
        $this->assertSame(['INBOX' => 'Faaaaaaaa'], $cache->getFolderMap());
    }

    public function testEnsureFolderMapSeedsFromFolders()
    {
        [$cache] = $this->_newCache([
            'folders' => [
                'Fbbbbbbbb' => [
                    'class' => 'Email',
                    'serverid' => 'INBOX/Sent',
                    'type' => Horde_ActiveSync::FOLDER_TYPE_USER_MAIL,
                ],
            ],
            'foldermap' => [],
        ]);

        $this->assertSame(
            ['INBOX/Sent' => 'Fbbbbbbbb'],
            $cache->getFolderMap()
        );
    }

    public function testUpdateFolderMaintainsFolderMapAndRename()
    {
        [$cache] = $this->_newCache([
            'foldermap' => [
                'INBOX/Old' => 'Fcccccccc',
            ],
        ]);

        $folder = new Horde_ActiveSync_Message_Folder(['protocolversion' => Horde_ActiveSync::VERSION_FOURTEEN]);
        $folder->serverid = 'Fcccccccc';
        $folder->_serverid = 'INBOX/New';
        $folder->type = Horde_ActiveSync::FOLDER_TYPE_USER_MAIL;
        $folder->displayname = 'New';
        $folder->parentid = '0';

        $cache->updateFolder($folder);

        $this->assertSame('INBOX/New', $cache->getFolders()['Fcccccccc']['serverid']);
        $this->assertSame(['INBOX/New' => 'Fcccccccc'], $cache->getFolderMap());
        $this->assertArrayNotHasKey('INBOX/Old', $cache->getFolderMap());
    }

    public function testDeleteFolderRemovesFolderMapEntry()
    {
        [$cache] = $this->_newCache([
            'folders' => [
                'Fdddddddd' => [
                    'class' => 'Email',
                    'serverid' => 'INBOX/Gone',
                    'type' => Horde_ActiveSync::FOLDER_TYPE_USER_MAIL,
                ],
            ],
            'foldermap' => [
                'INBOX/Gone' => 'Fdddddddd',
                'INBOX' => 'Feeeeeeee',
            ],
        ]);

        $cache->deleteFolder('Fdddddddd');

        $this->assertArrayNotHasKey('Fdddddddd', $cache->getFolders());
        $this->assertSame(['INBOX' => 'Feeeeeeee'], $cache->getFolderMap());
    }

    public function testReconcileFoldersDropsStaleEntries()
    {
        [$cache] = $this->_newCache([
            'folders' => [
                'Flive0001' => [
                    'class' => 'Email',
                    'serverid' => 'INBOX',
                    'type' => Horde_ActiveSync::FOLDER_TYPE_INBOX,
                ],
                'Fstale001' => [
                    'class' => 'Email',
                    'serverid' => 'INBOX/Stale',
                    'type' => Horde_ActiveSync::FOLDER_TYPE_USER_MAIL,
                ],
            ],
            'foldermap' => [
                'INBOX' => 'Flive0001',
                'INBOX/Stale' => 'Fstale001',
            ],
        ]);

        $cache->reconcileFolders(['Flive0001']);

        $this->assertSame(['Flive0001'], array_keys($cache->getFolders()));
        $this->assertSame(['INBOX' => 'Flive0001'], $cache->getFolderMap());
    }

    public function testGetBackendIdFallsBackToFolderMapWhenFoldersEmpty()
    {
        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getFolderUidToBackendIdMap')->willReturn([
            'INBOX' => 'Fpersist1',
        ]);

        $cache = $this->createMock(Horde_ActiveSync_SyncCache::class);
        $cache->method('getFolder')->willReturn(false);

        $as = $this->createMock(Horde_ActiveSync::class);
        $as->state = $state;
        $as->logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());

        $collections = new Horde_ActiveSync_Collections($cache, $as);

        $this->assertSame(
            'INBOX',
            $collections->getBackendIdForFolderUid('Fpersist1')
        );
    }

    public function testStateMapPrefersPersistedFolderMap()
    {
        $state = $this->getMockBuilder(Horde_ActiveSync_State_Base::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $state->expects($this->once())
            ->method('getSyncCache')
            ->willReturn([
                'foldermap' => ['INBOX' => 'Ffrommap0'],
                'folders' => [
                    'Folduid01' => [
                        'class' => 'Email',
                        'serverid' => 'INBOX',
                        'type' => 2,
                    ],
                ],
            ]);

        $device = new \stdClass();
        $device->id = 'device';
        $device->user = 'user';
        $prop = new \ReflectionProperty(Horde_ActiveSync_State_Base::class, '_deviceInfo');
        $prop->setAccessible(true);
        $prop->setValue($state, $device);

        $this->assertSame(
            ['INBOX' => 'Ffrommap0'],
            $state->getFolderUidToBackendIdMap()
        );
    }
}
