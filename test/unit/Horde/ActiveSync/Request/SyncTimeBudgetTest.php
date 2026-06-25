<?php

/**
 * Unit tests for ActiveSync Sync response time budgeting.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use Horde_ActiveSync_Connector_Exporter_Sync;
use Horde_ActiveSync_Driver_Base;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Request_Sync;
use Horde_ActiveSync_Wbxml_Encoder;
use Horde_Log_Handler_Null;
use Horde_Stream_Temp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Horde_ActiveSync_Request_Sync::class)]
class SyncTimeBudgetTest extends TestCase
{
    public function testGetSyncConfigReturnsConfiguredValues()
    {
        $driver = $this->_driverWithSyncConfig(['maxresponsetime' => 25]);

        $this->assertSame(['maxresponsetime' => 25], $driver->getSyncConfig());
    }

    public function testGetSyncConfigDefaultsToEmptyArray()
    {
        $driver = $this->_driverWithSyncConfig();

        $this->assertSame([], $driver->getSyncConfig());
    }

    public function testHasPendingChangesReflectsExporterStep()
    {
        $exporter = $this->_exporterWithoutConstructor();
        $ref = new ReflectionClass($exporter);

        $changesProp = $ref->getProperty('_changes');
        $changesProp->setAccessible(true);
        $changesProp->setValue($exporter, [
            ['id' => '1', 'type' => Horde_ActiveSync::CHANGE_TYPE_CHANGE],
            ['id' => '2', 'type' => Horde_ActiveSync::CHANGE_TYPE_CHANGE],
        ]);

        $stepProp = $ref->getProperty('_step');
        $stepProp->setAccessible(true);
        $stepProp->setValue($exporter, 0);

        $this->assertTrue($exporter->hasPendingChanges());

        $stepProp->setValue($exporter, 1);
        $this->assertTrue($exporter->hasPendingChanges());

        $stepProp->setValue($exporter, 2);
        $this->assertFalse($exporter->hasPendingChanges());
    }

    public function testUseSyncCommandsBufferWhenBudgetEnabledWithinWindow()
    {
        $sync = $this->_syncRequestWithoutConstructor();

        $this->assertTrue($this->_invokeSyncMethod(
            $sync,
            '_useSyncCommandsBuffer',
            [25, 10, 50, 0, 100]
        ));

        $this->assertFalse($this->_invokeSyncMethod(
            $sync,
            '_useSyncCommandsBuffer',
            [0, 10, 50, 0, 100]
        ));

        $this->assertFalse($this->_invokeSyncMethod(
            $sync,
            '_useSyncCommandsBuffer',
            [25, 60, 50, 0, 100]
        ));

        $this->assertFalse($this->_invokeSyncMethod(
            $sync,
            '_useSyncCommandsBuffer',
            [25, 5, 50, 98, 100]
        ));
    }

    public function testSyncTimeBudgetDisabledWhenZero()
    {
        $sync = $this->_syncRequestWithoutConstructor();

        $this->assertFalse($this->_invokeSyncMethod(
            $sync,
            '_isSyncTimeBudgetExceeded',
            [microtime(true) - 60, 0, 5]
        ));
    }

    public function testSyncTimeBudgetAllowsFirstChange()
    {
        $sync = $this->_syncRequestWithoutConstructor();

        $this->assertFalse($this->_invokeSyncMethod(
            $sync,
            '_isSyncTimeBudgetExceeded',
            [microtime(true) - 60, 25, 0]
        ));
    }

    public function testSyncTimeBudgetExceededAfterFirstChange()
    {
        $sync = $this->_syncRequestWithoutConstructor();

        $this->assertTrue($this->_invokeSyncMethod(
            $sync,
            '_isSyncTimeBudgetExceeded',
            [microtime(true) - 30, 25, 2]
        ));

        $this->assertFalse($this->_invokeSyncMethod(
            $sync,
            '_isSyncTimeBudgetExceeded',
            [microtime(true) - 10, 25, 2]
        ));
    }

    public function testBufferedCommandsAreAppendedAfterMoreAvailable()
    {
        $main = fopen('php://memory', 'wb+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($main);
        $encoder->setLogger(new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null()));
        $encoder->startWBXML();

        $buffer = new Horde_Stream_Temp();
        $savedMain = $encoder->swapOutputStream($buffer);
        $encoder->startTag(Horde_ActiveSync::SYNC_COMMANDS);
        $encoder->startTag(Horde_ActiveSync::SYNC_ADD);
        $encoder->startTag(Horde_ActiveSync::SYNC_SERVERENTRYID);
        $encoder->content('1');
        $encoder->endTag();
        $encoder->endTag();
        $encoder->endTag();
        $bufferLength = $buffer->length();
        $encoder->swapOutputStream($savedMain);

        $encoder->startTag(Horde_ActiveSync::SYNC_MOREAVAILABLE, false, true);
        $afterMoreAvailable = ftell($main);
        $encoder->appendOutputStream($buffer);

        rewind($main);
        $outputLength = strlen(stream_get_contents($main));

        $this->assertGreaterThan(0, $bufferLength);
        $this->assertGreaterThan($afterMoreAvailable, $outputLength);
    }

    protected function _driverWithSyncConfig(array $sync = [])
    {
        $driver = $this->getMockBuilder(Horde_ActiveSync_Driver_Base::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $params = (new ReflectionClass(Horde_ActiveSync_Driver_Base::class))
            ->getProperty('_params');
        $params->setAccessible(true);
        $params->setValue($driver, ['sync' => $sync]);

        return $driver;
    }

    protected function _syncRequestWithoutConstructor()
    {
        $ref = new ReflectionClass(Horde_ActiveSync_Request_Sync::class);
        return $ref->newInstanceWithoutConstructor();
    }

    protected function _exporterWithoutConstructor()
    {
        $ref = new ReflectionClass(Horde_ActiveSync_Connector_Exporter_Sync::class);
        return $ref->newInstanceWithoutConstructor();
    }

    protected function _invokeSyncMethod($object, $method, array $args = [])
    {
        $ref = new ReflectionClass($object);
        $method = $ref->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
