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

use Horde_ActiveSync;

final class HealthEvaluator
{
    private readonly HealthOptions $options;

    public function __construct(?HealthOptions $options = null)
    {
        $this->options = $options ?? new HealthOptions();
    }

    public function evaluate(DeviceFacts $facts): DeviceHealth
    {
        $now = $this->options->now();
        $stuckAfter = $this->options->hbStuckAfterFor($facts->hbinterval);
        $abandonedAfter = $this->options->hbAbandonedAfterFor(
            $facts->hbinterval
        );
        $signals = [];
        $started = $facts->lasthbsyncstarted ?: null;
        $ended = $facts->lastsyncendnormal ?: null;
        $heartbeatInFlight = false;

        if ($started !== null && ($ended === null || $started > $ended)) {
            $heartbeatAge = $now - $started;
            if ($heartbeatAge <= $stuckAfter) {
                $signals[] = $this->signal(
                    SignalCode::HB_IN_FLIGHT,
                    HealthStatus::OK
                );
                $heartbeatInFlight = true;
            } elseif ($heartbeatAge <= $abandonedAfter && $ended === null) {
                $signals[] = $this->signal(
                    SignalCode::HB_MISSING_END,
                    HealthStatus::WARN
                );
            } elseif ($heartbeatAge <= $abandonedAfter) {
                $signals[] = $this->signal(
                    SignalCode::HB_STUCK,
                    HealthStatus::WARN
                );
            } else {
                $signals[] = $this->signal(
                    SignalCode::HB_ABANDONED,
                    HealthStatus::OK
                );
            }
        }

        if ($facts->foldersyncrequired >= $this->options->fsrCriticalAt) {
            $signals[] = $this->signal(
                SignalCode::FSR_CRITICAL,
                HealthStatus::CRITICAL
            );
        } elseif ($facts->foldersyncrequired >= $this->options->fsrWarnAt) {
            $signals[] = $this->signal(
                SignalCode::FSR_WARN,
                HealthStatus::WARN
            );
        }

        if ($facts->blocked) {
            $signals[] = $this->signal(SignalCode::BLOCKED, HealthStatus::WARN);
        }

        $pending = [
            Horde_ActiveSync::RWSTATUS_PENDING,
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING,
        ];
        $wiped = [
            Horde_ActiveSync::RWSTATUS_WIPED,
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_WIPED,
        ];
        if (in_array($facts->rwstatus, $pending, true)
            || $facts->accountOnlyRwstatus === Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING) {
            $signals[] = $this->signal(
                SignalCode::WIPE_PENDING,
                HealthStatus::WARN
            );
        }
        if (in_array($facts->rwstatus, $wiped, true)
            || $facts->accountOnlyRwstatus === Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_WIPED) {
            $signals[] = $this->signal(
                SignalCode::WIPE_COMPLETE,
                HealthStatus::WARN
            );
        }

        $collections = array_map(
            fn (CollectionFacts $collection): CollectionHealth =>
                $this->evaluateCollection($collection, $now),
            $facts->collections
        );

        $activityTimestamps = array_filter(
            [$facts->cacheTimestamp, $facts->lastSyncTs],
            static fn (?int $timestamp): bool => $timestamp !== null
        );
        $ageSeconds = $activityTimestamps === []
            ? null
            : $now - max($activityTimestamps);
        $active = $heartbeatInFlight
            || ($ageSeconds !== null
                && $ageSeconds <= $this->options->activeWithin);

        $statuses = array_map(
            static fn (HealthSignal $signal): string => $signal->severity,
            $signals
        );
        foreach ($collections as $collection) {
            $statuses[] = $collection->status;
        }

        return new DeviceHealth(
            user: $facts->user,
            deviceId: $facts->deviceId,
            deviceType: $facts->deviceType,
            version: $facts->version,
            status: HealthStatus::worst(...$statuses),
            active: $active,
            ageSeconds: $ageSeconds,
            hbinterval: $facts->hbinterval,
            foldersyncrequired: $facts->foldersyncrequired,
            signals: $signals,
            collections: $collections
        );
    }

    private function evaluateCollection(
        CollectionFacts $facts,
        int $now
    ): CollectionHealth {
        $signals = [];
        if ($facts->backlog !== null
            && $facts->backlogpings >= $this->options->backlogTriggerMax
            && ($now - $facts->backlog) < $this->options->backlogAbandonedAfter) {
            $signals[] = $this->signal(
                SignalCode::BACKLOG_STUCK,
                HealthStatus::CRITICAL,
                $facts->id
            );
        } elseif ($facts->backlog !== null
            && ($now - $facts->backlog) >= $this->options->backlogGrace
            && $facts->backlogpings < $this->options->backlogTriggerMax) {
            $signals[] = $this->signal(
                SignalCode::BACKLOG_PENDING,
                HealthStatus::WARN,
                $facts->id
            );
        }

        $statuses = array_map(
            static fn (HealthSignal $signal): string => $signal->severity,
            $signals
        );

        return new CollectionHealth(
            id: $facts->id,
            class: $facts->class,
            serverid: $facts->serverid,
            lastsynckey: $facts->lastsynckey,
            status: HealthStatus::worst(...$statuses),
            signals: $signals
        );
    }

    private function signal(
        string $code,
        string $severity,
        ?string $collectionId = null
    ): HealthSignal {
        return new HealthSignal(
            code: $code,
            severity: $severity,
            detail: SignalCode::describe($code),
            collectionId: $collectionId
        );
    }
}
