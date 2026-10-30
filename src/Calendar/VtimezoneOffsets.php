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

namespace Horde\ActiveSync\Calendar;

use Horde\Icalendar\Calendar\Vtimezone;
use Horde\Icalendar\Component;

/**
 * Evaluates the UTC offset a VTIMEZONE defines for a local wall-clock time.
 *
 * Mail clients such as Outlook describe a zone with a vendor TZID (for
 * example "W. Europe Standard Time") and embed the rules in a VTIMEZONE.
 * The TZID alone cannot be resolved, so the embedded STANDARD and DAYLIGHT
 * observances are the authority.
 *
 * Only the rule forms real VTIMEZONE data uses are evaluated:
 *
 * - an observance without RRULE (a single transition at its DTSTART),
 * - RDATE transitions,
 * - FREQ=YEARLY with BYMONTH and either BYDAY=nXX (the n-th or last
 *   weekday of the month) or BYDAY=XX with a BYMONTHDAY list (the first such
 *   weekday among those days). INTERVAL=1, WKST, UNTIL and a single
 *   BYHOUR/BYMINUTE/BYSECOND are accepted.
 *
 * Any other rule makes the whole VTIMEZONE unevaluable. In that case
 * offsetAt() returns null instead of an approximation, so the caller can
 * fall back deliberately rather than shift a meeting by an hour.
 */
final class VtimezoneOffsets
{
    private const WEEKDAYS = [
        'SU' => 0,
        'MO' => 1,
        'TU' => 2,
        'WE' => 3,
        'TH' => 4,
        'FR' => 5,
        'SA' => 6,
    ];

    private const SUPPORTED_RULE_PARTS = [
        'FREQ',
        'INTERVAL',
        'WKST',
        'BYMONTH',
        'BYDAY',
        'BYMONTHDAY',
        'BYHOUR',
        'BYMINUTE',
        'BYSECOND',
        'UNTIL',
    ];

    /**
     * Return the UTC offset in effect for a local wall-clock time.
     *
     * @param Vtimezone $vtimezone  The VTIMEZONE to evaluate.
     * @param int       $wallTime   The wall-clock reading expressed as if it
     *                              were UTC (the result of gmmktime()).
     *
     * @return int|null  Seconds east of UTC (3600 for +01:00), or null when
     *                   the VTIMEZONE cannot be evaluated exactly.
     */
    public function offsetAt(Vtimezone $vtimezone, int $wallTime): ?int
    {
        $year = (int) gmdate('Y', $wallTime);

        $transitions = [];
        $observances = array_merge(
            $vtimezone->getChildrenByType('STANDARD'),
            $vtimezone->getChildrenByType('DAYLIGHT')
        );
        foreach ($observances as $observance) {
            $found = $this->transitions($observance, $year);
            if ($found === null) {
                return null;
            }
            array_push($transitions, ...$found);
        }

        if ($transitions === []) {
            return null;
        }

        usort(
            $transitions,
            static fn(array $a, array $b): int => $a['onset'] <=> $b['onset']
        );

        // Before the first known transition the zone is in the state the
        // first transition leaves.
        $offset = $transitions[0]['from'];
        foreach ($transitions as $transition) {
            if ($transition['onset'] > $wallTime) {
                break;
            }
            $offset = $transition['to'];
        }

        return $offset;
    }

