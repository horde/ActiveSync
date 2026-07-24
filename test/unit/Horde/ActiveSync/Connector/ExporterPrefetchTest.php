<?php

/**
 * Unit tests for batched message prefetching in the SYNC exporter.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */

namespace Horde\ActiveSync\Connector;

use PHPUnit\Framework\TestCase;
use Horde_ActiveSync;
use Horde_ActiveSync_Connector_Exporter_Sync;
use Horde_ActiveSync_Driver_Base;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Message_Base;
use Horde_Log_Handler_Null;
use ReflectionClass;

class ExporterPrefetchTest extends TestCase
{
    /**
     * Messages returned by the bulk call must be consumed without any
     * single-message fetches.
     */
    public function testBulkPrefetchConsumedWithoutSingleFetches()
    {
        $msgA = $this->createMock(Horde_ActiveSync_Message_Base::class);
        $msgB = $this->createMock(Horde_ActiveSync_Message_Base::class);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->expects($this->once())
            ->method('getMessagesBulk')
            ->with('INBOX', [10, 11])
            ->willReturn([10 => $msgA, 11 => $msgB]);
        $driver->expects($this->never())->method('getMessage');

        $exporter = $this->_exporterFixture($driver, [
            ['id' => 10, 'type' => Horde_ActiveSync::CHANGE_TYPE_CHANGE],
            ['id' => 11, 'type' => Horde_ActiveSync::CHANGE_TYPE_CHANGE],
        ]);

        $this->assertSame($msgA, $this->_getChangeMessage($exporter, ['id' => 10], 0));
        $this->assertSame($msgB, $this->_getChangeMessage($exporter, ['id' => 11], 1));
    }

    /**
     * Ids missing from the bulk result must fall back to a single fetch,
     * without re-triggering the bulk call.
     */
    public function testMissingIdsFallBackToSingleFetch()
    {
        $msgA = $this->createMock(Horde_ActiveSync_Message_Base::class);
        $msgB = $this->createMock(Horde_ActiveSync_Message_Base::class);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->expects($this->once())
            ->method('getMessagesBulk')
            ->willReturn([10 => $msgA]);
        $driver->expects($this->once())
            ->method('getMessage')
            ->with('INBOX', 11)
            ->willReturn($msgB);

        $exporter = $this->_exporterFixture($driver, [
            ['id' => 10, 'type' => Horde_ActiveSync::CHANGE_TYPE_CHANGE],
            ['id' => 11, 'type' => Horde_ActiveSync::CHANGE_TYPE_CHANGE],
        ]);

        $this->assertSame($msgA, $this->_getChangeMessage($exporter, ['id' => 10], 0));
        $this->assertSame($msgB, $this->_getChangeMessage($exporter, ['id' => 11], 1));
    }

    /**
     * Backends without bulk support (empty result) must behave exactly as
     * before: one single fetch per change, one bulk attempt per window.
     */
    public function testNoBulkSupportFallsBackPerMessage()
    {
        $msgA = $this->createMock(Horde_ActiveSync_Message_Base::class);
        $msgB = $this->createMock(Horde_ActiveSync_Message_Base::class);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->expects($this->once())
            ->method('getMessagesBulk')
            ->willReturn([]);
        $driver->expects($this->exactly(2))
            ->method('getMessage')
            ->willReturnOnConsecutiveCalls($msgA, $msgB);

        $exporter = $this->_exporterFixture($driver, [
            ['id' => 10, 'type' => Horde_ActiveSync::CHANGE_TYPE_CHANGE],
            ['id' => 11, 'type' => Horde_ActiveSync::CHANGE_TYPE_CHANGE],
        ]);

        $this->assertSame($msgA, $this->_getChangeMessage($exporter, ['id' => 10], 0));
        $this->assertSame($msgB, $this->_getChangeMessage($exporter, ['id' => 11], 1));
    }

    /**
     * Initial sync change lists contain bare uids; they must be prefetched
     * as CHANGE_TYPE_CHANGE entries.
     */
    public function testInitialSyncBareUidsArePrefetched()
    {
        $msgA = $this->createMock(Horde_ActiveSync_Message_Base::class);
        $msgB = $this->createMock(Horde_ActiveSync_Message_Base::class);

        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->expects($this->once())
            ->method('getMessagesBulk')
            ->with('INBOX', [10, 11])
            ->willReturn([10 => $msgA, 11 => $msgB]);
        $driver->expects($this->never())->method('getMessage');

        $exporter = $this->_exporterFixture($driver, [10, 11]);

        $this->assertSame($msgA, $this->_getChangeMessage($exporter, ['id' => 10], 0));
        $this->assertSame($msgB, $this->_getChangeMessage($exporter, ['id' => 11], 1));
    }

    /**
     * Build an exporter with stubbed dependencies, bypassing the
     * constructor (only the prefetch path is under test).
     */
    protected function _exporterFixture($driver, array $changes)
    {
        $reflection = new ReflectionClass(Horde_ActiveSync_Connector_Exporter_Sync::class);
        $exporter = $reflection->newInstanceWithoutConstructor();

        $as = $this->createMock(Horde_ActiveSync::class);
        $as->driver = $driver;

        $this->_setProperty($exporter, '_as', $as);
        $this->_setProperty(
            $exporter,
            '_logger',
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );
        $this->_setProperty($exporter, '_changes', $changes);
        $this->_setProperty($exporter, '_currentCollection', [
            'id' => 'F1',
            'serverid' => 'INBOX',
            'class' => Horde_ActiveSync::CLASS_EMAIL,
        ]);

        return $exporter;
    }

    protected function _getChangeMessage($exporter, array $change, $step)
    {
        $this->_setProperty($exporter, '_step', $step);
        $reflection = new ReflectionClass($exporter);
        $method = $reflection->getMethod('_getChangeMessage');
        $method->setAccessible(true);

        return $method->invoke($exporter, $change);
    }

    protected function _setProperty($object, $property, $value)
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
