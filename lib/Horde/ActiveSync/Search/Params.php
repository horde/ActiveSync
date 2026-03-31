<?php

/**
 * Horde_ActiveSync_Search_Params
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Dmitry Petrov <dpetrov67@gmail.com>
 * @package   ActiveSync
 */

/**
 * Horde_ActiveSync_Search_Params class for Horde_ActiveSync.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Dmitry Petrov <dpetrov67@gmail.com>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Search_Params
{
    /**
     * Constructor.
     *
     * @param string $type            The search type; one of 'gal', 'mailbox',
     *                                or 'documentlibrary'.
     * @param array  $query           The search query.
     * @param array  $options         The search options.
     * @param int    $start           The start offset for results.
     * @param int    $limit           The maximum number of results to return.
     * @param bool   $rebuildResults  If true, invalidate any cached search;
     *                                otherwise use cached results if available.
     * @param bool   $deepTraversal   If true, traverse sub-folders.
     */
    public function __construct(
        public string $type,
        public array $query,
        public array $options,
        public int $start,
        public int $limit,
        public bool $rebuildResults,
        public bool $deepTraversal,
    ) {}
}
