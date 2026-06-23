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

use Horde_ActiveSync;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Message_MeetingRequest;
use Horde_Icalendar;
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

    protected function _createMeetingRequest(
        string $version,
        array $extraEventLines = [],
        string $method = 'REQUEST'
    ): Horde_ActiveSync_Message_MeetingRequest
    {
        $logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());
        $message = new Horde_ActiveSync_Message_MeetingRequest([
            'logger' => $logger,
            'protocolversion' => $version,
        ]);
        $vcal = new Horde_Icalendar();
        $vcal->parseVcalendar($this->_buildVcalendar($extraEventLines, $method));
        $message->fromvEvent($vcal);

        return $message;
    }

    protected function _buildVcalendar(
        array $extraEventLines = [],
        string $method = 'REQUEST'
    ): string
    {
        return implode("\r\n", array_merge([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Horde//ActiveSync Test//EN',
            'METHOD:' . $method,
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
