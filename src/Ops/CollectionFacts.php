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

final class CollectionFacts
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $class = null,
        public readonly ?string $serverid = null,
        public readonly ?string $lastsynckey = null,
        public readonly ?int $backlog = null,
        public readonly int $backlogpings = 0,
        public readonly bool $pingable = false
    ) {
    }
}
