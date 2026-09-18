<?php

/**
 * Ghost collections: synckey present, folders[] entry missing.
 *
 * Incremental FolderSync cannot repair that skew; PING must self-heal
 * instead of returning FOLDERSYNC_REQUIRED (PING status 7 loop).
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
use Horde_ActiveSync_State_Base;
use Horde_ActiveSync_SyncCache;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class CollectionsGhostFolderCacheTest extends TestCase
{
    private const GHOST_UID = 'A99cfc101';
    private const LIVE_UID = 'Ad225f47d';
    private const LIVE_BACKEND = 'Calendar:livecalendarid';
    private const MAP_UID = 'A11111111';
    private const MAP_BACKEND = 'Calendar:stillhere';

    protected function _newCollections(array $stored): Horde_ActiveSync_Collections
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
            'hierarchy' => '{00000000-0000-0000-0000-000000000001}3',
            'collections' => [],
            'pingheartbeat' => false,
        ], $stored);

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getSyncCache')->willReturn($stored);
        $state->method('saveSyncCache');

        $cache = new Horde_ActiveSync_SyncCache(
            $state,
            'device',
            'user',
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );

        $as = $this->createMock(Horde_ActiveSync::class);
        $as->logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());
        $as->device = (object) [
            'id' => 'device',
            'version' => Horde_ActiveSync::VERSION_FOURTEEN,
        ];

        return new Horde_ActiveSync_Collections($cache, $as);
    }

    protected function _cache(Horde_ActiveSync_Collections $collections): Horde_ActiveSync_SyncCache
    {
        $prop = new \ReflectionProperty(Horde_ActiveSync_Collections::class, '_cache');
        $prop->setAccessible(true);

        return $prop->getValue($collections);
    }

    public function testHealDropsGhostCollectionWithoutFolderMapEntry()
    {
        $collections = $this->_newCollections([
            'folders' => [
                self::LIVE_UID => [
                    'class' => Horde_ActiveSync::CLASS_CALENDAR,
                    'serverid' => self::LIVE_BACKEND,
                    'type' => Horde_ActiveSync::FOLDER_TYPE_USER_APPOINTMENT,
                ],
            ],
            'foldermap' => [
                self::LIVE_BACKEND => self::LIVE_UID,
            ],
            'collections' => [
                self::LIVE_UID => [
                    'class' => Horde_ActiveSync::CLASS_CALENDAR,
                    'synckey' => '{00000000-0000-0000-0000-000000000002}80',
                    'pingable' => true,
                    'serverid' => self::LIVE_BACKEND,
                ],
                self::GHOST_UID => [
                    'class' => Horde_ActiveSync::CLASS_CALENDAR,
                    'synckey' => '{00000000-0000-0000-0000-000000000003}4',
                    'pingable' => true,
                ],
            ],
        ]);

        $this->assertTrue($collections->healCollectionsMissingFolderCache());

        $cache = $this->_cache($collections);
        $this->assertFalse($cache->collectionExists(self::GHOST_UID));
        $this->assertNotFalse($cache->getFolder(self::LIVE_UID));
        $this->assertTrue($cache->collectionExists(self::LIVE_UID));
        $this->assertFalse($collections->collectionsNeedFolderResync());
    }

    public function testHealRestoresFolderCacheFromFolderMap()
    {
        $collections = $this->_newCollections([
            'folders' => [],
            'foldermap' => [
                self::MAP_BACKEND => self::MAP_UID,
            ],
            'collections' => [
                self::MAP_UID => [
                    'class' => Horde_ActiveSync::CLASS_CALENDAR,
                    'synckey' => '{00000000-0000-0000-0000-000000000004}2',
                    'pingable' => true,
                ],
            ],
        ]);

        $this->assertTrue($collections->healCollectionsMissingFolderCache());

        $cache = $this->_cache($collections);
        $folder = $cache->getFolder(self::MAP_UID);
        $this->assertNotFalse($folder);
        $this->assertSame(self::MAP_BACKEND, $folder['serverid']);
        $this->assertSame(Horde_ActiveSync::CLASS_CALENDAR, $folder['class']);
        $this->assertTrue($cache->collectionExists(self::MAP_UID));
        $this->assertFalse($collections->collectionsNeedFolderResync());
    }

    public function testHealLeavesHealthyCollectionsUntouched()
    {
        $collections = $this->_newCollections([
            'folders' => [
                self::LIVE_UID => [
                    'class' => Horde_ActiveSync::CLASS_CALENDAR,
                    'serverid' => self::LIVE_BACKEND,
                    'type' => Horde_ActiveSync::FOLDER_TYPE_USER_APPOINTMENT,
                ],
            ],
            'foldermap' => [
                self::LIVE_BACKEND => self::LIVE_UID,
            ],
            'collections' => [
                self::LIVE_UID => [
                    'class' => Horde_ActiveSync::CLASS_CALENDAR,
                    'synckey' => '{00000000-0000-0000-0000-000000000002}80',
                    'pingable' => true,
                    'serverid' => self::LIVE_BACKEND,
                ],
            ],
        ]);

        $this->assertFalse($collections->healCollectionsMissingFolderCache());
        $this->assertFalse($collections->collectionsNeedFolderResync());
        $this->assertTrue($this->_cache($collections)->collectionExists(self::LIVE_UID));
    }
}
