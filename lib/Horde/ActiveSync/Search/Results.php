<?php

/**
 * Horde_ActiveSync_Search_Results
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Dmitry Petrov <dpetrov67@gmail.com>
 * @package   ActiveSync
 */

/**
 * Horde_ActiveSync_Search_Results class for Horde_ActiveSync.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Dmitry Petrov <dpetrov67@gmail.com>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Search_Results
{
    /**
     * Constructor.
     *
     * @param int        $total   The total number of results available.
     * @param array|null $rows    The result rows, or null if error.
     * @param int        $status  The search status code.
     */
    public function __construct(
        public int    $total,
        public ?array $rows,
        public int    $status,
    ) {}
}
