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

final class HealthOptions
{
    public function __construct(
        private readonly ?int $now = null,
        public readonly int $activeWithin = 300,
        public readonly ?int $hbStuckAfter = null,
        public readonly int $hbDefaultInterval = 900,
        public readonly int $hbSlack = 60,
        public readonly ?int $hbAbandonedAfter = null,
        public readonly int $fsrWarnAt = 3,
        public readonly int $fsrCriticalAt = 5,
        public readonly int $backlogGrace = 60,
        public readonly int $backlogTriggerMax = 3,
        public readonly int $backlogAbandonedAfter = 3600
    ) {
    }

    public function now(): int
    {
        return $this->now ?? time();
    }

    public function hbStuckAfterFor(?int $hbinterval): int
    {
        return $this->hbStuckAfter
            ?? (($hbinterval ?: $this->hbDefaultInterval) + $this->hbSlack);
    }

    public function hbAbandonedAfterFor(?int $hbinterval): int
    {
        return $this->hbAbandonedAfter
            ?? (2 * $this->hbStuckAfterFor($hbinterval));
    }
}
