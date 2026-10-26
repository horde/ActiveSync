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
 * Unit tests for the per-item status a backend may attach to a failed
 * changeMessage(): rejected client data must surface as STATUS_INVALID so
 * the client stops resending the item, while a plain false stays a
 * retryable STATUS_SERVERERROR.
 */
class Horde_ActiveSync_Connector_ImporterChangeStatusTest extends TestCase
{
    public function testBackendErrorStatusIsReportedForModify(): void
    {
        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('changeMessage')
            ->willReturn(['error' => Horde_ActiveSync_Request_Sync::STATUS_INVALID]);

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('isConflict')->willReturn(false);
        $state->expects($this->never())->method('updateState');

        $result = $this->_importer($state, $driver)->importMessageChange(
            'contact-1',
            new Horde_ActiveSync_Message_Contact(),
            $this->_device(),
            false,
            Horde_ActiveSync::CLASS_CONTACTS
        );

        $this->assertSame('contact-1', $result[0]);
        $this->assertSame(Horde_ActiveSync_Request_Sync::STATUS_INVALID, $result['error']);
    }

    public function testBackendErrorStatusIsReportedForAdd(): void
    {
        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('changeMessage')
            ->willReturn(['error' => Horde_ActiveSync_Request_Sync::STATUS_INVALID]);

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('isDuplicatePIMAddition')->willReturn(false);
        $state->expects($this->never())->method('updateState');

        $result = $this->_importer($state, $driver)->importMessageChange(
            false,
            new Horde_ActiveSync_Message_Contact(),
            $this->_device(),
            'client-1',
            Horde_ActiveSync::CLASS_CONTACTS
        );

        $this->assertFalse($result[0]);
        $this->assertSame(Horde_ActiveSync_Request_Sync::STATUS_INVALID, $result['error']);
    }

    public function testPlainFalseStaysServerError(): void
    {
        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('changeMessage')->willReturn(false);

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('isConflict')->willReturn(false);

        $result = $this->_importer($state, $driver)->importMessageChange(
            'contact-1',
            new Horde_ActiveSync_Message_Contact(),
            $this->_device(),
            false,
            Horde_ActiveSync::CLASS_CONTACTS
        );

        $this->assertSame('contact-1', $result[0]);
        $this->assertSame(Horde_ActiveSync_Request_Sync::STATUS_SERVERERROR, $result['error']);
    }

    public function testSuccessfulChangeIsUnaffected(): void
    {
        $driver = $this->createMock(Horde_ActiveSync_Driver_Base::class);
        $driver->method('changeMessage')
            ->willReturn(['id' => 'contact-1', 'mod' => 5, 'flags' => 1]);

        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('isConflict')->willReturn(false);
        $state->expects($this->once())->method('updateState');

        $result = $this->_importer($state, $driver)->importMessageChange(
            'contact-1',
            new Horde_ActiveSync_Message_Contact(),
            $this->_device(),
            false,
            Horde_ActiveSync::CLASS_CONTACTS
        );

        $this->assertSame('contact-1', $result['id']);
        $this->assertArrayNotHasKey('error', $result);
    }

    protected function _device(): Horde_ActiveSync_Device
    {
        $device = $this->createMock(Horde_ActiveSync_Device::class);
        $device->supported = [];
        return $device;
    }

    protected function _importer($state, $driver): Horde_ActiveSync_Connector_Importer
    {
        $collections = $this->createMock(Horde_ActiveSync_Collections::class);
        $collections->method('getBackendIdForFolderUid')->willReturn('@Contacts@');

        $as = $this->createMock(Horde_ActiveSync::class);
        $as->driver = $driver;
        $as->method('getCollectionsObject')->willReturn($collections);

        $importer = new Horde_ActiveSync_Connector_Importer($as);
        $importer->setLogger(new Horde_Log_Logger(new Horde_Log_Handler_Null()));
        $importer->init($state, 'folder-uid', 0);

        return $importer;
    }
}
