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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HealthEvaluator::class)]
#[CoversClass(HealthOptions::class)]
#[CoversClass(HealthStatus::class)]
#[CoversClass(SignalCode::class)]
#[CoversClass(HealthSignal::class)]
#[CoversClass(CollectionHealth::class)]
#[CoversClass(DeviceHealth::class)]
#[CoversClass(FleetSummary::class)]
class HealthEvaluatorTest extends TestCase
{
    private const NOW = 1_700_000_000;

    /**
     * @dataProvider folderSyncRequiredProvider
     */
    #[DataProvider('folderSyncRequiredProvider')]
    public function testFolderSyncRequiredBoundaries(
        int $count,
        array $expectedCodes
    ): void {
        $health = $this->evaluate(['foldersyncrequired' => $count]);

        $this->assertSame($expectedCodes, $health->signalCodes());
    }

    public static function folderSyncRequiredProvider(): array
    {
        return [
            'zero' => [0, []],
            'below warning' => [2, []],
            'warning' => [3, [SignalCode::FSR_WARN]],
            'critical' => [5, [SignalCode::FSR_CRITICAL]],
        ];
    }

    public function testHeartbeatBoundariesAndMissingEnd(): void
    {
        $atBoundary = $this->evaluate([
            'lasthbsyncstarted' => self::NOW - 960,
            'lastsyncendnormal' => self::NOW - 1_000,
        ]);
        $this->assertSame(
            [SignalCode::HB_IN_FLIGHT],
            $atBoundary->signalCodes()
        );
        $this->assertTrue($atBoundary->active);
        $this->assertSame(HealthStatus::OK, $atBoundary->status);

        $stuck = $this->evaluate([
            'lasthbsyncstarted' => self::NOW - 961,
            'lastsyncendnormal' => self::NOW - 1_000,
        ]);
        $this->assertSame([SignalCode::HB_STUCK], $stuck->signalCodes());
        $this->assertSame(HealthStatus::WARN, $stuck->status);
        $this->assertTrue($stuck->isStuck());

        $missingEnd = $this->evaluate([
            'lasthbsyncstarted' => self::NOW - 961,
            'lastsyncendnormal' => null,
        ]);
        $this->assertSame(
            [SignalCode::HB_MISSING_END],
            $missingEnd->signalCodes()
        );
        $this->assertSame(HealthStatus::WARN, $missingEnd->status);

        $stuckAtAbandonedBoundary = $this->evaluate([
            'lasthbsyncstarted' => self::NOW - 1_920,
            'lastsyncendnormal' => self::NOW - 2_000,
        ]);
        $this->assertSame(
            [SignalCode::HB_STUCK],
            $stuckAtAbandonedBoundary->signalCodes()
        );

        $abandoned = $this->evaluate([
            'cacheTimestamp' => self::NOW - 301,
            'lasthbsyncstarted' => self::NOW - 1_921,
            'lastsyncendnormal' => self::NOW - 2_000,
        ]);
        $this->assertSame(
            [SignalCode::HB_ABANDONED],
            $abandoned->signalCodes()
        );
        $this->assertSame(HealthStatus::OK, $abandoned->status);
        $this->assertFalse($abandoned->isStuck());
        $this->assertFalse($abandoned->active);

        $missingEndAtAbandonedBoundary = $this->evaluate([
            'lasthbsyncstarted' => self::NOW - 1_920,
            'lastsyncendnormal' => null,
        ]);
        $this->assertSame(
            [SignalCode::HB_MISSING_END],
            $missingEndAtAbandonedBoundary->signalCodes()
        );

        $abandonedMissingEnd = $this->evaluate([
            'cacheTimestamp' => self::NOW - 301,
            'lasthbsyncstarted' => self::NOW - 1_921,
            'lastsyncendnormal' => null,
        ]);
        $this->assertSame(
            [SignalCode::HB_ABANDONED],
            $abandonedMissingEnd->signalCodes()
        );
        $this->assertSame(HealthStatus::OK, $abandonedMissingEnd->status);
        $this->assertFalse($abandonedMissingEnd->isStuck());
        $this->assertFalse($abandonedMissingEnd->active);
    }

    public function testHeartbeatAbandonedThresholdOptions(): void
    {
        $defaults = new HealthOptions();
        $this->assertSame(1_320, $defaults->hbAbandonedAfterFor(600));

        $explicit = new HealthOptions(hbAbandonedAfter: 2_000);
        $this->assertSame(2_000, $explicit->hbAbandonedAfterFor(600));

        $this->assertSame(3600, $defaults->backlogAbandonedAfter);
    }

    public function testBlockedAndWipeSignals(): void
    {
        $pending = $this->evaluate([
            'blocked' => true,
            'rwstatus' => 2,
            'accountOnlyRwstatus' => 4,
        ]);
        $this->assertSame(
            [SignalCode::BLOCKED, SignalCode::WIPE_PENDING],
            $pending->signalCodes()
        );

        $complete = $this->evaluate([
            'rwstatus' => 3,
            'accountOnlyRwstatus' => 5,
        ]);
        $this->assertSame(
            [SignalCode::WIPE_COMPLETE],
            $complete->signalCodes()
        );
    }

