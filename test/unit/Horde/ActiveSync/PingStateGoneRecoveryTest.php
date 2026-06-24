<?php

/**
 * Unit tests for PING StateGone recovery in Collections.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project (http://www.horde.org/)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use Horde_ActiveSync_Collections;
use Horde_ActiveSync_Exception_StateGone;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_SyncCache;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

#[CoversClass(Horde_ActiveSync_Collections::class)]
class PingStateGoneRecoveryTest extends TestCase
{
    private const COLLECTION_ID = 'T3a22eaf2';

    private const STALE_SYNCKEY = '{6a13541c-a3bc-448b-853c-915b00000000}14';

    private const CURRENT_SYNCKEY = '{6a13541c-a3bc-448b-853c-915b00000000}23';

    public function testRefreshAndRetryCollectionStateReturnsTrueWhenStateLoads()
    {
        $collections = $this->_createCollectionsPartialMock();
        $collections->expects($this->once())->method('updateCollectionsFromCache');
        $collections->expects($this->once())->method('initCollectionState');

        $this->_setCollections($collections, [
            self::COLLECTION_ID => [
                'id' => self::COLLECTION_ID,
                'synckey' => self::STALE_SYNCKEY,
                'lastsynckey' => self::CURRENT_SYNCKEY,
                'class' => Horde_ActiveSync::CLASS_TASKS,
                'serverid' => 'Tasks:tasklist1',
            ],
        ]);

        $this->assertTrue($this->_invokeRefreshAndRetry($collections, self::COLLECTION_ID));
    }

    public function testRefreshAndRetryCollectionStateReturnsFalseWhenStateStillMissing()
    {
        $collections = $this->_createCollectionsPartialMock();
        $collections->expects($this->once())->method('updateCollectionsFromCache');
        $collections->expects($this->once())
            ->method('initCollectionState')
            ->willThrowException(new Horde_ActiveSync_Exception_StateGone());

        $this->_setCollections($collections, [
            self::COLLECTION_ID => [
                'id' => self::COLLECTION_ID,
                'synckey' => self::STALE_SYNCKEY,
                'class' => Horde_ActiveSync::CLASS_TASKS,
                'serverid' => 'Tasks:tasklist1',
            ],
        ]);

        $this->assertFalse($this->_invokeRefreshAndRetry($collections, self::COLLECTION_ID));
    }

    public function testRefreshAndRetryCollectionStateReturnsFalseForUnknownCollection()
    {
        $collections = $this->_createCollectionsPartialMock();
        $collections->expects($this->never())->method('updateCollectionsFromCache');
        $collections->expects($this->never())->method('initCollectionState');

        $this->assertFalse($this->_invokeRefreshAndRetry($collections, 'Tmissing'));
    }

    private function _createCollectionsPartialMock()
    {
        $state = $this->createMock('Horde_ActiveSync_State_Sql');
        $state->method('getSyncCache')->willReturn(['timestamp' => 0]);

        $cache = new Horde_ActiveSync_SyncCache($state, 'device', 'user');

        $as = $this->createMock(Horde_ActiveSync::class);
        $as->logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());

        return $this->getMockBuilder(Horde_ActiveSync_Collections::class)
            ->setConstructorArgs([$cache, $as])
            ->onlyMethods(['initCollectionState', 'updateCollectionsFromCache'])
            ->getMock();
    }

    private function _setCollections(Horde_ActiveSync_Collections $collections, array $data)
    {
        $property = new ReflectionProperty(Horde_ActiveSync_Collections::class, '_collections');
        $property->setAccessible(true);
        $property->setValue($collections, $data);
    }

    private function _invokeRefreshAndRetry(
        Horde_ActiveSync_Collections $collections,
        string $id
    ) {
        $method = new ReflectionMethod(
            Horde_ActiveSync_Collections::class,
            '_refreshAndRetryCollectionState'
        );
        $method->setAccessible(true);

        return $method->invoke($collections, $id);
    }
}
