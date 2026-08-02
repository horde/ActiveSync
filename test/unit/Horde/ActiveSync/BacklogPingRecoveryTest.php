<?php

/**
 * Unit tests for the undrained SYNC backlog recovery via PING.
 *
 * When a windowed SYNC response ships MOREAVAILABLE and the client never
 * returns to continue the drain (e.g. its sync loop died on a transient
 * connection error), pollForChanges() must report the collection as
 * changed so the client resumes - guarded by a grace period and a
 * trigger cap.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project
 * @package   ActiveSync
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use Horde_ActiveSync_Collections;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_SyncCache;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Horde_ActiveSync_Collections::class)]
#[CoversClass(Horde_ActiveSync_SyncCache::class)]
class BacklogPingRecoveryTest extends TestCase
{
    private const COLLECTION_ID = 'F1234abcd';

    public function testSyncCacheBacklogFlagLifecycle()
    {
        $cache = $this->_cacheFixture();

        // No flag yet.
        $this->assertFalse($cache->hasStaleBacklog(self::COLLECTION_ID, 0));
        $this->assertSame(0, $cache->getBacklogPingCount(self::COLLECTION_ID));

        // Fresh flag: stale with zero grace, not stale within the grace.
        $cache->setBacklogFlag(self::COLLECTION_ID);
        $this->assertTrue($cache->hasStaleBacklog(self::COLLECTION_ID, 0));
        $this->assertFalse($cache->hasStaleBacklog(self::COLLECTION_ID, 60));

        // Trigger counting.
        $cache->incrementBacklogPingCount(self::COLLECTION_ID);
        $cache->incrementBacklogPingCount(self::COLLECTION_ID);
        $this->assertSame(2, $cache->getBacklogPingCount(self::COLLECTION_ID));

        // A new windowed response refreshes the flag and resets the counter.
        $cache->setBacklogFlag(self::COLLECTION_ID);
        $this->assertSame(0, $cache->getBacklogPingCount(self::COLLECTION_ID));

        // A drained SYNC clears everything.
        $cache->incrementBacklogPingCount(self::COLLECTION_ID);
        $cache->resetBacklogFlag(self::COLLECTION_ID);
        $this->assertFalse($cache->hasStaleBacklog(self::COLLECTION_ID, 0));
        $this->assertSame(0, $cache->getBacklogPingCount(self::COLLECTION_ID));
    }

    public function testSyncCacheBacklogFlagOnMissingCollectionIsHarmless()
    {
        $cache = $this->_cacheFixture();

        $cache->setBacklogFlag('Fmissing');
        $cache->incrementBacklogPingCount('Fmissing');
        $cache->resetBacklogFlag('Fmissing');
        $this->assertFalse($cache->hasStaleBacklog('Fmissing', 0));
        $this->assertSame(0, $cache->getBacklogPingCount('Fmissing'));
    }

    /**
     * A stale backlog (older than the grace period) must be reported as a
     * change so the client resumes its SYNC loop.
     */
    public function testPollForChangesRecoversStaleBacklog()
    {
        $cache = $this->_cacheFixture(time() - 2 * Horde_ActiveSync_Collections::BACKLOG_GRACE_PERIOD);
        $collections = $this->_collectionsFixture($cache);
        $collections->expects($this->once())
            ->method('setGetChangesFlag')
            ->with(self::COLLECTION_ID);

        $this->assertTrue(
            $collections->pollForChanges(1, 1, ['pingable' => true])
        );
        $this->assertTrue($cache->hasPingChangeFlag(self::COLLECTION_ID));
        $this->assertSame(1, $cache->getBacklogPingCount(self::COLLECTION_ID));
    }

    /**
     * A fresh backlog flag (client still actively draining) must NOT
     * trigger; the PING keeps polling normally.
     */
    public function testPollForChangesHonorsGracePeriod()
    {
        $cache = $this->_cacheFixture(time());
        $collections = $this->_collectionsFixture($cache);
        $collections->expects($this->never())->method('setGetChangesFlag');

        $this->assertFalse(
            $collections->pollForChanges(1, 1, ['pingable' => true])
        );
        $this->assertSame(0, $cache->getBacklogPingCount(self::COLLECTION_ID));
    }

    /**
     * After BACKLOG_TRIGGER_MAX fruitless recovery notifications the flag
     * goes silent - no endless PING/SYNC loop from a stuck flag.
     */
    public function testPollForChangesHonorsTriggerCap()
    {
        $cache = $this->_cacheFixture(
            time() - 2 * Horde_ActiveSync_Collections::BACKLOG_GRACE_PERIOD,
            Horde_ActiveSync_Collections::BACKLOG_TRIGGER_MAX
        );
        $collections = $this->_collectionsFixture($cache);
        $collections->expects($this->never())->method('setGetChangesFlag');

        $this->assertFalse(
            $collections->pollForChanges(1, 1, ['pingable' => true])
        );
        $this->assertSame(
            Horde_ActiveSync_Collections::BACKLOG_TRIGGER_MAX,
            $cache->getBacklogPingCount(self::COLLECTION_ID)
        );
    }

    /**
     * Build a real SyncCache seeded with one pingable email collection.
     *
     * @param integer|null $backlog  Backlog flag timestamp, null for none.
     * @param integer $backlogpings  Pre-existing trigger count.
     */
    protected function _cacheFixture($backlog = null, $backlogpings = 0): Horde_ActiveSync_SyncCache
    {
        $collection = [
            'lastsynckey' => '{6a13541c-a3bc-448b-853c-915b00000000}14',
            'class' => Horde_ActiveSync::CLASS_EMAIL,
            'serverid' => 'INBOX',
            'pingable' => true,
        ];
        if ($backlog !== null) {
            $collection['backlog'] = $backlog;
            $collection['backlogpings'] = $backlogpings;
        }

        $state = $this->createMock('Horde_ActiveSync_State_Sql');
        $state->method('getSyncCache')->willReturn([
            'timestamp' => 0,
            'collections' => [self::COLLECTION_ID => $collection],
            'folders' => [],
        ]);

        return new Horde_ActiveSync_SyncCache($state, 'device', 'user');
    }

    /**
     * Partial Collections mock around the real poll loop and the real cache.
     */
    protected function _collectionsFixture(Horde_ActiveSync_SyncCache $cache)
    {
        $as = $this->createMock(Horde_ActiveSync::class);
        $as->logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());
        $as->state = $cache->state;
        $as->device = (object) [
            'id' => 'device',
            'version' => Horde_ActiveSync::VERSION_FOURTEEN,
        ];
        $as->provisioning = Horde_ActiveSync::PROVISIONING_NONE;

        $collections = $this->getMockBuilder(Horde_ActiveSync_Collections::class)
            ->setConstructorArgs([$cache, $as])
            ->onlyMethods([
                'initCollectionState',
                'updateCollectionsFromCache',
                'checkStaleRequest',
                'collectionsNeedFolderResync',
                'restorePingableCollectionsFromCache',
                'havePingableCollections',
                'haveHierarchy',
                'save',
                'setGetChangesFlag',
                'getCollectionChangeCount',
                '_sleep',
            ])
            ->getMock();

        $collections->method('collectionsNeedFolderResync')->willReturn(false);
        $collections->method('havePingableCollections')->willReturn(true);
        $collections->method('haveHierarchy')->willReturn(true);
        $collections->method('checkStaleRequest')->willReturn(false);
        $collections->method('getCollectionChangeCount')->willReturn(0);

        $property = new ReflectionProperty(Horde_ActiveSync_Collections::class, '_collections');
        $property->setAccessible(true);
        $property->setValue($collections, [
            self::COLLECTION_ID => [
                'id' => self::COLLECTION_ID,
                'synckey' => '{6a13541c-a3bc-448b-853c-915b00000000}14',
                'class' => Horde_ActiveSync::CLASS_EMAIL,
                'serverid' => 'INBOX',
            ],
        ]);

        return $collections;
    }
}
