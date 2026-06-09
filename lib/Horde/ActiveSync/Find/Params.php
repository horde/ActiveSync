<?php

/**
 * Horde_ActiveSync_Find_Params
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 */

/**
 * Parameters for an EAS Find command request.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Find_Params
{
    /**
     * @param string      $type           One of 'mailbox' or 'gal'.
     * @param string|null $searchId       Client-provided search session id.
     * @param array       $query          Parsed query (freetext, class, collectionid).
     * @param array       $options        Options (picture, etc.).
     * @param int         $start          Result range start.
     * @param int         $limit          Maximum results to return.
     * @param bool        $deepTraversal  Search subfolders when true.
     */
    public function __construct(
        public string $type,
        public ?string $searchId,
        public array $query,
        public array $options,
        public int $start,
        public int $limit,
        public bool $deepTraversal,
    ) {}
}