    /**
     * Collect the transitions of one observance that can affect $year.
     *
     * The onset is the wall-clock time at which the observance begins,
     * expressed in the offset in effect before it (TZOFFSETFROM), as
     * RFC 5545 defines it.
     *
     * @return list<array{onset: int, from: int, to: int}>|null
     *         Null when the observance uses an unsupported construct.
     */
    private function transitions(Component $observance, int $year): ?array
    {
        $properties = $observance->getProperties();

        $from = $this->parseOffset($properties->getValue('TZOFFSETFROM'));
        $to = $this->parseOffset($properties->getValue('TZOFFSETTO'));
        $rawDtstart = $properties->getValue('DTSTART');
        if ($rawDtstart === null) {
            // RFC 5545 requires DTSTART; an observance without one does not
            // say when it applies, so it cannot contribute a transition.
            return [];
        }
        $dtstart = $this->parseWallTime($rawDtstart);
        if ($from === null || $to === null || $dtstart === null) {
            return null;
        }

        $onsets = [];

        foreach ($properties->getAllValues('RDATE') as $rdates) {
            foreach (explode(',', $rdates) as $rdate) {
                $onset = $this->parseWallTime(trim($rdate));
                if ($onset === null) {
                    return null;
                }
                $onsets[] = $onset;
            }
        }

        $rrule = $properties->getValue('RRULE');
        if ($rrule === null) {
            // Without a rule DTSTART is the (only) transition.
            $onsets[] = $dtstart;
        } else {
            $rule = $this->parseYearlyRule($rrule, $dtstart);
            if ($rule === null) {
                return null;
            }
            $startYear = (int) gmdate('Y', $dtstart);
            $candidateYears = [$year - 1, $year];
            if ($rule['until'] !== null) {
                // A rule that ended before the window still decided the
                // offset that applies afterwards (e.g. a zone that gave up
                // DST), so include its last occurrences.
                $untilYear = (int) gmdate('Y', $rule['until']);
                if ($untilYear < $year - 1) {
                    array_unshift($candidateYears, $untilYear - 1, $untilYear);
                }
            }
            foreach ($candidateYears as $candidateYear) {
                if ($candidateYear < $startYear) {
                    continue;
                }
                $day = $this->dayOfMonth($candidateYear, $rule);
                if ($day === null) {
                    continue;
                }
                $onset = gmmktime(0, 0, 0, $rule['month'], $day, $candidateYear) + $rule['timeOfDay'];
                if ($onset < $dtstart) {
                    continue;
                }
                if ($rule['until'] !== null
                    && $onset - ($rule['untilIsUtc'] ? $from : 0) > $rule['until']) {
                    continue;
                }
                $onsets[] = $onset;
            }
        }

        return array_map(
            static fn(int $onset): array => ['onset' => $onset, 'from' => $from, 'to' => $to],
            $onsets
        );
    }

