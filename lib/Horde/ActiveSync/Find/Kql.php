<?php

/**
 * Horde_ActiveSync_Find_Kql
 *
 * Keyword Query Language (KQL) parser for EAS Find mailbox searches.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 */

/**
 * Parse Keyword Query Language used by EAS Find requests into IMAP searches.
 *
 * Supports boolean operators (AND, OR, NOT), parentheses, implicit AND between
 * adjacent terms, and common Outlook/Exchange property restrictions.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 */
use Horde\Util\HordeString;

class Horde_ActiveSync_Find_Kql
{
    /**
     * Header fields searched by the participants: restriction.
     */
    protected const PARTICIPANT_HEADERS = ['from', 'to', 'cc', 'bcc'];

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

        $tokens = self::_tokenize($text);
        if (!$tokens) {
            return $query;
        }

        $parser = new self();
        $ast = $parser->_parseExpression($tokens);
        if ($ast === null) {
            return $query;
        }

        return self::_astToImap($ast);
    }

    /**
     * @param array<int, array{type: string, value: string}> $tokens
     *
     * @return array|null
     */
    protected function _parseExpression(array $tokens): ?array
    {
        $this->_tokens = $tokens;
        $this->_pos = 0;

        if (!$this->_tokens) {
            return null;
        }

        $expr = $this->_parseOr();
        if ($expr === null) {
            return null;
        }

        return $this->_pos < count($this->_tokens) ? null : $expr;
    }

    /** @var array<int, array{type: string, value: string}> */
    protected $_tokens = [];

    /** @var int */
    protected $_pos = 0;

    /**
     * @return array|null
     */
    protected function _parseOr(): ?array
    {
        $left = $this->_parseAnd();
        if ($left === null) {
            return null;
        }

        $nodes = [$left];
        while ($this->_match('OR')) {
            $right = $this->_parseAnd();
            if ($right === null) {
                return null;
            }
            $nodes[] = $right;
        }

        return count($nodes) === 1 ? $nodes[0] : ['op' => 'or', 'nodes' => $nodes];
    }

    /**
     * @return array|null
     */
    protected function _parseAnd(): ?array
    {
        $left = $this->_parseNot();
        if ($left === null) {
            return null;
        }

        $nodes = [$left];
        while (true) {
            if ($this->_match('AND')) {
                $right = $this->_parseNot();
                if ($right === null) {
                    return null;
                }
                $nodes[] = $right;
                continue;
            }

            if ($this->_isPrimaryStart()) {
                $right = $this->_parseNot();
                if ($right === null) {
                    return null;
                }
                $nodes[] = $right;
                continue;
            }

            break;
        }

        return count($nodes) === 1 ? $nodes[0] : ['op' => 'and', 'nodes' => $nodes];
    }

    /**
     * @return array|null
     */
    protected function _parseNot(): ?array
    {
        if ($this->_match('NOT')) {
            $child = $this->_parseNot();
            if ($child === null) {
                return null;
            }

            return ['op' => 'not', 'node' => $child];
        }

        return $this->_parsePrimary();
    }

    /**
     * @return array|null
     */
    protected function _parsePrimary(): ?array
    {
        if ($this->_match('LPAREN')) {
            $expr = $this->_parseOr();
            if ($expr === null || !$this->_match('RPAREN')) {
                return null;
            }

            return $expr;
        }

        $token = $this->_consume('ATOM');
        if ($token === null) {
            return null;
        }

        return ['op' => 'crit', 'value' => $token['value']];
    }

    protected function _isPrimaryStart(): bool
    {
        $token = $this->_peek();
        if ($token === null) {
            return false;
        }

        return in_array($token['type'], ['LPAREN', 'NOT', 'ATOM'], true);
    }

    /**
     * @return array{type: string, value: string}|null
     */
    protected function _peek(): ?array
    {
        return $this->_tokens[$this->_pos] ?? null;
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    protected function _match(string $type): bool
    {
        $token = $this->_peek();
        if ($token === null || $token['type'] !== $type) {
            return false;
        }

        $this->_pos++;

        return true;
    }

    /**
     * @param string $type
     *
     * @return array{type: string, value: string}|null
     */
    protected function _consume(string $type): ?array
    {
        $token = $this->_peek();
        if ($token === null || $token['type'] !== $type) {
            return null;
        }

        $this->_pos++;

        return $token;
    }

    /**
     * @param array $ast
     *
     * @return Horde_Imap_Client_Search_Query
     */
    protected static function _astToImap(array $ast): Horde_Imap_Client_Search_Query
    {
        switch ($ast['op']) {
            case 'crit':
                return self::_criterionToQuery($ast['value']);

            case 'and':
                $query = new Horde_Imap_Client_Search_Query();
                $query->charset('UTF-8', false);
                foreach ($ast['nodes'] as $node) {
                    $query->andSearch([self::_astToImap($node)]);
                }

                return $query;

            case 'or':
                $queries = [];
                foreach ($ast['nodes'] as $node) {
                    $queries[] = self::_astToImap($node);
                }
                $query = new Horde_Imap_Client_Search_Query();
                $query->charset('UTF-8', false);
                $query->orSearch($queries);

                return $query;

            case 'not':
                if ($ast['node']['op'] === 'crit') {
                    return self::_criterionToQuery($ast['node']['value'], true);
                }

                return self::_astToImap(self::_negateAst($ast['node']));
        }

        $query = new Horde_Imap_Client_Search_Query();
        $query->charset('UTF-8', false);

        return $query;
    }

    /**
     * @param array $ast
     *
     * @return array
     */
    protected static function _negateAst(array $ast): array
    {
        switch ($ast['op']) {
            case 'crit':
                return ['op' => 'not', 'node' => $ast];

            case 'not':
                return $ast['node'];

            case 'and':
                return [
                    'op' => 'or',
                    'nodes' => array_map([self::class, '_negateAst'], $ast['nodes']),
                ];

            case 'or':
                return [
                    'op' => 'and',
                    'nodes' => array_map([self::class, '_negateAst'], $ast['nodes']),
                ];
        }

        return $ast;
    }

    /**
     * @param string $text
     * @param bool   $not
     *
     * @return Horde_Imap_Client_Search_Query
     */
    protected static function _criterionToQuery(string $text, bool $not = false): Horde_Imap_Client_Search_Query
    {
        $text = trim($text);
        $query = new Horde_Imap_Client_Search_Query();
        $query->charset('UTF-8', false);

        if (preg_match(
            '/^(received|sent)\s*(>=|<=|>|<|:)\s*(.+)$/i',
            $text,
            $m
        )) {
            $date = self::_parseDate(trim($m[3], '"'));
            if ($date === null) {
                $query->text($text, false, $not);

                return $query;
            }

            $range = self::_dateRange($m[2]);
            $query->dateSearch($date, $range, $not);

            return $query;
        }

        if (preg_match('/^size\s*(>=|<=|>|<|:)\s*(\d+)$/i', $text, $m)) {
            $size = (int) $m[2];
            $larger = in_array($m[1], ['>', '>=', ':'], true);
            $query->size($size, $larger, $not);

            return $query;
        }

        if (preg_match('/^size\s*:\s*(\d+)\s*\.\.\s*(\d+)$/i', $text, $m)) {
            $min = new Horde_Imap_Client_Search_Query();
            $min->charset('UTF-8', false);
            $min->size((int) $m[1], true);
            $max = new Horde_Imap_Client_Search_Query();
            $max->charset('UTF-8', false);
            $max->size((int) $m[2], false);
            $outer = new Horde_Imap_Client_Search_Query();
            $outer->charset('UTF-8', false);
            $outer->andSearch([$min, $max]);
            if ($not) {
                return self::_astToImap(self::_negateAst([
                    'op' => 'and',
                    'nodes' => [
                        ['op' => 'crit', 'value' => 'size>=' . $m[1]],
                        ['op' => 'crit', 'value' => 'size<=' . $m[2]],
                    ],
                ]));
            }

            return $outer;
        }

        if (preg_match(
            '/^(from|to|cc|bcc|subject|body|participants|category|attachment|attachmentnames|importance|hasattachment|isread|isflagged)\s*:\s*(.+)$/i',
            $text,
            $m
        )) {
            $property = HordeString::lower($m[1]);
            $value = self::_parsePropertyValue($m[2]);

            return self::_propertyToQuery($property, $value, $not);
        }

        if (preg_match('/^"([^"]*)"$/', $text, $m)) {
            $query->text($m[1], false, $not);

            return $query;
        }

        $query->text($text, false, $not);

        return $query;
    }

    /**
     * @param string $property
     * @param string $value
     * @param bool   $not
     *
     * @return Horde_Imap_Client_Search_Query
     */
    protected static function _propertyToQuery(
        string $property,
        string $value,
        bool $not = false
    ): Horde_Imap_Client_Search_Query {
        $query = new Horde_Imap_Client_Search_Query();
        $query->charset('UTF-8', false);

        switch ($property) {
            case 'from':
            case 'to':
            case 'cc':
            case 'bcc':
            case 'subject':
                $query->headerText($property, $value, $not);

                return $query;

            case 'body':
                $query->text($value, true, $not);

                return $query;

            case 'participants':
                $parts = [];
                foreach (self::PARTICIPANT_HEADERS as $header) {
                    $part = new Horde_Imap_Client_Search_Query();
                    $part->charset('UTF-8', false);
                    $part->headerText($header, $value, $not);
                    $parts[] = $part;
                }
                if ($not) {
                    $query->andSearch($parts);
                } else {
                    $query->orSearch($parts);
                }

                return $query;

            case 'category':
                $flag = self::_categoryToImapFlag($value);
                if ($flag === '') {
                    $query->text($value, false, $not);

                    return $query;
                }
                $query->flag($flag, !$not);

                return $query;

            case 'attachment':
            case 'attachmentnames':
                $query->headerText('Content-Disposition', $value, $not);

                return $query;

            case 'importance':
                return self::_importanceToQuery($value, $not);

            case 'hasattachment':
                return self::_hasAttachmentToQuery($value, $not);

            case 'isread':
                $set = self::_parseBoolean($value);
                if ($not) {
                    $set = !$set;
                }
                $query->flag('SEEN', $set);

                return $query;

            case 'isflagged':
                $set = self::_parseBoolean($value);
                if ($not) {
                    $set = !$set;
                }
                $query->flag('FLAGGED', $set);

                return $query;
        }

        $query->text($property . ':' . $value, false, $not);

        return $query;
    }

    /**
     * @param string $value
     * @param bool   $not
     *
     * @return Horde_Imap_Client_Search_Query
     */
    protected static function _hasAttachmentToQuery(string $value, bool $not = false): Horde_Imap_Client_Search_Query
    {
        $has = self::_parseBoolean($value);
        if ($not) {
            $has = !$has;
        }

        $query = new Horde_Imap_Client_Search_Query();
        $query->charset('UTF-8', false);
        if ($has) {
            $query->headerText('Content-Type', 'multipart/', false);
        } else {
            $query->headerText('Content-Type', 'multipart/', true);
        }

        return $query;
    }

    /**
     * @param string $value
     * @param bool   $not
     *
     * @return Horde_Imap_Client_Search_Query
     */
    protected static function _importanceToQuery(string $value, bool $not = false): Horde_Imap_Client_Search_Query
    {
        $value = HordeString::lower($value);
        $queries = [];

        switch ($value) {
            case 'high':
                foreach (['Importance: high', 'X-Priority: 1', 'X-MSMail-Priority: High'] as $needle) {
                    [$header, $text] = explode(': ', $needle, 2);
                    $part = new Horde_Imap_Client_Search_Query();
                    $part->charset('UTF-8', false);
                    $part->headerText($header, $text, $not);
                    $queries[] = $part;
                }
                break;

            case 'low':
                foreach (['Importance: low', 'X-Priority: 5', 'X-MSMail-Priority: Low'] as $needle) {
                    [$header, $text] = explode(': ', $needle, 2);
                    $part = new Horde_Imap_Client_Search_Query();
                    $part->charset('UTF-8', false);
                    $part->headerText($header, $text, $not);
                    $queries[] = $part;
                }
                break;

            default:
                foreach (['Importance: normal', 'X-Priority: 3', 'X-MSMail-Priority: Normal'] as $needle) {
                    [$header, $text] = explode(': ', $needle, 2);
                    $part = new Horde_Imap_Client_Search_Query();
                    $part->charset('UTF-8', false);
                    $part->headerText($header, $text, $not);
                    $queries[] = $part;
                }
        }

        $query = new Horde_Imap_Client_Search_Query();
        $query->charset('UTF-8', false);
        $query->orSearch($queries);

        return $query;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected static function _parsePropertyValue(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^"([^"]*)"$/', $value, $m)) {
            return $m[1];
        }

        return $value;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected static function _parseBoolean(string $value): bool
    {
        return in_array(HordeString::lower(trim($value, '"')), ['1', 'true', 'yes'], true);
    }

    /**
     * @param string $category
     *
     * @return string
     */
    protected static function _categoryToImapFlag(string $category): string
    {
        $atom = new Horde_Imap_Client_Data_Format_Atom(
            strtr(Horde_String_Transliterate::toAscii($category), ' ', '_')
        );

        return HordeString::lower($atom->stripNonAtomCharacters());
    }

    /**
     * @param string $operator
     *
     * @return string
     */
    protected static function _dateRange(string $operator): string
    {
        switch ($operator) {
            case '>':
            case '>=':
                return Horde_Imap_Client_Search_Query::DATE_SINCE;

            case '<':
            case '<=':
                return Horde_Imap_Client_Search_Query::DATE_BEFORE;
        }

        return Horde_Imap_Client_Search_Query::DATE_ON;
    }

    /**
     * @param string $value
     *
     * @return Horde_Date|null
     */
    protected static function _parseDate(string $value): ?Horde_Date
    {
        $value = trim($value, '"');
        if ($value === '') {
            return null;
        }

        $lower = HordeString::lower($value);
        if ($lower === 'today') {
            return new Horde_Date('@' . strtotime('today UTC'), 'UTC');
        }
        if ($lower === 'yesterday') {
            return new Horde_Date('@' . strtotime('yesterday UTC'), 'UTC');
        }

        $formats = ['Y-m-d', 'Y/m/d', 'm/d/Y', 'n/j/Y'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value, new DateTimeZone('UTC'));
            if ($dt instanceof DateTime) {
                return new Horde_Date($dt->format('Y-m-d'), 'UTC');
            }
        }

        try {
            return new Horde_Date($value, 'UTC');
        } catch (Horde_Date_Exception $e) {
            return null;
        }
    }

    /**
     * @param string $text
     *
     * @return array<int, array{type: string, value: string}>
     */
    protected static function _tokenize(string $text): array
    {
        $tokens = [];
        $length = strlen($text);
        $offset = 0;

        while ($offset < $length) {
            if (ctype_space($text[$offset])) {
                $offset++;
                continue;
            }

            $remainder = substr($text, $offset);

            if ($remainder === '') {
                break;
            }

            if ($remainder[0] === '(') {
                $tokens[] = ['type' => 'LPAREN', 'value' => '('];
                $offset++;
                continue;
            }

            if ($remainder[0] === ')') {
                $tokens[] = ['type' => 'RPAREN', 'value' => ')'];
                $offset++;
                continue;
            }

            if ($remainder[0] === '"') {
                $end = strpos($remainder, '"', 1);
                if ($end === false) {
                    $tokens[] = ['type' => 'ATOM', 'value' => $remainder];
                    break;
                }
                $tokens[] = [
                    'type' => 'ATOM',
                    'value' => substr($remainder, 0, $end + 1),
                ];
                $offset += $end + 1;
                continue;
            }

            if (preg_match('/^(AND|OR|NOT)\b/i', $remainder, $m)) {
                $tokens[] = [
                    'type' => strtoupper($m[1]),
                    'value' => strtoupper($m[1]),
                ];
                $offset += strlen($m[0]);
                continue;
            }

            if (preg_match(
                '/^([a-z][a-z0-9]*)\s*(:|>=|<=|>|<)\s*("([^"]*)"|[^\s()]+)/i',
                $remainder,
                $m
            )) {
                $tokens[] = [
                    'type' => 'ATOM',
                    'value' => $m[1] . $m[2] . (isset($m[4]) && $m[3][0] === '"'
                        ? '"' . $m[4] . '"'
                        : $m[3]),
                ];
                $offset += strlen($m[0]);
                continue;
            }

            if (preg_match('/^([^\s()]+)/', $remainder, $m)) {
                $tokens[] = ['type' => 'ATOM', 'value' => $m[1]];
                $offset += strlen($m[0]);
                continue;
            }

            break;
        }

        return $tokens;
    }
}
