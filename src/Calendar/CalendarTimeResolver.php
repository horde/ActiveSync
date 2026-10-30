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

use DateTimeImmutable;
use DateTimeZone;
use Horde\Date\TimezoneMapper;
use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Calendar\Vtimezone;
use Horde\Icalendar\Property\Property;

/**
 * Resolves DATE and DATE-TIME properties of a calendar to unix timestamps.
 *
 * The typed Vevent accessors read a TZID only as an IANA zone name and throw
 * on anything else. Mail clients routinely send Windows names or custom
 * names whose rules live in the calendar's own VTIMEZONE, so a meeting from
 * such a sender could not be read through them. This resolver reads the
 * property directly and resolves the zone in this order:
 *
 * 1. A UTC value ("...Z").
 * 2. The VTIMEZONE in the same calendar that carries the TZID.
 * 3. The TZID as an IANA name, or as a Windows name mapped to one.
 * 4. Otherwise the wall-clock time is read in the server time zone and the
 *    unresolved TZID is reported on the result.
 *
 * Values without a TZID (floating times and DATE values) are read in the
 * server time zone.
 */
final class CalendarTimeResolver
{
    /** @var array<string, Vtimezone> */
    private array $vtimezones = [];

    /** @var array<string, true>|null */
    private static ?array $zoneNames = null;

    public function __construct(
        VCalendar $calendar,
        private readonly VtimezoneOffsets $offsets = new VtimezoneOffsets()
    ) {
        foreach ($calendar->getTimezones() as $vtimezone) {
            $tzid = $vtimezone->getPropertyValue('TZID');
            if ($tzid !== null && $tzid !== '') {
                $this->vtimezones[$tzid] ??= $vtimezone;
            }
        }
    }

    /**
     * Resolve a DATE or DATE-TIME property.
     *
     * @return ResolvedTime|null  Null when the value is not a date or
     *                            date-time.
     */
    public function resolve(Property $property): ?ResolvedTime
    {
        // vCalendar 1.0 properties such as AALARM append extra fields after
        // a semicolon.
        $value = trim(explode(';', $property->getValue())[0]);

        if (!preg_match('/^(\d{4})-?(\d{2})-?(\d{2})(?:T(\d{2})(\d{2})(\d{2})(Z)?)?$/i', $value, $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        [$hour, $minute, $second] = [(int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0)];

        if (!empty($m[7])) {
            return new ResolvedTime(gmmktime($hour, $minute, $second, $month, $day, $year));
        }

        $tzid = trim($property->getParameter('TZID')?->getValue() ?? '');
        if ($tzid === '') {
            return new ResolvedTime($this->inZone(
                date_default_timezone_get(),
                $year,
                $month,
                $day,
                $hour,
                $minute,
                $second
            ));
        }

        $wallTime = gmmktime($hour, $minute, $second, $month, $day, $year);

        if (isset($this->vtimezones[$tzid])) {
            $offset = $this->offsets->offsetAt($this->vtimezones[$tzid], $wallTime);
            if ($offset !== null) {
                return new ResolvedTime($wallTime - $offset);
            }
        }

        $zone = TimezoneMapper::toIana($tzid);
        if ($this->isKnownZone($zone)) {
            return new ResolvedTime($this->inZone($zone, $year, $month, $day, $hour, $minute, $second));
        }

        return new ResolvedTime(
            $this->inZone(date_default_timezone_get(), $year, $month, $day, $hour, $minute, $second),
            $tzid
        );
    }

    /**
     * Timestamp of a wall-clock reading in a known time zone.
     */
    private function inZone(
        string $zone,
        int $year,
        int $month,
        int $day,
        int $hour,
        int $minute,
        int $second
    ): int {
        return (new DateTimeImmutable('now', new DateTimeZone($zone)))
            ->setDate($year, $month, $day)
            ->setTime($hour, $minute, $second)
            ->getTimestamp();
    }

    /**
     * Whether PHP knows the time zone name, so constructing it cannot fail.
     */
    private function isKnownZone(string $zone): bool
    {
        self::$zoneNames ??= array_fill_keys(
            DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),
            true
        );

        return isset(self::$zoneNames[$zone]);
    }
}
