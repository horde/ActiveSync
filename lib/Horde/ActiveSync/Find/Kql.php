<?php

/**
 * Horde_ActiveSync_Find_Kql
 *
 * Minimal KQL parser for EAS 16.0 Find mailbox searches.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 */

/**
 * Parse a subset of Keyword Query Language used by EAS Find requests.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Find_Kql
{
    /**
     * Parse KQL / free-text into an IMAP search query.
     *
     * @param string $text  Raw query string from the client.
     *
     * @return Horde_Imap_Client_Search_Query
     */
    public static function toImapQuery(string $text): Horde_Imap_Client_Search_Query
    {
        $text = trim($text);
        $query = new Horde_Imap_Client_Search_Query();
        $query->charset('UTF-8', false);

        if ($text === '') {
            return $query;
        }

        if (preg_match('/\s+OR\s+/i', $text)) {
            $parts = preg_split('/\s+OR\s+/i', $text);
            $queries = [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $queries[] = self::_parseClause($part);
                }
            }
            if (count($queries) > 1) {
                $query->orSearch($queries);

                return $query;
            }
            if (count($queries) === 1) {
                return $queries[0];
            }
        }

        return self::_parseClause($text);
    }

    /**
     * Parse a single KQL clause (no OR).
     *
     * @param string $text  One clause from a Find FreeText string.
     *
     * @return Horde_Imap_Client_Search_Query
     */
    protected static function _parseClause(string $text): Horde_Imap_Client_Search_Query
    {
        $text = trim($text);
        $query = new Horde_Imap_Client_Search_Query();
        $query->charset('UTF-8', false);

        if (preg_match('/^\s*(from|to|cc|bcc|subject)\s*:\s*("([^"]+)"|(\S+))\s*$/i', $text, $m)) {
            $value = !empty($m[3]) ? $m[3] : $m[4];
            $query->headerText(Horde_String::lower($m[1]), $value, false);

            return $query;
        }

        if (preg_match('/^"([^"]+)"$/', $text, $m)) {
            $query->text($m[1], false);

            return $query;
        }

        if (preg_match('/^hasattachment:\s*(yes|true|1)$/i', $text)) {
            $query->text('multipart', false);

            return $query;
        }

        if (preg_match('/^received\s*(>=|<=|>|<|:)\s*(\d{4}-\d{2}-\d{2})/i', $text, $m)) {
            $date = new Horde_Date($m[2], 'UTC');
            $op = $m[1];
            if ($op === '>=' || $op === '>') {
                $query->dateSearch($date, Horde_Imap_Client_Search_Query::DATE_SINCE);
            } elseif ($op === '<=' || $op === '<') {
                $query->dateSearch($date, Horde_Imap_Client_Search_Query::DATE_BEFORE);
            } else {
                $query->dateSearch($date, Horde_Imap_Client_Search_Query::DATE_ON);
            }

            return $query;
        }

        $query->text($text, false);

        return $query;
    }
}
