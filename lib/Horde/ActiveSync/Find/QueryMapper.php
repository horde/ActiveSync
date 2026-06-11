<?php

/**
 * Horde_ActiveSync_Find_QueryMapper
 *
 * Maps EAS Find command parameters to Search command parameters so both
 * commands share the same IMAP/GAL backend.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 */
use Horde\Util\HordeString;

class Horde_ActiveSync_Find_QueryMapper
{
    /**
     * Convert Find parameters into the structure expected by
     * Horde_Core_ActiveSync_Driver::getSearchResults().
     *
     * @param Horde_ActiveSync_Find_Params $params  Parsed Find request.
     *
     * @return Horde_ActiveSync_Search_Params
     */
    public static function toSearchParams(
        Horde_ActiveSync_Find_Params $params
    ): Horde_ActiveSync_Search_Params {
        $type = HordeString::lower($params->type);

        if ($type === 'gal') {
            $text = $params->query['text']
                ?? $params->query['freetext']
                ?? '';

            return new Horde_ActiveSync_Search_Params(
                type: 'gal',
                query: [$text],
                options: $params->options,
                start: $params->start,
                limit: $params->limit,
                rebuildResults: false,
                deepTraversal: false,
            );
        }

        $criteria = [];
        if (!empty($params->query['class'])) {
            $criteria['FolderType'] = $params->query['class'];
        }
        if (!empty($params->query['serverid'])) {
            $criteria['serverid'] = $params->query['serverid'];
        }
        if (!empty($params->query['freetext'])) {
            $criteria[Horde_ActiveSync_Request_Search::SEARCH_FREETEXT]
                = $params->query['freetext'];
        }

        return new Horde_ActiveSync_Search_Params(
            type: 'mailbox',
            query: [
                [
                    'op' => Horde_ActiveSync_Request_Search::SEARCH_AND,
                    'value' => $criteria,
                ],
            ],
            options: $params->options,
            start: $params->start,
            limit: $params->limit,
            rebuildResults: false,
            deepTraversal: $params->deepTraversal,
        );
    }
}
