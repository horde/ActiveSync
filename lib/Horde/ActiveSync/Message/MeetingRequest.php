<?php

use Horde\ActiveSync\Calendar\CalendarTimeResolver;
use Horde\Date\DateException;
use Horde\Date\Duration;
use Horde\Icalendar\Calendar\Vevent;
use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Component\AbstractComponent;
use Horde\Icalendar\Enum\CalendarMethod;
use Horde\Icalendar\IcalendarException;
use Horde\Icalendar\Property\Property;
use Horde\Util\HordeString;

/**
 * Portions of this class were ported from the Z-Push project:
 *   File      :   wbxml.php
 *   Project   :   Z-Push
 *   Descr     :   WBXML mapping file
 *
 *   Created   :   01.10.2007
 *
 *   © Zarafa Deutschland GmbH, www.zarafaserver.de
 *   This file is distributed under GPL-2.0.
 *   Consult LICENSE file for details
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */

/**
 * Horde_ActiveSync_Message_MeetingRequest
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property integer   $alldayevent
 * @property Horde_Date   $starttime
 * @property Horde_Date   $dtstamp
 * @property Horde_Date   $endtime
 * @property integer   $instancetype
 * @property string   $location
 * @property string   $organizer
 * @property string   $recurrenceid
 * @property integer   $reminder
 * @property integer   $responserequested
 * @property Horde_ActiveSync_Message_Recurrence   $recurrences (Not currently supported).
 * @property integer   $sensitivity
 * @property integer   $busystatus
 * @property string|Horde_Date timezone
 * @property string   globalobjid
 * @property boolean  $disallownewtimeproposal
 */
class Horde_ActiveSync_Message_MeetingRequest extends Horde_ActiveSync_Message_Base
{
    /** @see [MS-ASEMAIL] 2.2.2.39 MeetingMessageType */
    public const MEETING_MESSAGE_INITIAL = '1';
    public const MEETING_MESSAGE_UPDATE  = '2';
    public const MEETING_MESSAGE_CANCEL  = '3';

    protected const DISALLOW_COUNTER_ATTRIBUTES = [
        'DISALLOW-COUNTER',
        'X-MICROSOFT-DISALLOW-COUNTER',
        'X-MS-DISALLOW-COUNTER',
    ];