    public function testBacklogBoundariesAndWorstAggregation(): void
    {
        $health = $this->evaluate([
            'blocked' => true,
            'collections' => [
                new CollectionFacts(
                    id: 'young',
                    backlog: self::NOW - 59,
                    backlogpings: 2
                ),
                new CollectionFacts(
                    id: 'pending',
                    backlog: self::NOW - 60,
                    backlogpings: 2
                ),
                new CollectionFacts(
                    id: 'stuck',
                    backlog: self::NOW,
                    backlogpings: 3
                ),
                new CollectionFacts(
                    id: 'abandoned',
                    backlog: self::NOW - 3600,
                    backlogpings: 3
                ),
            ],
        ]);

        $this->assertSame(HealthStatus::CRITICAL, $health->status);
        $this->assertSame(HealthStatus::OK, $health->collections[0]->status);
        $this->assertSame(HealthStatus::OK, $health->collections[3]->status);
        $this->assertSame([], $health->collections[3]->signals);
        $this->assertSame(
            [SignalCode::BACKLOG_PENDING],
            array_map(
                static fn (HealthSignal $signal): string => $signal->code,
                $health->collections[1]->signals
            )
        );
        $this->assertSame(
            [SignalCode::BLOCKED, SignalCode::BACKLOG_PENDING, SignalCode::BACKLOG_STUCK],
            $health->signalCodes()
        );
        $this->assertTrue($health->isStuck());
    }

    public function testActiveWindowEdgesAndLatestTimestamp(): void
    {
        $atBoundary = $this->evaluate([
            'cacheTimestamp' => self::NOW - 300,
            'lastSyncTs' => self::NOW - 400,
        ]);
        $this->assertTrue($atBoundary->active);
        $this->assertSame(300, $atBoundary->ageSeconds);

        $outside = $this->evaluate([
            'cacheTimestamp' => self::NOW - 301,
        ]);
        $this->assertFalse($outside->active);
        $this->assertSame(301, $outside->ageSeconds);

        $unknown = $this->evaluate();
        $this->assertNull($unknown->ageSeconds);
        $this->assertFalse($unknown->active);
    }

    public function testFleetSummaryCountsAndSerialization(): void
    {
        $ok = $this->evaluate(['cacheTimestamp' => self::NOW]);
        $warn = $this->evaluate(['blocked' => true]);
        $critical = $this->evaluate(['foldersyncrequired' => 5]);
        $pending = $this->evaluate(['rwstatus' => 2]);
        $summary = FleetSummary::fromDevices(
            [$ok, $warn, $critical, $pending],
            self::NOW
        );

        $this->assertSame(4, $summary->devices);
        $this->assertSame(1, $summary->active);
        $this->assertSame(1, $summary->ok);
        $this->assertSame(2, $summary->warn);
        $this->assertSame(1, $summary->critical);
        $this->assertSame(1, $summary->stuck);
        $this->assertSame(1, $summary->wipePending);
        $this->assertSame(1, $summary->blocked);
        $this->assertTrue($summary->hasCritical());
        $this->assertSame(self::NOW, $summary->toArray()['asOf']);

        $withPath = $warn->withLogPath('/logs/DEVICE1.txt');
        $this->assertNull($warn->logPath);
        $this->assertSame('/logs/DEVICE1.txt', $withPath->toArray()['logPath']);
    }

    public function testSignalCatalogAndStatusHelpers(): void
    {
        $this->assertCount(11, SignalCode::all());
        foreach (SignalCode::all() as $code) {
            $this->assertNotSame('', SignalCode::describe($code));
        }
        $this->assertTrue(HealthStatus::isValid(HealthStatus::WARN));
        $this->assertFalse(HealthStatus::isValid('unknown'));
        $this->assertSame(
            HealthStatus::CRITICAL,
            HealthStatus::worst(
                HealthStatus::OK,
                HealthStatus::CRITICAL,
                HealthStatus::WARN
            )
        );
    }

    private function evaluate(array $values = []): DeviceHealth
    {
        $facts = new DeviceFacts(
            user: 'alice@example.com',
            deviceId: 'DEVICE1',
            deviceType: 'phone',
            version: '16.1',
            rwstatus: $values['rwstatus'] ?? 0,
            accountOnlyRwstatus: $values['accountOnlyRwstatus'] ?? 0,
            blocked: $values['blocked'] ?? false,
            lastSyncTs: $values['lastSyncTs'] ?? null,
            cacheTimestamp: $values['cacheTimestamp'] ?? null,
            hbinterval: $values['hbinterval'] ?? 900,
            lasthbsyncstarted: $values['lasthbsyncstarted'] ?? null,
            lastsyncendnormal: $values['lastsyncendnormal'] ?? null,
            foldersyncrequired: $values['foldersyncrequired'] ?? 0,
            collections: $values['collections'] ?? []
        );

        return (new HealthEvaluator(
            new HealthOptions(now: self::NOW)
        ))->evaluate($facts);
    }
}
