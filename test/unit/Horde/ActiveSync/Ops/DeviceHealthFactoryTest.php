<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Ops;

use Horde_ActiveSync_Device;
use Horde_ActiveSync_State_Sql;
use Horde_ActiveSync_SyncCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeviceHealthFactory::class)]
#[CoversClass(DeviceFacts::class)]
#[CoversClass(CollectionFacts::class)]
class DeviceHealthFactoryTest extends TestCase
{
    private const NOW = 1_700_000_000;

    public function testFromRowDecodesPropertiesAndFalseValues(): void
    {
        $factory = $this->factory();
        $health = $factory->fromRow(
            [
                'device_id' => 'DEVICE1',
                'device_type' => 'phone',
                'device_user' => 'alice@example.com',
                'device_rwstatus' => 0,
                'device_accountonly_rwstatus' => 0,
                'device_properties' => serialize([
                    'blocked' => true,
                    'version' => '16.1',
                ]),
            ],
            [
                'timestamp' => false,
                'hbinterval' => false,
                'wait' => 15,
                'lasthbsyncstarted' => false,
                'lastsyncendnormal' => false,
                'foldersyncrequired' => 3,
                'collections' => [
                    'collection-1' => [
                        'class' => 'Email',
                        'serverid' => 'INBOX',
                        'lastsynckey' => false,
                        'backlog' => self::NOW - 60,
                        'backlogpings' => 1,
                        'pingable' => true,
                    ],
                ],
            ]
        );

        $this->assertSame('16.1', $health->version);
        $this->assertSame(900, $health->hbinterval);
        $this->assertNull($health->ageSeconds);
        $this->assertSame(
            [SignalCode::FSR_WARN, SignalCode::BLOCKED, SignalCode::BACKLOG_PENDING],
            $health->signalCodes()
        );
        $this->assertSame('collection-1', $health->collections[0]->id);
        $this->assertNull($health->collections[0]->lastsynckey);
    }

    public function testFromRowAcceptsArrayProperties(): void
    {
        $health = $this->factory()->fromRow(
            [
                'device_id' => 'DEVICE1',
                'device_type' => 'phone',
                'device_user' => 'alice@example.com',
                'device_rwstatus' => 0,
                'device_accountonly_rwstatus' => 0,
                'device_properties' => ['version' => '14.1'],
            ],
            ['collections' => []],
            self::NOW
        );

        $this->assertSame('14.1', $health->version);
        $this->assertTrue($health->active);
    }

    public function testFromDeviceUsesLegacyObjectsAndIncludesKeylessCollections(): void
    {
        $state = $this->createMock(Horde_ActiveSync_State_Sql::class);
        $state->method('getSyncCache')->willReturn([
            'timestamp' => self::NOW - 10,
            'hbinterval' => 900,
            'wait' => false,
            'lasthbsyncstarted' => false,
            'lastsyncendnormal' => false,
            'foldersyncrequired' => 0,
            'collections' => [
                'collection-1' => [
                    'class' => 'Email',
                    'serverid' => 'INBOX',
                    'lastsynckey' => false,
                    'backlog' => self::NOW - 60,
                    'backlogpings' => 3,
                    'pingable' => true,
                ],
            ],
        ]);
        $device = new Horde_ActiveSync_Device($state, [
            'id' => 'DEVICE1',
            'user' => 'alice@example.com',
            'deviceType' => 'phone',
            'rwstatus' => 0,
            'accountOnlyRwstatus' => 0,
            'properties' => [
                'blocked' => false,
                'version' => '16.1',
            ],
        ]);
        $cache = new Horde_ActiveSync_SyncCache(
            $state,
            'DEVICE1',
            'alice@example.com'
        );

        $health = $this->factory()->fromDevice(
            $device,
            $cache,
            self::NOW - 20
        );

        $this->assertSame('DEVICE1', $health->deviceId);
        $this->assertSame('16.1', $health->version);
        $this->assertSame(10, $health->ageSeconds);
        $this->assertTrue($health->active);
        $this->assertSame(
            [SignalCode::BACKLOG_STUCK],
            $health->signalCodes()
        );
        $this->assertNull($health->collections[0]->lastsynckey);
    }

    private function factory(): DeviceHealthFactory
    {
        return new DeviceHealthFactory(new HealthEvaluator(
            new HealthOptions(now: self::NOW)
        ));
    }
}
