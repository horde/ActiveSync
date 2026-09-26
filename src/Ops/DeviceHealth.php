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

final class DeviceHealth
{
    /**
     * @param HealthSignal[]     $signals
     * @param CollectionHealth[] $collections
     */
    public function __construct(
        public readonly string $user,
        public readonly string $deviceId,
        public readonly string $deviceType,
        public readonly ?string $version,
        public readonly string $status,
        public readonly bool $active,
        public readonly ?int $ageSeconds,
        public readonly ?int $hbinterval,
        public readonly int $foldersyncrequired,
        public readonly array $signals,
        public readonly array $collections,
        public ?string $logPath = null
    ) {
    }

    public function withLogPath(?string $logPath): self
    {
        $clone = clone $this;
        $clone->logPath = $logPath;

        return $clone;
    }

    public function isStuck(): bool
    {
        return array_intersect($this->signalCodes(), [
            SignalCode::HB_STUCK,
            SignalCode::HB_MISSING_END,
            SignalCode::FSR_CRITICAL,
            SignalCode::BACKLOG_STUCK,
        ]) !== [];
    }

    public function signalCodes(): array
    {
        $codes = array_map(
            static fn (HealthSignal $signal): string => $signal->code,
            $this->signals
        );
        foreach ($this->collections as $collection) {
            foreach ($collection->signals as $signal) {
                $codes[] = $signal->code;
            }
        }

        return array_values(array_unique($codes));
    }

    public function toArray(): array
    {
        return [
            'user' => $this->user,
            'deviceId' => $this->deviceId,
            'deviceType' => $this->deviceType,
            'version' => $this->version,
            'status' => $this->status,
            'active' => $this->active,
            'ageSeconds' => $this->ageSeconds,
            'hbinterval' => $this->hbinterval,
            'foldersyncrequired' => $this->foldersyncrequired,
            'signals' => array_map(
                static fn (HealthSignal $signal): array => $signal->toArray(),
                $this->signals
            ),
            'collections' => array_map(
                static fn (CollectionHealth $collection): array => $collection->toArray(),
                $this->collections
            ),
            'logPath' => $this->logPath,
        ];
    }
}
