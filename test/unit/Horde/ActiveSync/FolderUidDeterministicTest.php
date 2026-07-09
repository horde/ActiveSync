<?php

/**
 * Unit tests for deterministic EAS folder UID generation.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project (http://www.horde.org/)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use Horde_ActiveSync_Driver_Base;
use Horde_ActiveSync_State_Base;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversNothing]
class FolderUidDeterministicTest extends TestCase
{
    public function testFolderUidIsDeterministicForRepeatedLookups()
    {
        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getFolderUidToBackendIdMap')->willReturn([]);

        $driver = $this->getMockBuilder(Horde_ActiveSync_Driver_Base::class)
            ->setConstructorArgs([['state' => $state]])
            ->getMockForAbstractClass();

        $method = new ReflectionMethod(Horde_ActiveSync_Driver_Base::class, '_getFolderUidForBackendId');
        $method->setAccessible(true);

        $inbox = $method->invoke($driver, 'INBOX', Horde_ActiveSync::FOLDER_TYPE_INBOX);
        $again = $method->invoke($driver, 'INBOX', Horde_ActiveSync::FOLDER_TYPE_INBOX);

        $this->assertSame(
            'F' . sprintf('%08x', crc32('F:' . 'INBOX') & 0xffffffff),
            $inbox
        );
    }

    public function testFolderUidIsDeterministicAcrossDriverInstances()
    {
        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getFolderUidToBackendIdMap')->willReturn([]);

        $method = new ReflectionMethod(Horde_ActiveSync_Driver_Base::class, '_getFolderUidForBackendId');
        $method->setAccessible(true);

        $driverA = $this->getMockBuilder(Horde_ActiveSync_Driver_Base::class)
            ->setConstructorArgs([['state' => $state]])
            ->getMockForAbstractClass();
        $driverB = $this->getMockBuilder(Horde_ActiveSync_Driver_Base::class)
            ->setConstructorArgs([['state' => $state]])
            ->getMockForAbstractClass();

        $contactBackendId = Horde_ActiveSync::CLASS_CONTACTS . ':42';
        $uidA = $method->invoke(
            $driverA,
            $contactBackendId,
            Horde_ActiveSync::FOLDER_TYPE_USER_CONTACT
        );
        $uidB = $method->invoke(
            $driverB,
            $contactBackendId,
            Horde_ActiveSync::FOLDER_TYPE_USER_CONTACT
        );

        $this->assertSame(
            'C' . sprintf('%08x', crc32('C:' . $contactBackendId) & 0xffffffff),
            $uidA
        );
    }

    public function testFolderUidUsesPersistedMapWhenPresent()
    {
        $state = $this->createMock(Horde_ActiveSync_State_Base::class);
        $state->method('getFolderUidToBackendIdMap')->willReturn([
            'INBOX' => 'F85953279',
        ]);

        $driver = $this->getMockBuilder(Horde_ActiveSync_Driver_Base::class)
            ->setConstructorArgs([['state' => $state]])
            ->getMockForAbstractClass();

        $method = new ReflectionMethod(Horde_ActiveSync_Driver_Base::class, '_getFolderUidForBackendId');
        $method->setAccessible(true);

        $this->assertSame(
            'F85953279',
            $method->invoke($driver, 'INBOX', Horde_ActiveSync::FOLDER_TYPE_INBOX)
        );
    }
}
