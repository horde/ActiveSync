<?php

declare(strict_types=1);

/**
 * Unit tests for the iTip handling of Horde_ActiveSync_Imap_EasMessageBuilder.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Imap;

use Horde_ActiveSync;
use Horde_ActiveSync_Imap_EasMessageBuilder;
use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Message_Mail;
use Horde_ActiveSync_Message_MeetingRequest;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Horde_ActiveSync_Imap_EasMessageBuilder::class)]
class EasMessageBuilderMeetingRequestTest extends TestCase
{
    /**
     * Run the builder's iTip handling on a calendar and return the message.
     */
    private function build(string $ics): Horde_ActiveSync_Message_Mail
    {
        $logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());
        $message = new Horde_ActiveSync_Message_Mail([
            'logger' => $logger,
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEENONE,
        ]);

        $part = new class ($ics) {
            public function __construct(private string $ics)
            {
            }

            public function getContents(): string
            {
                return $this->ics;
            }

            public function getCharset(): string
            {
                return 'UTF-8';
            }
        };
        $imapMessage = new class ($part) {
            public function __construct(private object $part)
            {
            }

            public function hasiCalendar(): object
            {
                return $this->part;
            }
        };

        // The constructor needs a full IMAP message; only the iTip handling
        // is under test.
        $reflection = new ReflectionClass(Horde_ActiveSync_Imap_EasMessageBuilder::class);
        $builder = $reflection->newInstanceWithoutConstructor();
        foreach ([
            '_version' => Horde_ActiveSync::VERSION_SIXTEENONE,
            '_logger' => $logger,
            '_easMessage' => $message,
            '_imapMessage' => $imapMessage,
        ] as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setValue($builder, $value);
        }
        $reflection->getMethod('_meetingRequest')->invoke($builder);

        return $message;
    }

    private function calendar(array $calendarLines, array $eventLines = []): string
    {
        return implode("\r\n", array_merge(
            ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Example//Test//EN'],
            $calendarLines,
            [
                'BEGIN:VEVENT',
                'UID:test-event-uid@example.test',
                'DTSTART:20260618T220000Z',
                'DTEND:20260618T230000Z',
                'DTSTAMP:20260619T195852Z',
                'ORGANIZER:mailto:organizer@example.test',
            ],
            $eventLines,
            ['END:VEVENT', 'END:VCALENDAR', '']
        ));
    }

    public function testRequestBecomesMeetingRequest(): void
    {
        $message = $this->build($this->calendar(['METHOD:REQUEST']));

        $this->assertSame('IPM.Schedule.Meeting.Request', $message->messageclass);
        $this->assertSame('urn:content-classes:calendarmessage', $message->contentclass);
        $this->assertInstanceOf(Horde_ActiveSync_Message_MeetingRequest::class, $message->meetingrequest);
    }

    /**
     * A repeated METHOD made the legacy reader return an array, which
     * crashed the whole sync with a TypeError.
     */
    public function testRepeatedMethodDoesNotBreakTheSync(): void
    {
        $message = $this->build($this->calendar(['METHOD:REQUEST', 'METHOD:REQUEST']));

        $this->assertSame('IPM.Schedule.Meeting.Request', $message->messageclass);
        $this->assertInstanceOf(Horde_ActiveSync_Message_MeetingRequest::class, $message->meetingrequest);
    }

    public function testMissingMethodIsTreatedAsRequest(): void
    {
        $message = $this->build($this->calendar([]));

        $this->assertSame('IPM.Schedule.Meeting.Request', $message->messageclass);
    }

    public function testCancelBecomesCanceledMeeting(): void
    {
        $message = $this->build($this->calendar(['METHOD:CANCEL']));

        $this->assertSame(Horde_ActiveSync_Message_Mail::CLASS_MEETING_CANCELED, $message->messageclass);
    }

    /**
     * @dataProvider replyProvider
     */
    public function testReplyMapsParticipationStatus(string $partstat, string $expectedClass): void
    {
        $message = $this->build($this->calendar(
            ['METHOD:REPLY'],
            ['ATTENDEE;PARTSTAT=' . $partstat . ':mailto:attendee@example.test']
        ));

        $this->assertSame($expectedClass, $message->messageclass);
        $this->assertInstanceOf(Horde_ActiveSync_Message_MeetingRequest::class, $message->meetingrequest);
    }

    public static function replyProvider(): array
    {
        return [
            'accepted' => ['ACCEPTED', 'IPM.Schedule.Meeting.Resp.Pos'],
            'declined' => ['DECLINED', 'IPM.Schedule.Meeting.Resp.Neg'],
            'tentative' => ['TENTATIVE', 'IPM.Schedule.Meeting.Resp.Tent'],
            'lower case' => ['accepted', 'IPM.Schedule.Meeting.Resp.Pos'],
        ];
    }

    public function testReplyWithoutAttendeeDegradesToPlainMail(): void
    {
        $message = $this->build($this->calendar(['METHOD:REPLY']));

        $this->assertEmpty($message->messageclass);
        $this->assertEmpty($message->meetingrequest);
    }

    public function testEventWithoutStartDegradesToPlainMail(): void
    {
        $ics = str_replace("DTSTART:20260618T220000Z\r\n", '', $this->calendar(['METHOD:REQUEST']));

        $message = $this->build($ics);

        $this->assertEmpty($message->messageclass);
        $this->assertEmpty($message->contentclass);
    }

    /**
     * @dataProvider unusableCalendarProvider
     */
    public function testUnusableCalendarLeavesTheMailUntouched(string $ics): void
    {
        $message = $this->build($ics);

        $this->assertEmpty($message->messageclass);
        $this->assertEmpty($message->contentclass);
    }

    public static function unusableCalendarProvider(): array
    {
        return [
            'not a calendar' => ["just some text\r\n"],
            'unterminated' => ["BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:x\r\n"],
            'no event' => ["BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//E//T//EN\r\nMETHOD:REQUEST\r\nEND:VCALENDAR\r\n"],
        ];
    }
}
