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

final class CollectionHealth
{
    /**
     * @param HealthSignal[] $signals
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $class,
        public readonly ?string $serverid,
        public readonly ?string $lastsynckey,
        public readonly string $status,
        public readonly array $signals
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'class' => $this->class,
            'serverid' => $this->serverid,
            'lastsynckey' => $this->lastsynckey,
            'status' => $this->status,
            'signals' => array_map(
                static fn (HealthSignal $signal): array => $signal->toArray(),
                $this->signals
            ),
        ];
    }
}
