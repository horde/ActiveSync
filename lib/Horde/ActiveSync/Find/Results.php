<?php

/**
 * Horde_ActiveSync_Find_Results
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 */

/**
 * Results for an EAS Find command request.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Find_Results
{
    /**
     * @param int        $status       Top-level Find status.
     * @param int        $storeStatus  Store-level status.
     * @param int        $total        Total matching entries.
     * @param array|null $rows         Result rows or null on error.
     */
    public function __construct(
        public int $status,
        public int $storeStatus,
        public int $total,
        public ?array $rows,
    ) {}
}
