<?php

/**
 * Unit tests for Horde_ActiveSync_Message_Attendee.
 *
 * @category Horde
 * @package  ActiveSync
 */

namespace Horde\ActiveSync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Horde_ActiveSync_Log_Logger;
use Horde_Log_Handler_Null;
use Horde_ActiveSync_Wbxml_Encoder;
use Horde_ActiveSync;
use Horde_ActiveSync_Message_Appointment;
use Horde_ActiveSync_Message_Attendee;

#[CoversClass(Horde_ActiveSync_Message_Attendee::class)]
class AttendeeTest extends TestCase
{
    public function testEncodeStreamEmitsEmptyProposedTimeTagsWhenClearing()
    {
        $withoutClear = $this->_encodeAttendee(false);
        $withClear = $this->_encodeAttendee(true);

        $this->assertGreaterThan(
            strlen($withoutClear),
            strlen($withClear),
            'clearProposedTimes must emit empty ProposedStartTime/ProposedEndTime WBXML tags'
        );
    }

    protected function _encodeAttendee(bool $clearProposedTimes): string
    {
        $logger = new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null());

        $attendee = new Horde_ActiveSync_Message_Attendee([
            'logger' => $logger,
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEENONE,
        ]);
        $attendee->email = 'guest@example.com';
        $attendee->name = 'Guest';
        $attendee->status = Horde_ActiveSync_Message_Attendee::STATUS_TENTATIVE;
        $attendee->type = Horde_ActiveSync_Message_Attendee::TYPE_REQUIRED;
        $attendee->clearProposedTimes = $clearProposedTimes;

        $stream = fopen('php://memory', 'w+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($stream);
        $encoder->setLogger($logger);

        $encoder->startTag(Horde_ActiveSync_Message_Appointment::POOMCAL_ATTENDEE);
        $attendee->encodeStream($encoder);
        $encoder->endTag();

        rewind($stream);
        $encoded = stream_get_contents($stream);
        fclose($stream);

        return $encoded;
    }
}