    /**
     * Property mapping.
     *
     * @var array
     */
    protected $_mapping =  [
        Horde_ActiveSync_Message_Mail::POOMMAIL_ALLDAYEVENT => [self::KEY_ATTRIBUTE => 'alldayevent'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_STARTTIME => [self::KEY_ATTRIBUTE => 'starttime', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Mail::POOMMAIL_DTSTAMP => [self::KEY_ATTRIBUTE => 'dtstamp', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Mail::POOMMAIL_ENDTIME => [self::KEY_ATTRIBUTE => 'endtime', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Mail::POOMMAIL_INSTANCETYPE => [self::KEY_ATTRIBUTE => 'instancetype'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_LOCATION => [self::KEY_ATTRIBUTE => 'location'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_ORGANIZER => [self::KEY_ATTRIBUTE => 'organizer'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_RECURRENCEID => [self::KEY_ATTRIBUTE => 'recurrenceid', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Mail::POOMMAIL_REMINDER => [self::KEY_ATTRIBUTE => 'reminder'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_RESPONSEREQUESTED => [self::KEY_ATTRIBUTE => 'responserequested'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_RECURRENCES => [self::KEY_ATTRIBUTE => 'recurrences', self::KEY_TYPE => 'Horde_ActiveSync_Message_MeetingRequestRecurrence', self::KEY_VALUES => Horde_ActiveSync_Message_Mail::POOMMAIL_RECURRENCE],
        Horde_ActiveSync_Message_Mail::POOMMAIL_SENSITIVITY => [self::KEY_ATTRIBUTE => 'sensitivity'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_BUSYSTATUS => [self::KEY_ATTRIBUTE => 'busystatus'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_TIMEZONE => [self::KEY_ATTRIBUTE => 'timezone'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_GLOBALOBJID => [self::KEY_ATTRIBUTE => 'globalobjid'],
        Horde_ActiveSync_Message_Mail::MEETINGRESPONSE_PROPOSEDSTARTTIME => [self::KEY_ATTRIBUTE => 'proposedstarttime', self::KEY_TYPE => self::TYPE_DATE],
        Horde_ActiveSync_Message_Mail::MEETINGRESPONSE_PROPOSEDENDTIME => [self::KEY_ATTRIBUTE => 'proposedendtime', self::KEY_TYPE => self::TYPE_DATE],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'alldayevent' => '0',
        'starttime' => false,
        'dtstamp' => false,
        'endtime' => false,
        'instancetype' => '0',
        'location' => false,
        'organizer' => false,
        'recurrenceid' => false,
        'reminder' => false,
        'responserequested' => false,
        'recurrences' => [],
        'sensitivity' => false,
        'busystatus' => false,
        'timezone' => false,
        'globalobjid' => false,
        'proposedstarttime' => false,
        'proposedendtime' => false,
    ];

    /**
     * The calendar the request/response is based on.
     *
     * @var VCalendar|null
     */
    protected $_calendar;

    /**
     * Resolves the date-times of the calendar being parsed.
     *
     * @var CalendarTimeResolver|null
     */
    protected $_resolver;

    /**
     * Const'r
     *
     * @see Horde_ActiveSync_Message_Base::__construct()
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        if ($this->_version >= Horde_ActiveSync::VERSION_FOURTEEN) {
            $this->_mapping += [
                Horde_ActiveSync_Message_Mail::POOMMAIL_DISALLOWNEWTIMEPROPOSAL => [self::KEY_ATTRIBUTE => 'disallownewtimeproposal'],
            ];
            $this->_properties += [
                'disallownewtimeproposal' => false,
            ];
        }
        if ($this->_version > Horde_ActiveSync::VERSION_FOURTEEN) {
            $this->_mapping += [
                Horde_ActiveSync_Message_Mail::POOMMAIL2_MEETINGMESSAGETYPE  => [self::KEY_ATTRIBUTE => 'meetingmessagetype'],
            ];
            $this->_properties += [
                'meetingmessagetype' => false,
            ];
        }
    }

    /**
     * Create a meeting request from a calendar.
     *
     * @param VCalendar           $calendar  The parsed iTip.
     * @param CalendarMethod|null $method    The iTip method. Defaults to the
     *                                       calendar's METHOD, or REQUEST when
     *                                       the sender omitted it (Bug #12083).
     *
     * @throws Horde_ActiveSync_Exception
     */
    public function fromCalendar(VCalendar $calendar, ?CalendarMethod $method = null): void
    {
        $method ??= $calendar->getMethod() ?? CalendarMethod::from('REQUEST');
        $this->_calendar = $calendar;
        $this->_resolver = new CalendarTimeResolver($calendar);

        $parsed = false;
        foreach ($calendar->getEvents() as $vevent) {
            $this->_parsevEvent($vevent, $method);
            $parsed = true;
        }

        // Ensure we actually have a vEvent to parse.
        if (!$parsed) {
            return;
        }

        if ($this->starttime instanceof Horde_Date) {
            $tz = new Horde_Mapi_Timezone();
            $this->timezone = $tz->getSyncTZFromOffsets(
                $tz->getOffsetsFromDate($this->starttime)
            );
        }
        $this->alldayevent = (int) $this->_isAllDay();
    }

    /**
     * Create a meeting request from a legacy iCalendar object.
     *
     * @param Horde_Icalendar $vCal  The vCalendar.
     *
     * @deprecated Use fromCalendar().
     *
     * @throws Horde_ActiveSync_Exception
     */
    public function fromvEvent($vCal)
    {
        try {
            $calendar = AbstractComponent::fromString($vCal->exportvCalendar());
        } catch (IcalendarException $e) {
            throw new Horde_ActiveSync_Exception($e->getMessage());
        }
        if (!$calendar instanceof VCalendar) {
            throw new Horde_ActiveSync_Exception('The iCalendar data is not a VCALENDAR.');
        }

        $this->fromCalendar($calendar);
    }

    /**
     * Return the vEvent the request/response is based on, as a legacy
     * iCalendar component.
     *
     * Needed by consumers that still take the legacy object, such as the
     * calendar/updateAttendee registry API.
     *
     * @deprecated Consumers should move to the typed calendar model.
     *
     * @return Horde_Icalendar_Vevent|null  Null when no event was parsed.
     */
    public function getvEvent()
    {
        if ($this->_calendar === null) {
            return null;
        }

        $legacy = new Horde_Icalendar();
        try {
            if (!$legacy->parsevCalendar($this->_calendar->toString())) {
                return null;
            }
        } catch (Horde_Icalendar_Exception $e) {
            return null;
        }

        $vevent = null;
        foreach ($legacy->getComponents() as $component) {
            if ($component instanceof Horde_Icalendar_Vevent) {
                $vevent = $component;
            }
        }

        return $vevent;
    }

    /**
     * Parses a vEvent into the message properties.
     *
     * @param Vevent         $vevent  The vEvent to parse.
     * @param CalendarMethod $method  The iTip method.
     *
     * @throws Horde_ActiveSync_Exception
     */
    protected function _parsevEvent(Vevent $vevent, CalendarMethod $method)
    {
        $method = $method->value;
        $this->responserequested = $method === 'REQUEST' ? '1' : '0';

        // EAS 14.1+: iOS only offers RSVP when MeetingMessageType is 1 or 2.
        if ($this->_version > Horde_ActiveSync::VERSION_FOURTEEN) {
            if ($method === 'REQUEST') {
                $this->meetingmessagetype = $vevent->getSequence() > 0
                    ? self::MEETING_MESSAGE_UPDATE
                    : self::MEETING_MESSAGE_INITIAL;
            } elseif ($method === 'PUBLISH') {
                $this->meetingmessagetype = self::MEETING_MESSAGE_INITIAL;
            } elseif ($method === 'CANCEL') {
                $this->meetingmessagetype = self::MEETING_MESSAGE_CANCEL;
            }
        }

        $organizer = $vevent->getOrganizer()?->getEmail();
        if ($organizer !== null && $organizer !== '') {
            $this->organizer = $organizer;
        }

        $start = $this->_resolveTime($vevent->getProperties()->get('DTSTART'));
        if ($start === null) {
            throw new Horde_ActiveSync_Exception('The calendar event has no usable DTSTART.');
        }
        $this->starttime = $start;
        $this->globalobjid = Horde_Mapi::createGoid($vevent->getUid() ?? 'nouid');

        $dtstamp = $this->_resolveTime($vevent->getProperties()->get('DTSTAMP'));
        if ($dtstamp !== null) {
            $this->dtstamp = $dtstamp;
        }

        $end = $this->_resolveTime($vevent->getProperties()->get('DTEND'));
        if ($end === null && $vevent->getDuration() !== null) {
            try {
                $end = new Horde_Date(
                    $start->timestamp() + Duration::fromIcalendar($vevent->getDuration())->toSeconds()
                );
            } catch (DateException $e) {
                $this->_logger->notice('Ignoring unparseable DURATION: ' . $e->getMessage());
            }
        }
        if ($end !== null) {
            $this->endtime = $end;
        }

        $location = $vevent->getProperties()->get('LOCATION');
        if ($location !== null) {
            $this->location = HordeString::truncate($this->_textValue($location), 255);
        }

        if ($vevent->getProperties()->has('CLASS')) {
            $class = $vevent->getClassification()->value;
            $this->sensitivity = $class === 'PRIVATE'
                ? Horde_ActiveSync_Message_Appointment::SENSITIVITY_PRIVATE
                : ($class === 'CONFIDENTIAL' ? Horde_ActiveSync_Message_Appointment::SENSITIVITY_CONFIDENTIAL
                    : ($class === 'PERSONAL' ? Horde_ActiveSync_Message_Appointment::SENSITIVITY_PERSONAL
                        : Horde_ActiveSync_Message_Appointment::SENSITIVITY_NORMAL));
        }

        $status = $vevent->getStatus()?->value;
        if ($status !== null) {
            $this->busystatus = $status === 'TENTATIVE' ? Horde_ActiveSync_Message_Appointment::BUSYSTATUS_TENTATIVE
                : ($status === 'CONFIRMED' ? Horde_ActiveSync_Message_Appointment::BUSYSTATUS_BUSY
                    : Horde_ActiveSync_Message_Appointment::BUSYSTATUS_FREE);
        }

        // vCalendar 1.0 alarms: the first field is the alarm's date-time.
        $alarmTime = $this->_resolveTime($vevent->getProperties()->get('AALARM'));
        if ($alarmTime !== null && $alarmTime->timestamp() < $start->timestamp()) {
            $this->reminder = $start->timestamp() - $alarmTime->timestamp();
        }

        // vCalendar 2.0 alarms
        foreach ($vevent->getAlarms() as $alarm) {
            $trigger = $alarm->getProperties()->get('TRIGGER');
            if ($trigger === null) {
                continue;
            }

            if (strtoupper($trigger->getParameter('VALUE')?->getValue() ?? '') === 'DATE-TIME') {
                $triggerTime = $this->_resolveTime($trigger);
                if ($triggerTime !== null) {
                    $this->reminder = $start->timestamp() - $triggerTime->timestamp();
                }
                continue;
            }

            try {
                $this->reminder = -Duration::fromIcalendar($trigger->getValue())->toSeconds();
            } catch (DateException $e) {
                $this->_logger->notice('Ignoring unparseable alarm TRIGGER: ' . $e->getMessage());
            }
        }

        $this->_parseDisallowNewTimeProposal($vevent);
        $this->_parseInstanceTypeAndRecurrence($vevent, $start);
    }

    /**
     * Populate instancetype, recurrenceid, and recurrences from VEVENT data.
     *
     * @param Vevent     $vevent  The event.
     * @param Horde_Date $start   The resolved start of the event.
     *
     * @author Torben Dannhauer <torben@dannhauer.de>
     */
    protected function _parseInstanceTypeAndRecurrence(Vevent $vevent, Horde_Date $start): void
    {
        $this->instancetype = Horde_ActiveSync_Utils_Recurrence::detectInstanceType($vevent);

        if (in_array($this->instancetype, ['2', '3'], true)) {
            $recurrenceId = $this->_resolveTime($vevent->getProperties()->get('RECURRENCE-ID'));
            if ($recurrenceId !== null) {
                $this->recurrenceid = $recurrenceId;
            }
        }

        if ($this->instancetype !== '1') {
            return;
        }

        $recurrence = Horde_ActiveSync_Utils_Recurrence::recurrenceFromVevent($vevent, $start);
        if ($recurrence === null) {
            return;
        }

        $this->recurrences = [
            Horde_ActiveSync_Utils_Recurrence::toMeetingRequestRecurrence(
                $recurrence,
                [
                    'logger' => $this->_logger,
                    'protocolversion' => $this->_version,
                    'device' => $this->_device,
                ]
            ),
        ];
    }

    /**
     * Import iCal disallow-counter state for EAS 14+ meeting requests.
     */
    protected function _parseDisallowNewTimeProposal(Vevent $vevent): void
    {
        if ($this->_version < Horde_ActiveSync::VERSION_FOURTEEN) {
            return;
        }

        foreach (self::DISALLOW_COUNTER_ATTRIBUTES as $name) {
            $value = $vevent->getPropertyValue($name);
            if ($value !== null
                && in_array(strtoupper(trim($value)), ['1', 'TRUE', 'YES'], true)) {
                $this->disallownewtimeproposal = true;
                return;
            }
        }
    }

    /**
     * Resolve a date or date-time property to a Horde_Date.
     *
     * @param Property|null $property  The property.
     *
     * @return Horde_Date|null  Null if the property is missing or is not a
     *                          date or date-time.
     */
    protected function _resolveTime(?Property $property): ?Horde_Date
    {
        if ($property === null || $this->_resolver === null) {
            return null;
        }

        $resolved = $this->_resolver->resolve($property);
        if ($resolved === null) {
            return null;
        }
        if ($resolved->unresolvedTzid !== null) {
            $this->_logger->notice(sprintf(
                'Unknown time zone "%s" in %s, using the server time zone.',
                $resolved->unresolvedTzid,
                $property->getName()
            ));
        }

        return new Horde_Date($resolved->timestamp);
    }

    /**
     * Return the text of a property: transfer encoding and charset undone,
     * iCalendar escapes resolved.
     *
     * @param Property $property  The text property.
     *
     * @return string  The UTF-8 text.
     */
    protected function _textValue(Property $property): string
    {
        $value = $property->getValue();

        if (strtoupper($property->getParameter('ENCODING')?->getValue() ?? '') === 'QUOTED-PRINTABLE') {
            $value = quoted_printable_decode($value);
        }

        $charset = $property->getParameter('CHARSET')?->getValue();
        if ($charset !== null && strcasecmp($charset, 'UTF-8') !== 0) {
            $value = HordeString::convertCharset($value, $charset, 'UTF-8');
        }

        return preg_replace_callback(
            '/\\\\([nN,;\\\\])/',
            static fn(array $m): string => strtolower($m[1]) === 'n' ? "\n" : $m[1],
            $value
        );
    }

    /**
     * Return if this is a request for an all day meeting.
     *
     * @return boolean
     */
    protected function _isAllDay()
    {
        if (!($this->starttime instanceof Horde_Date) || !($this->endtime instanceof Horde_Date)) {
            return false;
        }

        return ($this->starttime->hour == 0 && $this->starttime->min == 0 && $this->starttime->sec == 0
             && (($this->endtime->hour == 23 && $this->endtime->min == 59)
              || ($this->endtime->hour == 0 && $this->endtime->min == 0 && $this->endtime->sec == 0
               && ($this->endtime->mday > $this->starttime->mday
                || $this->endtime->month > $this->starttime->month
                || $this->endtime->year > $this->starttime->year))));
    }

}
