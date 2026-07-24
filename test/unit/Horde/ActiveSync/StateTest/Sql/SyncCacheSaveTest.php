<?php

/**
 * Unit tests for dirty-field aware SyncCache persistence in the SQL state
 * backend.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */

namespace Horde\ActiveSync\StateTest\Sql;

use Horde\ActiveSync\Test\Helpers\DbHelper;
use PHPUnit\Framework\TestCase;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_State_Sql;
use Horde_Log_Handler_Null;
use ReflectionClass;

class SyncCacheSaveTest extends TestCase
{
    public function testInsertNewCacheRow()
    {
        $state = $this->_newState();
        $cache = $this->_cacheFixture();

        $state->saveSyncCache($cache, 'device', 'user@example.com', ['timestamp' => true]);

        $stored = $state->getSyncCache('device', 'user@example.com');
        $this->assertEquals('100', $stored['timestamp']);
        $this->assertEquals(['F1' => ['class' => 'Email', 'lastsynckey' => 'a']], $stored['collections']);
    }

    /**
     * A save must only write its dirty fields; fields changed by a parallel
     * request in the meantime must survive.
     */
    public function testDirtyFieldMergePreservesConcurrentChanges()
    {
        $state = $this->_newState();
        $initial = $this->_cacheFixture();
        $state->saveSyncCache($initial, 'device', 'user@example.com', ['timestamp' => true]);

        // Parallel request B advances collection F1.
        $cacheB = $initial;
        $cacheB['collections']['F1']['lastsynckey'] = 'b';
        $cacheB['timestamp'] = 200;
        $state->saveSyncCache(
            $cacheB,
            'device',
            'user@example.com',
            ['collections' => ['F1' => true], 'timestamp' => true]
        );

        // Request A still holds the stale in-memory cache (F1 => 'a') but
        // only marked lasthbsyncstarted dirty. Its save must NOT clobber
        // B's F1 change.
        $cacheA = $initial;
        $cacheA['lasthbsyncstarted'] = 300;
        $cacheA['timestamp'] = 300;
        $state->saveSyncCache(
            $cacheA,
            'device',
            'user@example.com',
            ['lasthbsyncstarted' => true, 'timestamp' => true]
        );

        $stored = $state->getSyncCache('device', 'user@example.com');
        $this->assertEquals('b', $stored['collections']['F1']['lastsynckey']);
        $this->assertEquals(300, $stored['lasthbsyncstarted']);
        $this->assertEquals('300', $stored['timestamp']);
    }

    /**
     * A collection removed from the in-memory cache with a per-id dirty
     * entry must be removed from storage.
     */
    public function testCollectionRemovalViaPerIdDirty()
    {
        $state = $this->_newState();
        $initial = $this->_cacheFixture();
        $initial['collections']['F2'] = ['class' => 'Email', 'lastsynckey' => 'x'];
        $state->saveSyncCache($initial, 'device', 'user@example.com', ['timestamp' => true]);

        $cache = $initial;
        unset($cache['collections']['F2']);
        $state->saveSyncCache(
            $cache,
            'device',
            'user@example.com',
            ['collections' => ['F2' => true], 'timestamp' => true]
        );

        $stored = $state->getSyncCache('device', 'user@example.com');
        $this->assertArrayNotHasKey('F2', $stored['collections']);
        $this->assertArrayHasKey('F1', $stored['collections']);
    }

    /**
     * A boolean true dirty entry for collections replaces the whole
     * property (clearCollections() semantics).
     */
    public function testWholeCollectionsReplaceWhenDirtyTrue()
    {
        $state = $this->_newState();
        $initial = $this->_cacheFixture();
        $state->saveSyncCache($initial, 'device', 'user@example.com', ['timestamp' => true]);

        $cache = $initial;
        $cache['collections'] = [];
        $state->saveSyncCache(
            $cache,
            'device',
            'user@example.com',
            ['collections' => true, 'timestamp' => true]
        );

        $stored = $state->getSyncCache('device', 'user@example.com');
        $this->assertSame([], $stored['collections']);
    }

    /**
     * Nothing dirty on an existing row must not write at all.
     */
    public function testSkipWriteWhenNothingDirty()
    {
        $state = $this->_newState();
        $initial = $this->_cacheFixture();
        $state->saveSyncCache($initial, 'device', 'user@example.com', ['timestamp' => true]);

        $cache = $initial;
        $cache['timestamp'] = 999;
        $cache['lasthbsyncstarted'] = 999;
        $state->saveSyncCache($cache, 'device', 'user@example.com', []);

        $stored = $state->getSyncCache('device', 'user@example.com');
        $this->assertEquals('100', $stored['timestamp']);
        $this->assertNotEquals(999, $stored['lasthbsyncstarted']);
    }

    protected function _cacheFixture()
    {
        return [
            'confirmed_synckeys' => [],
            'lasthbsyncstarted' => false,
            'lastsyncendnormal' => false,
            'timestamp' => 100,
            'wait' => false,
            'hbinterval' => false,
            'folders' => [],
            'foldermap' => [],
            'hierarchy' => false,
            'collections' => ['F1' => ['class' => 'Email', 'lastsynckey' => 'a']],
            'pingheartbeat' => false,
            'synckeycounter' => [],
        ];
    }

    protected function _newState()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension is not loaded');
        }

        $migrationDir = dirname(__DIR__, 6) . '/migration/Horde/ActiveSync';
        $db = DbHelper::createSqliteDb([
            'migrations' => [[
                'migrationsPath' => $migrationDir,
                'schemaTableName' => 'horde_activesync_schema_info',
            ]],
        ]);

        $state = new Horde_ActiveSync_State_Sql(['db' => $db]);
        $this->_setProperty(
            $state,
            '_logger',
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );

        return $state;
    }

    protected function _setProperty($object, $property, $value)
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
