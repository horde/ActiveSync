<?php

/**
 * Unit tests for PING behavior when the collection lock is held by a
 * parallel request.
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
use Horde_ActiveSync_Exception_TemporaryFailure;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_SyncCache;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Horde_ActiveSync_Collections::class)]
class PingCollectionLockContentionTest extends TestCase
{
    private const COLLECTION_ID = 'F1234abcd';

    /**
     * A collection lock held by a parallel SYNC must not trigger a state
     * reset; the collection is skipped and retried on the next iteration.
     */
    public function testPollForChangesSkipsCollectionOnLockContention()
    {
        $state = $this->createMock('Horde_ActiveSync_State_Sql');
        $state->method('getSyncCache')->willReturn(['timestamp' => 0]);
        $state->expects($this->never())->method('loadState');

        $cache = new Horde_ActiveSync_SyncCache($state, 'device', 'user');

        $as = $this->createMock(Horde_ActiveSync::class);
        $as->logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());
        $as->state = $state;
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
                '_sleep',
            ])
            ->getMock();

        $collections->method('collectionsNeedFolderResync')->willReturn(false);
        $collections->method('havePingableCollections')->willReturn(true);
        $collections->method('haveHierarchy')->willReturn(true);
        $collections->method('checkStaleRequest')->willReturn(false);
        $collections->method('initCollectionState')->willThrowException(
            new Horde_ActiveSync_Exception_TemporaryFailure('Collection lock held.')
        );
        $collections->expects($this->never())->method('setGetChangesFlag');

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

        $this->assertFalse(
            $collections->pollForChanges(1, 1, ['pingable' => true])
        );
    }
}
