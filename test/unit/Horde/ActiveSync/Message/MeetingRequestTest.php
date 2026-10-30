<?php

/**
 * Unit tests for Horde_ActiveSync_Message_MeetingRequest.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync;

use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Component\AbstractComponent;
use Horde_ActiveSync;
use Horde_ActiveSync_Exception;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Message_MeetingRequest;
use Horde_Date;
use Horde_Icalendar;
use Horde_Icalendar_Vevent;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_ActiveSync_Message_MeetingRequest::class)]
class MeetingRequestTest extends TestCase
{
    protected string $_oldtz;

    protected function setUp(): void
    {
        $this->_oldtz = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->_oldtz);
    }

    public function testFromVeventExportsDisallowNewTimeProposalForDisallowCounter(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE,
            ['DISALLOW-COUNTER:TRUE']
        );

        $this->assertTrue($message->getProperty('disallownewtimeproposal'));
    }

    public function testFromVeventExportsDisallowNewTimeProposalForMicrosoftVariants(): void
    {
        foreach (['X-MS-DISALLOW-COUNTER:TRUE', 'X-MICROSOFT-DISALLOW-COUNTER:TRUE'] as $line) {
            $message = $this->_createMeetingRequest(
                Horde_ActiveSync::VERSION_SIXTEENONE,
                [$line]
            );

            $this->assertTrue($message->getProperty('disallownewtimeproposal'));
        }
    }

    public function testFromVeventOmitsDisallowNewTimeProposalWhenUnset(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE
        );

        $this->assertFalse($message->getProperty('disallownewtimeproposal'));
    }

    public function testFromVeventIgnoresDisallowCounterBeforeEas14(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_TWELVEONE,
            ['DISALLOW-COUNTER:TRUE']
        );

        $this->assertFalse($message->propertyExists('disallownewtimeproposal'));
    }

    public function testFromVeventSetsMeetingMessageTypeForCancel(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE,
            [],
            'CANCEL'
        );

        $this->assertSame(
            Horde_ActiveSync_Message_MeetingRequest::MEETING_MESSAGE_CANCEL,
            $message->getProperty('meetingmessagetype')
        );
        $this->assertSame('0', $message->getProperty('responserequested'));
    }

    public function testFromVeventSetsInstanceTypeForSingleAppointment(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE
        );

        $this->assertSame('0', $message->getProperty('instancetype'));
        $this->assertSame([], $message->getProperty('recurrences'));
    }

    public function testFromVeventExportsWeeklyRecurrenceForMasterSeries(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE,
            ['RRULE:FREQ=WEEKLY;BYDAY=TH;COUNT=5']
        );

        $this->assertSame('1', $message->getProperty('instancetype'));
        $recurrences = $message->getProperty('recurrences');
        $this->assertCount(1, $recurrences);
        $this->assertInstanceOf(
            \Horde_ActiveSync_Message_MeetingRequestRecurrence::class,
            $recurrences[0]
        );
        $this->assertSame(
            \Horde_ActiveSync_Message_Recurrence::TYPE_WEEKLY,
            $recurrences[0]->getProperty('type')
        );
        $this->assertSame(5, (int) $recurrences[0]->getProperty('occurrences'));
    }

    public function testFromVeventSetsInstanceTypeForRecurrenceId(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE,
            ['RECURRENCE-ID:20260618T220000Z']
        );

        $this->assertSame('3', $message->getProperty('instancetype'));
        $this->assertInstanceOf(
            \Horde_Date::class,
            $message->getProperty('recurrenceid')
        );
    }

    public function testRepeatedMethodUsesTheFirstValue(): void
    {
        $ics = str_replace(
            "METHOD:REQUEST\r\n",
            "METHOD:REQUEST\r\nMETHOD:REQUEST\r\nMETHOD:CANCEL\r\n",
            $this->_buildVcalendar()
        );

        $message = $this->_parse($ics);

        $this->assertSame('1', $message->getProperty('responserequested'));
        $this->assertSame(
            Horde_ActiveSync_Message_MeetingRequest::MEETING_MESSAGE_INITIAL,
            $message->getProperty('meetingmessagetype')
        );
    }

    public function testMissingMethodIsTreatedAsRequest(): void
    {
        $ics = str_replace("METHOD:REQUEST\r\n", '', $this->_buildVcalendar());

        $message = $this->_parse($ics);

        $this->assertSame('1', $message->getProperty('responserequested'));
    }

    public function testMethodIsCaseInsensitive(): void
    {
        $message = $this->_createMeetingRequest(Horde_ActiveSync::VERSION_SIXTEENONE, [], 'cancel');

        $this->assertSame('0', $message->getProperty('responserequested'));
    }

    public function testSequenceMakesRequestAnUpdate(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE,
            ['SEQUENCE:2']
        );

        $this->assertSame(
            Horde_ActiveSync_Message_MeetingRequest::MEETING_MESSAGE_UPDATE,
            $message->getProperty('meetingmessagetype')
        );
    }

    public function testTimesAndOrganizerAreExported(): void
    {
        $message = $this->_createMeetingRequest(Horde_ActiveSync::VERSION_SIXTEENONE);

        $this->assertSame(gmmktime(22, 0, 0, 6, 18, 2026), $message->getProperty('starttime')->timestamp());
        $this->assertSame(gmmktime(23, 0, 0, 6, 18, 2026), $message->getProperty('endtime')->timestamp());
        $this->assertSame(gmmktime(19, 58, 52, 6, 19, 2026), $message->getProperty('dtstamp')->timestamp());
        $this->assertSame('organizer@example.test', $message->getProperty('organizer'));
        $this->assertSame('0', (string) $message->getProperty('alldayevent'));
    }

    public function testWindowsTimeZoneWithEmbeddedVtimezone(): void
    {
        $zone = [
            'BEGIN:VTIMEZONE',
            'TZID:W. Europe Standard Time',
            'BEGIN:STANDARD',
            'DTSTART:16011028T030000',
            'RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'END:STANDARD',
            'BEGIN:DAYLIGHT',
            'DTSTART:16010325T020000',
            'RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'END:DAYLIGHT',
            'END:VTIMEZONE',
        ];
        $ics = $this->_replaceEventTimes(
            $this->_buildVcalendar([], 'REQUEST', $zone),
            'DTSTART;TZID=W. Europe Standard Time:20260715T100000',
            'DTEND;TZID=W. Europe Standard Time:20260715T110000'
        );

        $message = $this->_parse($ics);

        // Summer time in Central Europe is UTC+2.
        $this->assertSame(gmmktime(8, 0, 0, 7, 15, 2026), $message->getProperty('starttime')->timestamp());
        $this->assertSame(gmmktime(9, 0, 0, 7, 15, 2026), $message->getProperty('endtime')->timestamp());
    }

    public function testUnknownTimeZoneFallsBackToServerTimeZone(): void
    {
        $ics = $this->_replaceEventTimes(
            $this->_buildVcalendar(),
            'DTSTART;TZID=Mystery Standard Time:20260715T100000',
            'DTEND;TZID=Mystery Standard Time:20260715T110000'
        );

        $message = $this->_parse($ics);

        // The test runs with UTC as the server time zone.
        $this->assertSame(gmmktime(10, 0, 0, 7, 15, 2026), $message->getProperty('starttime')->timestamp());
    }

    public function testAllDayEventIsDetected(): void
    {
        $ics = $this->_replaceEventTimes(
            $this->_buildVcalendar(),
            'DTSTART;VALUE=DATE:20260715',
            'DTEND;VALUE=DATE:20260716'
        );

        $message = $this->_parse($ics);

        $this->assertSame(1, $message->getProperty('alldayevent'));
    }

    public function testEndIsDerivedFromDuration(): void
    {
        $ics = $this->_replaceEventTimes($this->_buildVcalendar(), 'DTSTART:20260715T100000Z', 'DURATION:PT90M');

        $message = $this->_parse($ics);

        $this->assertSame(gmmktime(11, 30, 0, 7, 15, 2026), $message->getProperty('endtime')->timestamp());
    }

    public function testEventWithoutUsableStartIsRejected(): void
    {
        $ics = $this->_replaceEventTimes($this->_buildVcalendar(), 'X-NOTHING:1', 'X-NOTHING:2');

        $this->expectException(Horde_ActiveSync_Exception::class);
        $this->_parse($ics);
    }

    public function testLocationIsUnescaped(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE,
            ['LOCATION:Room 1\, Building 2\; Floor 3']
        );

        $this->assertSame('Room 1, Building 2; Floor 3', $message->getProperty('location'));
    }

    public function testLocationQuotedPrintableAndCharsetAreDecoded(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE,
            ['LOCATION;ENCODING=QUOTED-PRINTABLE;CHARSET=ISO-8859-1:Caf=E9 M=FCnchen']
        );

        $this->assertSame("Caf\u{e9} M\u{fc}nchen", $message->getProperty('location'));
    }

    public function testLocationIsIndependentOfEndTime(): void
    {
        $ics = $this->_replaceEventTimes(
            $this->_buildVcalendar(['LOCATION:Room 1']),
            'DTSTART:20260715T100000Z',
            'X-NOTHING:1'
        );

        $message = $this->_parse($ics);

        $this->assertSame('Room 1', $message->getProperty('location'));
    }

    public function testClassAndStatusAreMapped(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE,
            ['CLASS:private', 'STATUS:tentative']
        );

        $this->assertSame(
            \Horde_ActiveSync_Message_Appointment::SENSITIVITY_PRIVATE,
            $message->getProperty('sensitivity')
        );
        $this->assertSame(
            \Horde_ActiveSync_Message_Appointment::BUSYSTATUS_TENTATIVE,
            $message->getProperty('busystatus')
        );
    }

    public function testRepeatedClassDoesNotBreakParsing(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE,
            ['CLASS:CONFIDENTIAL', 'CLASS:PUBLIC']
        );

        $this->assertSame(
            \Horde_ActiveSync_Message_Appointment::SENSITIVITY_CONFIDENTIAL,
            $message->getProperty('sensitivity')
        );
    }

    public function testRelativeAlarmSetsReminder(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE,
            ['BEGIN:VALARM', 'ACTION:DISPLAY', 'TRIGGER:-PT15M', 'END:VALARM']
        );

        $this->assertSame(900, $message->getProperty('reminder'));
    }

    public function testAbsoluteAlarmSetsReminder(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE,
            ['BEGIN:VALARM', 'ACTION:DISPLAY', 'TRIGGER;VALUE=DATE-TIME:20260618T214500Z', 'END:VALARM']
        );

        $this->assertSame(900, $message->getProperty('reminder'));
    }

    public function testUnparseableAlarmIsIgnored(): void
    {
        $message = $this->_createMeetingRequest(
            Horde_ActiveSync::VERSION_SIXTEENONE,
            ['BEGIN:VALARM', 'ACTION:DISPLAY', 'TRIGGER:soon', 'END:VALARM']
        );

        $this->assertFalse($message->getProperty('reminder'));
    }

    public function testLegacyEntryPointStillWorks(): void
    {
        $logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());
        $message = new Horde_ActiveSync_Message_MeetingRequest([
            'logger' => $logger,
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEENONE,
        ]);
        $vcal = new Horde_Icalendar();
        $vcal->parseVcalendar($this->_buildVcalendar());

        $message->fromvEvent($vcal);

        $this->assertSame(gmmktime(22, 0, 0, 6, 18, 2026), $message->getProperty('starttime')->timestamp());
    }

    public function testGetvEventReturnsLegacyEvent(): void
    {
        $message = $this->_createMeetingRequest(Horde_ActiveSync::VERSION_SIXTEENONE);

        $vevent = $message->getvEvent();

        $this->assertInstanceOf(Horde_Icalendar_Vevent::class, $vevent);
        $this->assertSame('test-event-uid@example.test', $vevent->getAttribute('UID'));
    }

    public function testGetvEventIsNullBeforeParsing(): void
    {
        $message = new Horde_ActiveSync_Message_MeetingRequest([
            'logger' => new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null()),
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEENONE,
        ]);

        $this->assertNull($message->getvEvent());
    }

    /**
     * Replace the DTSTART and DTEND lines of the default event.
     */
    protected function _replaceEventTimes(string $ics, string $start, string $end): string
    {
        return str_replace(
            ["DTSTART:20260618T220000Z", "DTEND:20260618T230000Z"],
            [$start, $end],
            $ics
        );
    }

    protected function _createMeetingRequest(
        string $version,
        array $extraEventLines = [],
        string $method = 'REQUEST'
    ): Horde_ActiveSync_Message_MeetingRequest
    {
        return $this->_parse($this->_buildVcalendar($extraEventLines, $method), $version);
    }

    protected function _parse(
        string $ics,
        string $version = Horde_ActiveSync::VERSION_SIXTEENONE
    ): Horde_ActiveSync_Message_MeetingRequest
    {
        $logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());
        $message = new Horde_ActiveSync_Message_MeetingRequest([
            'logger' => $logger,
            'protocolversion' => $version,
        ]);
        $calendar = AbstractComponent::fromString($ics);
        $this->assertInstanceOf(VCalendar::class, $calendar);
        $message->fromCalendar($calendar);

        return $message;
    }

    protected function _buildVcalendar(
        array $extraEventLines = [],
        string $method = 'REQUEST',
        array $timezoneLines = []
    ): string
    {
        return implode("\r\n", array_merge([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Horde//ActiveSync Test//EN',
            'METHOD:' . $method,
        ], $timezoneLines, [
            'BEGIN:VEVENT',
            'UID:test-event-uid@example.test',
            'DTSTART:20260618T220000Z',
            'DTEND:20260618T230000Z',
            'DTSTAMP:20260619T195852Z',
            'ORGANIZER:mailto:organizer@example.test',
            'ATTENDEE:mailto:attendee@example.test',
        ], $extraEventLines, [
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]));
    }
}
