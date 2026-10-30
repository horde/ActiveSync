<?php

declare(strict_types=1);

/**
 * Unit tests for the calendar time resolution used by meeting requests.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Calendar;

use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Component\AbstractComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CalendarTimeResolver::class)]
#[CoversClass(VtimezoneOffsets::class)]
#[CoversClass(ResolvedTime::class)]
class CalendarTimeResolverTest extends TestCase
{
    private string $oldTz;

    protected function setUp(): void
    {
        $this->oldTz = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->oldTz);
    }

    /**
     * Resolve the DTSTART of the only event in a calendar.
     */
    private function resolve(string $dtstartLine, string $timezones = ''): ?ResolvedTime
    {
        $calendar = AbstractComponent::fromString(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Example//EN\r\nMETHOD:REQUEST\r\n"
            . $timezones
            . "BEGIN:VEVENT\r\nUID:alice-1@example.com\r\n"
            . "DTSTAMP:20260101T000000Z\r\n"
            . $dtstartLine . "\r\n"
            . "SUMMARY:Planning\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );
        $this->assertInstanceOf(VCalendar::class, $calendar);
        $event = $calendar->getEvents()[0];

        return (new CalendarTimeResolver($calendar))->resolve($event->getProperties()->get('DTSTART'));
    }

    /**
     * A US style zone under a vendor TZID: second Sunday in March and first
     * Sunday in November, DTSTART in 1601 as Outlook writes it.
     */
    private function usZone(string $tzid): string
    {
        return "BEGIN:VTIMEZONE\r\nTZID:$tzid\r\n"
            . "BEGIN:STANDARD\r\nDTSTART:16011104T020000\r\n"
            . "RRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=11\r\n"
            . "TZOFFSETFROM:-0400\r\nTZOFFSETTO:-0500\r\nEND:STANDARD\r\n"
            . "BEGIN:DAYLIGHT\r\nDTSTART:16010311T020000\r\n"
            . "RRULE:FREQ=YEARLY;BYDAY=2SU;BYMONTH=3\r\n"
            . "TZOFFSETFROM:-0500\r\nTZOFFSETTO:-0400\r\nEND:DAYLIGHT\r\n"
            . "END:VTIMEZONE\r\n";
    }

    public function testUtcValue(): void
    {
        $result = $this->resolve('DTSTART:20260701T100000Z');

        $this->assertSame(gmmktime(10, 0, 0, 7, 1, 2026), $result->timestamp);
        $this->assertNull($result->unresolvedTzid);
    }

    public function testFloatingValueUsesServerTimeZone(): void
    {
        $result = $this->resolve('DTSTART:20260701T100000');

        // Berlin is UTC+2 in July.
        $this->assertSame(gmmktime(8, 0, 0, 7, 1, 2026), $result->timestamp);
        $this->assertNull($result->unresolvedTzid);
    }

    public function testDateValueUsesServerTimeZone(): void
    {
        $result = $this->resolve('DTSTART;VALUE=DATE:20260701');

        $this->assertSame(gmmktime(22, 0, 0, 6, 30, 2026), $result->timestamp);
    }

    public function testIanaTzidWithoutVtimezone(): void
    {
        $result = $this->resolve('DTSTART;TZID=America/New_York:20260115T100000');

        $this->assertSame(gmmktime(15, 0, 0, 1, 15, 2026), $result->timestamp);
        $this->assertNull($result->unresolvedTzid);
    }

    public function testWindowsTzidWithoutVtimezoneIsMapped(): void
    {
        $result = $this->resolve('DTSTART;TZID=W. Europe Standard Time:20260115T100000');

        $this->assertSame(gmmktime(9, 0, 0, 1, 15, 2026), $result->timestamp);
        $this->assertNull($result->unresolvedTzid);
    }

    /**
     * @dataProvider customZoneProvider
     */
    public function testCustomTzidIsResolvedFromVtimezone(string $local, int $expectedUtc): void
    {
        $result = $this->resolve(
            'DTSTART;TZID=Custom Eastern:' . $local,
            $this->usZone('Custom Eastern')
        );

        $this->assertSame($expectedUtc, $result->timestamp);
        $this->assertNull($result->unresolvedTzid);
    }

    public static function customZoneProvider(): array
    {
        return [
            'winter' => ['20260115T100000', gmmktime(15, 0, 0, 1, 15, 2026)],
            'summer' => ['20260715T100000', gmmktime(14, 0, 0, 7, 15, 2026)],
            'day before DST starts' => ['20260307T120000', gmmktime(17, 0, 0, 3, 7, 2026)],
            'day DST starts' => ['20260308T120000', gmmktime(16, 0, 0, 3, 8, 2026)],
            'day DST ends' => ['20261101T120000', gmmktime(17, 0, 0, 11, 1, 2026)],
            'leap day' => ['20280229T120000', gmmktime(17, 0, 0, 2, 29, 2028)],
        ];
    }

    public function testVtimezoneWinsOverTzidName(): void
    {
        // The calendar redefines an IANA name with a fixed -0300 offset.
        $zone = "BEGIN:VTIMEZONE\r\nTZID:Europe/Berlin\r\n"
            . "BEGIN:STANDARD\r\nDTSTART:20000101T000000\r\n"
            . "TZOFFSETFROM:-0300\r\nTZOFFSETTO:-0300\r\nEND:STANDARD\r\n"
            . "END:VTIMEZONE\r\n";

        $result = $this->resolve('DTSTART;TZID=Europe/Berlin:20260115T100000', $zone);

        $this->assertSame(gmmktime(13, 0, 0, 1, 15, 2026), $result->timestamp);
    }

    public function testUnknownTzidWithoutVtimezoneIsReported(): void
    {
        $result = $this->resolve('DTSTART;TZID=Mystery Standard Time:20260701T100000');

        $this->assertSame('Mystery Standard Time', $result->unresolvedTzid);
        // Falls back to the server time zone (Berlin, UTC+2).
        $this->assertSame(gmmktime(8, 0, 0, 7, 1, 2026), $result->timestamp);
    }

    public function testUnsupportedVtimezoneFallsBackToIanaName(): void
    {
        $zone = "BEGIN:VTIMEZONE\r\nTZID:Europe/Berlin\r\n"
            . "BEGIN:STANDARD\r\nDTSTART:20000101T000000\r\n"
            . "RRULE:FREQ=DAILY;COUNT=3\r\n"
            . "TZOFFSETFROM:+0100\r\nTZOFFSETTO:+0100\r\nEND:STANDARD\r\n"
            . "END:VTIMEZONE\r\n";

        $result = $this->resolve('DTSTART;TZID=Europe/Berlin:20260701T100000', $zone);

        $this->assertSame(gmmktime(8, 0, 0, 7, 1, 2026), $result->timestamp);
        $this->assertNull($result->unresolvedTzid);
    }

    /**
     * @dataProvider invalidValueProvider
     */
    public function testInvalidValueResolvesToNull(string $line): void
    {
        $this->assertNull($this->resolve($line));
    }

    public static function invalidValueProvider(): array
    {
        return [
            'text' => ['DTSTART:tomorrow'],
            'impossible date' => ['DTSTART:20260231T100000Z'],
            'truncated' => ['DTSTART:2026070'],
        ];
    }

    public function testVcalendar10ValueWithTrailingFields(): void
    {
        $result = $this->resolve('DTSTART:20260701T100000Z;PT10M;3;Beep');

        $this->assertSame(gmmktime(10, 0, 0, 7, 1, 2026), $result->timestamp);
    }

    /**
     * Zone whose DST rules ended long ago and that has stayed on standard
     * time since (Brisbane style).
     */
    public function testExpiredRulesLeaveTheLastOffsetInForce(): void
    {
        $zone = "BEGIN:VTIMEZONE\r\nTZID:Expired\r\n"
            . "BEGIN:DAYLIGHT\r\nDTSTART:19891029T020000\r\n"
            . "RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10;UNTIL=19911026T160000Z\r\n"
            . "TZOFFSETFROM:+1000\r\nTZOFFSETTO:+1100\r\nEND:DAYLIGHT\r\n"
            . "BEGIN:STANDARD\r\nDTSTART:19900304T020000\r\n"
            . "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19920229T160000Z\r\n"
            . "TZOFFSETFROM:+1100\r\nTZOFFSETTO:+1000\r\nEND:STANDARD\r\n"
            . "END:VTIMEZONE\r\n";

        $result = $this->resolve('DTSTART;TZID=Expired:20260115T120000', $zone);

        $this->assertSame(gmmktime(2, 0, 0, 1, 15, 2026), $result->timestamp);
    }

    public function testObservanceWithoutDtstartIsIgnored(): void
    {
        $zone = "BEGIN:VTIMEZONE\r\nTZID:Partial\r\n"
            . "BEGIN:STANDARD\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0000\r\nEND:STANDARD\r\n"
            . "BEGIN:STANDARD\r\nDTSTART:19710101T000000\r\n"
            . "TZOFFSETFROM:+0000\r\nTZOFFSETTO:+0200\r\nEND:STANDARD\r\n"
            . "END:VTIMEZONE\r\n";

        $result = $this->resolve('DTSTART;TZID=Partial:20260115T120000', $zone);

        $this->assertSame(gmmktime(10, 0, 0, 1, 15, 2026), $result->timestamp);
    }

    /**
     * Rules restated with BYMONTHDAY, BYHOUR and WKST, as some exporters
     * write the "second Sunday" of a month.
     */
    public function testRuleWithByMonthDayAndTimeParts(): void
    {
        $zone = "BEGIN:VTIMEZONE\r\nTZID:Exporter\r\n"
            . "BEGIN:STANDARD\r\nDTSTART:16011104T000000\r\n"
            . "RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=11;BYDAY=SU;BYMONTHDAY=1,2,3,4,5,6,7;BYHOUR=2\r\n"
            . "TZOFFSETFROM:-0400\r\nTZOFFSETTO:-0500\r\nEND:STANDARD\r\n"
            . "BEGIN:DAYLIGHT\r\nDTSTART:16010311T000000\r\n"
            . "RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=3;BYDAY=SU;BYMONTHDAY=8,9,10,11,12,13,14;BYHOUR=2\r\n"
            . "TZOFFSETFROM:-0500\r\nTZOFFSETTO:-0400\r\nEND:DAYLIGHT\r\n"
            . "END:VTIMEZONE\r\n";

        $summer = $this->resolve('DTSTART;TZID=Exporter:20260715T100000', $zone);
        $winter = $this->resolve('DTSTART;TZID=Exporter:20260115T100000', $zone);
        $afterSwitch = $this->resolve('DTSTART;TZID=Exporter:20260308T120000', $zone);
        $beforeSwitch = $this->resolve('DTSTART;TZID=Exporter:20260307T120000', $zone);

        $this->assertSame(gmmktime(14, 0, 0, 7, 15, 2026), $summer->timestamp);
        $this->assertSame(gmmktime(15, 0, 0, 1, 15, 2026), $winter->timestamp);
        $this->assertSame(gmmktime(16, 0, 0, 3, 8, 2026), $afterSwitch->timestamp);
        $this->assertSame(gmmktime(17, 0, 0, 3, 7, 2026), $beforeSwitch->timestamp);
    }

    public function testOffsetsReturnNullForUnsupportedRule(): void
    {
        $calendar = AbstractComponent::fromString(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Example//EN\r\n"
            . "BEGIN:VTIMEZONE\r\nTZID:Odd\r\n"
            . "BEGIN:STANDARD\r\nDTSTART:20000101T000000\r\nRRULE:FREQ=MONTHLY\r\n"
            . "TZOFFSETFROM:+0100\r\nTZOFFSETTO:+0100\r\nEND:STANDARD\r\n"
            . "END:VTIMEZONE\r\n"
            . "END:VCALENDAR\r\n"
        );

        $this->assertNull(
            (new VtimezoneOffsets())->offsetAt($calendar->getTimezones()[0], gmmktime(12, 0, 0, 7, 1, 2026))
        );
    }
}