    /**
     * Parse the supported yearly rule.
     *
     * @return array{
     *     month: int,
     *     weekday: int,
     *     ordinal: int|null,
     *     monthdays: list<int>,
     *     timeOfDay: int,
     *     until: int|null,
     *     untilIsUtc: bool
     * }|null
     */
    private function parseYearlyRule(string $rrule, int $dtstart): ?array
    {
        $parts = [];
        foreach (explode(';', $rrule) as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) !== 2) {
                return null;
            }
            $parts[strtoupper($pair[0])] = $pair[1];
        }

        if (($parts['FREQ'] ?? '') !== 'YEARLY'
            || (isset($parts['INTERVAL']) && $parts['INTERVAL'] !== '1')
            || array_diff(array_keys($parts), self::SUPPORTED_RULE_PARTS) !== []) {
            return null;
        }

        if (!isset($parts['BYMONTH'], $parts['BYDAY'])
            || !preg_match('/^\d{1,2}$/', $parts['BYMONTH'])
            || !preg_match('/^([+-]?\d)?(SU|MO|TU|WE|TH|FR|SA)$/i', $parts['BYDAY'], $day)) {
            return null;
        }
        $month = (int) $parts['BYMONTH'];
        if ($month < 1 || $month > 12) {
            return null;
        }

        $ordinal = $day[1] === '' ? null : (int) $day[1];
        $monthdays = [];
        if (isset($parts['BYMONTHDAY'])) {
            foreach (explode(',', $parts['BYMONTHDAY']) as $monthday) {
                if (!preg_match('/^\d{1,2}$/', $monthday) || (int) $monthday < 1 || (int) $monthday > 31) {
                    return null;
                }
                $monthdays[] = (int) $monthday;
            }
            sort($monthdays);
        } elseif ($ordinal === null || $ordinal === 0) {
            // A bare weekday would match every such day of the month.
            return null;
        }

        // Time of day comes from DTSTART unless BYHOUR/BYMINUTE/BYSECOND
        // restate it. DTSTART is often in year 1601, a negative timestamp,
        // so use a floor modulo.
        $seconds = (($dtstart % 86400) + 86400) % 86400;
        $time = [
            'BYHOUR' => intdiv($seconds, 3600),
            'BYMINUTE' => intdiv($seconds % 3600, 60),
            'BYSECOND' => $seconds % 60,
        ];
        foreach ($time as $name => $default) {
            if (isset($parts[$name])) {
                if (!preg_match('/^\d{1,2}$/', $parts[$name])) {
                    return null;
                }
                $time[$name] = (int) $parts[$name];
            }
        }

        $until = null;
        $untilIsUtc = false;
        if (isset($parts['UNTIL'])) {
            $until = $this->parseUntil($parts['UNTIL']);
            if ($until === null) {
                return null;
            }
            $untilIsUtc = str_ends_with(strtoupper($parts['UNTIL']), 'Z');
        }

        return [
            'month' => $month,
            'weekday' => self::WEEKDAYS[strtoupper($day[2])],
            'ordinal' => $ordinal,
            'monthdays' => $monthdays,
            'timeOfDay' => $time['BYHOUR'] * 3600 + $time['BYMINUTE'] * 60 + $time['BYSECOND'],
            'until' => $until,
            'untilIsUtc' => $untilIsUtc,
        ];
    }

    /**
     * Day of the month the rule selects in a year, or null if none.
     *
     * @param array{month: int, weekday: int, ordinal: int|null, monthdays: list<int>} $rule
     */
    private function dayOfMonth(int $year, array $rule): ?int
    {
        $firstOfMonth = gmmktime(0, 0, 0, $rule['month'], 1, $year);
        $daysInMonth = (int) gmdate('t', $firstOfMonth);
        $firstWeekday = (int) gmdate('w', $firstOfMonth);

        if ($rule['monthdays'] !== []) {
            foreach ($rule['monthdays'] as $monthday) {
                if ($monthday <= $daysInMonth
                    && (($firstWeekday + $monthday - 1) % 7) === $rule['weekday']) {
                    return $monthday;
                }
            }

            return null;
        }

        if ($rule['ordinal'] > 0) {
            $day = 1 + (($rule['weekday'] - $firstWeekday + 7) % 7) + ($rule['ordinal'] - 1) * 7;
        } else {
            $lastWeekday = ($firstWeekday + $daysInMonth - 1) % 7;
            $day = $daysInMonth - (($lastWeekday - $rule['weekday'] + 7) % 7) + ($rule['ordinal'] + 1) * 7;
        }

        return ($day >= 1 && $day <= $daysInMonth) ? $day : null;
    }

    /**
     * Parse an UNTIL value.
     *
     * Older Horde exports wrote malformed values such as "19440325T16000Z".
     * Only the end of a historic rule depends on the time part, so when the
     * time part is malformed the date alone is used.
     */
    private function parseUntil(string $value): ?int
    {
        $value = trim($value);

        $until = $this->parseWallTime($value);
        if ($until !== null) {
            return $until;
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})(?:T\d*)?Z?$/i', $value, $m)
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return gmmktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
        }

        return null;
    }

    /**
     * Parse a UTC offset such as "+0100" or "-053000" into seconds.
     */
    private function parseOffset(?string $value): ?int
    {
        if ($value === null || !preg_match('/^([+-])(\d{2})(\d{2})(\d{2})?$/', trim($value), $m)) {
            return null;
        }

        $seconds = (int) $m[2] * 3600 + (int) $m[3] * 60 + (int) ($m[4] ?? 0);

        return $m[1] === '-' ? -$seconds : $seconds;
    }

    /**
     * Parse "YYYYMMDDTHHMMSS[Z]" into a wall-clock reading (as if UTC).
     */
    private function parseWallTime(?string $value): ?int
    {
        if ($value === null
            || !preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})Z?$/i', trim($value), $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return gmmktime((int) $m[4], (int) $m[5], (int) $m[6], (int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
