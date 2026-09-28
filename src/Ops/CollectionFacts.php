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

/**
 * Health inputs copied from one persisted SyncCache collection row.
 *
 * The cache stores collections as an associative array keyed by collection
 * id. This snapshot keeps the fields the evaluator reads:
 *
 * - id: collection id, from the row key or the row's id value
 * - class: EAS collection class (`class`)
 * - serverid: backend folder id (`serverid`)
 * - lastsynckey: persisted sync key (`lastsynckey`; the in-request
 *   `synckey` alias is ignored)
 * - backlog: unix time when MOREAVAILABLE was recorded (`backlog`)
 * - backlogpings: recovery attempts (`backlogpings`)
 * - pingable: whether the collection is included in PING (`pingable`)
 *
 * Window size, filter type, truncation, body preferences, and conflict
 * policy stay on the cache row.
 */
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
