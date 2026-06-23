<?php

/**
 * Unit tests for the FOLDERSYNC_REQUIRED loop guard.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use Horde_ActiveSync_Collections;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Request_Ping;
use Horde_ActiveSync_Request_Sync;
use Horde_ActiveSync_SyncCache;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversNothing]
class FolderSyncRequiredTest extends TestCase
{
    public function testSyncCacheFolderSyncRequiredCounter()
    {
        $state = $this->createMock('Horde_ActiveSync_State_Sql');
        $state->method('getSyncCache')->willReturn(['timestamp' => 0]);

        $cache = new Horde_ActiveSync_SyncCache($state, 'device', 'user');
        $this->assertSame(0, $cache->getFolderSyncRequiredIgnoredCount());

        $this->assertSame(1, $cache->incrementFolderSyncRequiredIgnored());
        $this->assertSame(1, $cache->getFolderSyncRequiredIgnoredCount());
        $this->assertSame(2, $cache->incrementFolderSyncRequiredIgnored());

        $cache->resetFolderSyncRequiredIgnored();
        $this->assertSame(0, $cache->getFolderSyncRequiredIgnoredCount());
    }

    public function testUpdateHierarchyKeyResetsFolderSyncRequiredCounter()
    {
        $state = $this->createMock('Horde_ActiveSync_State_Sql');
        $state->method('getSyncCache')->willReturn(['timestamp' => 0]);

        $cache = new Horde_ActiveSync_SyncCache($state, 'device', 'user');
        $cache->incrementFolderSyncRequiredIgnored();
        $cache->incrementFolderSyncRequiredIgnored();

        $as = $this->createMock(Horde_ActiveSync::class);
        $as->logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());

        $collections = new Horde_ActiveSync_Collections($cache, $as);
        $collections->updateHierarchyKey('{00000000-0000-0000-0000-000000000001}1');

        $this->assertSame(0, $cache->getFolderSyncRequiredIgnoredCount());
    }

    public function testFolderSyncRequiredStatusEscalatesAfterMaxIgnored()
    {
        $cache = $this->createMock(Horde_ActiveSync_SyncCache::class);
        $cache->method('validateCache')->willReturn(true);
        $cache->expects($this->exactly(6))
            ->method('getFolderSyncRequiredIgnoredCount')
            ->willReturnOnConsecutiveCalls(0, 1, 2, 3, 4, 5);
        $cache->expects($this->exactly(5))->method('incrementFolderSyncRequiredIgnored');
        $cache->expects($this->once())->method('resetFolderSyncRequiredIgnored');

        $as = $this->createMock(Horde_ActiveSync::class);
        $as->logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());

        $collections = new Horde_ActiveSync_Collections($cache, $as);
        $normal = Horde_ActiveSync_Request_Sync::STATUS_FOLDERSYNC_REQUIRED;
        $escalated = Horde_ActiveSync_Request_Sync::STATUS_KEYMISM;

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($normal, $collections->folderSyncRequiredStatus($normal, $escalated));
        }
        $this->assertSame($escalated, $collections->folderSyncRequiredStatus($normal, $escalated));
    }

    public function testSyncResolveFolderSyncRequiredStatusPassesThroughOtherStatuses()
    {
        $collections = $this->createMock(Horde_ActiveSync_Collections::class);
        $collections->expects($this->never())->method('folderSyncRequiredStatus');

        $handler = $this->getMockBuilder(Horde_ActiveSync_Request_Sync::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $collectionsProp = new \ReflectionProperty($handler, '_collections');
        $collectionsProp->setAccessible(true);
        $collectionsProp->setValue($handler, $collections);

        $method = new ReflectionMethod(Horde_ActiveSync_Request_Sync::class, '_resolveFolderSyncRequiredStatus');
        $method->setAccessible(true);

        $this->assertSame(
            Horde_ActiveSync_Request_Sync::STATUS_SUCCESS,
            $method->invoke($handler, Horde_ActiveSync_Request_Sync::STATUS_SUCCESS)
        );
    }

    public function testPingResolveFolderSyncRequiredStatusEscalatesToServerError()
    {
        $collections = $this->createMock(Horde_ActiveSync_Collections::class);
        $collections->expects($this->once())
            ->method('folderSyncRequiredStatus')
            ->with(
                Horde_ActiveSync_Request_Ping::STATUS_FOLDERSYNCREQD,
                Horde_ActiveSync_Request_Ping::STATUS_SERVERERROR
            )
            ->willReturn(Horde_ActiveSync_Request_Ping::STATUS_SERVERERROR);

        $handler = $this->getMockBuilder(Horde_ActiveSync_Request_Ping::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $collectionsProp = new \ReflectionProperty($handler, '_collections');
        $collectionsProp->setAccessible(true);
        $collectionsProp->setValue($handler, $collections);

        $method = new ReflectionMethod(Horde_ActiveSync_Request_Ping::class, '_resolveFolderSyncRequiredStatus');
        $method->setAccessible(true);

        $this->assertSame(
            Horde_ActiveSync_Request_Ping::STATUS_SERVERERROR,
            $method->invoke($handler, Horde_ActiveSync_Request_Ping::STATUS_FOLDERSYNCREQD)
        );
    }
}
