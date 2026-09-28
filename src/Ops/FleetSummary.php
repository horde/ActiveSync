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

final class FleetSummary
{
    public function __construct(
        public readonly int $devices,
        public readonly int $active,
        public readonly int $ok,
        public readonly int $warn,
        public readonly int $critical,
        public readonly int $stuck,
        public readonly int $wipePending,
        public readonly int $blocked,
        public readonly int $asOf
    ) {
    }

    public static function fromDevices(iterable $deviceHealths, int $asOf): self
    {
        $counts = [
            'devices' => 0,
            'active' => 0,
            'ok' => 0,
            'warn' => 0,
            'critical' => 0,
            'stuck' => 0,
            'wipePending' => 0,
            'blocked' => 0,
        ];

        foreach ($deviceHealths as $device) {
            ++$counts['devices'];
            $counts['active'] += (int) $device->active;
            ++$counts[$device->status];
            $counts['stuck'] += (int) $device->isStuck();
            $codes = $device->signalCodes();
            $counts['wipePending'] += (int) in_array(
                SignalCode::WIPE_PENDING,
                $codes,
                true
            );
            $counts['blocked'] += (int) in_array(
                SignalCode::BLOCKED,
                $codes,
                true
            );
        }

        return new self(
            devices: $counts['devices'],
            active: $counts['active'],
            ok: $counts['ok'],
            warn: $counts['warn'],
            critical: $counts['critical'],
            stuck: $counts['stuck'],
            wipePending: $counts['wipePending'],
            blocked: $counts['blocked'],
            asOf: $asOf
        );
    }

    public function hasCritical(): bool
    {
        return $this->critical > 0;
    }

    public function toArray(): array
    {
        return [
            'devices' => $this->devices,
            'active' => $this->active,
            'ok' => $this->ok,
            'warn' => $this->warn,
            'critical' => $this->critical,
            'stuck' => $this->stuck,
            'wipePending' => $this->wipePending,
            'blocked' => $this->blocked,
            'asOf' => $this->asOf,
        ];
    }
}
