<?php

/**
 * Shared recurrence conversion for ActiveSync message types.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project (http://www.horde.org)
 * @package   ActiveSync
 */
class Horde_ActiveSync_Utils_Recurrence
{
  /** @see Horde_ActiveSync_Message_Appointment::$_dayOfWeekMap */
    protected static $_dayOfWeekMap = [
        Horde_Date::DATE_SUNDAY    => Horde_Date::MASK_SUNDAY,
        Horde_Date::DATE_MONDAY    => Horde_Date::MASK_MONDAY,
        Horde_Date::DATE_TUESDAY   => Horde_Date::MASK_TUESDAY,
        Horde_Date::DATE_WEDNESDAY => Horde_Date::MASK_WEDNESDAY,
        Horde_Date::DATE_THURSDAY  => Horde_Date::MASK_THURSDAY,
        Horde_Date::DATE_FRIDAY    => Horde_Date::MASK_FRIDAY,
        Horde_Date::DATE_SATURDAY  => Horde_Date::MASK_SATURDAY,
    ];

    /**
     * Detect MS-ASEMAIL InstanceType from a VEVENT.
     *
     * @return string  InstanceType value ('0'–'3').
     */
    public static function detectInstanceType(Horde_Icalendar_Vevent $vevent): string
    {
        $hasRecurrenceId = false;
        try {
            $recurrenceId = $vevent->getAttribute('RECURRENCE-ID');
            $hasRecurrenceId = !is_array($recurrenceId) && strlen((string) $recurrenceId);
        } catch (Horde_Icalendar_Exception $e) {
        }

        $hasRrule = false;
        try {
            $rrule = $vevent->getAttribute('RRULE');
            $hasRrule = !is_array($rrule) && strlen((string) $rrule);
        } catch (Horde_Icalendar_Exception $e) {
        }

        if ($hasRecurrenceId) {
            return $hasRrule ? '2' : '3';
        }

        if ($hasRrule) {
            return '1';
        }

        return '0';
    }

    /**
     * Parse RRULE from a VEVENT into Horde_Date_Recurrence.
     */
    public static function recurrenceFromVevent(
        Horde_Icalendar_Vevent $vevent
    ): ?Horde_Date_Recurrence {
        try {
            $rrule = $vevent->getAttribute('RRULE');
        } catch (Horde_Icalendar_Exception $e) {
            return null;
        }

        if (is_array($rrule) || !strlen((string) $rrule)) {
            return null;
        }

        try {
            $start = new Horde_Date($vevent->getAttribute('DTSTART'));
        } catch (Horde_Exception $e) {
            return null;
        }

        $recurrence = new Horde_Date_Recurrence($start);
        if (strpos($rrule, '=') !== false) {
            $recurrence->fromRRule20($rrule);
        } else {
            $recurrence->fromRRule10($rrule);
        }

        return $recurrence;
    }

    /**
     * Build a POOMCAL recurrence message from Horde_Date_Recurrence.
     *
     * @param array $options  Keys: logger, protocolversion, device, firstdayofweek.
     */
    public static function toCalendarRecurrence(
        Horde_Date_Recurrence $recurrence,
        array $options = []
    ): Horde_ActiveSync_Message_Recurrence {
        $r = new Horde_ActiveSync_Message_Recurrence($options);

        if (!empty($options['firstdayofweek'])
            && ($options['protocolversion'] ?? '') >= Horde_ActiveSync::VERSION_FOURTEENONE) {
            $r->firstdayofweek = $options['firstdayofweek'];
        }

        self::_mapHordeRecurrence($recurrence, $r);

        if (($options['protocolversion'] ?? '') >= Horde_ActiveSync::VERSION_FOURTEEN) {
            $r->calendartype = Horde_ActiveSync_Message_Recurrence::CALENDAR_TYPE_GREGORIAN;
        }

        return $r;
    }

    /**
     * Build a POOMMAIL recurrence message from Horde_Date_Recurrence.
     *
     * @param array $options  Keys: logger, protocolversion, device, firstdayofweek.
     */
    public static function toMeetingRequestRecurrence(
        Horde_Date_Recurrence $recurrence,
        array $options = []
    ): Horde_ActiveSync_Message_MeetingRequestRecurrence {
        $calendar = self::toCalendarRecurrence($recurrence, $options);

        return Horde_ActiveSync_Message_MeetingRequestRecurrence::fromCalendarRecurrence(
            $calendar,
            $options
        );
    }

    /**
     * Copy recurrence fields onto a recurrence message object.
     *
     * @param Horde_ActiveSync_Message_Recurrence|Horde_ActiveSync_Message_MeetingRequestRecurrence $target
     */
    protected static function _mapHordeRecurrence(
        Horde_Date_Recurrence $recurrence,
        $target
    ): void {
        switch ($recurrence->recurType) {
            case Horde_Date_Recurrence::RECUR_DAILY:
                $target->type = Horde_ActiveSync_Message_Recurrence::TYPE_DAILY;
                break;
            case Horde_Date_Recurrence::RECUR_WEEKLY:
                $target->type = Horde_ActiveSync_Message_Recurrence::TYPE_WEEKLY;
                $target->dayofweek = $recurrence->getRecurOnDays();
                break;
            case Horde_Date_Recurrence::RECUR_MONTHLY_DATE:
                $target->type = Horde_ActiveSync_Message_Recurrence::TYPE_MONTHLY;
                break;
            case Horde_Date_Recurrence::RECUR_MONTHLY_WEEKDAY:
                $target->type = Horde_ActiveSync_Message_Recurrence::TYPE_MONTHLY_NTH;
                $target->weekofmonth = ceil($recurrence->start->mday / 7);
                $target->dayofweek = self::$_dayOfWeekMap[$recurrence->start->dayOfWeek()];
                break;
            case Horde_Date_Recurrence::RECUR_YEARLY_DATE:
                $target->type = Horde_ActiveSync_Message_Recurrence::TYPE_YEARLY;
                $target->monthofyear = $recurrence->start->month;
                $target->dayofmonth = $recurrence->start->mday;
                break;
            case Horde_Date_Recurrence::RECUR_YEARLY_DAY:
                $target->type = Horde_ActiveSync_Message_Recurrence::TYPE_YEARLYNTH;
                $target->weekofmonth = ceil($recurrence->start->mday / 7);
                $target->monthofyear = $recurrence->start->month;
                break;
            case Horde_Date_Recurrence::RECUR_YEARLY_WEEKDAY:
                $target->type = Horde_ActiveSync_Message_Recurrence::TYPE_YEARLYNTH;
                $target->dayofweek = self::$_dayOfWeekMap[$recurrence->start->dayOfWeek()];
                $target->weekofmonth = ceil($recurrence->start->mday / 7);
                $target->monthofyear = $recurrence->start->month;
                break;
        }

        if (!empty($recurrence->recurInterval)) {
            $target->interval = $recurrence->recurInterval;
        }

        if ($recurrence->hasRecurCount()) {
            $target->occurrences = $recurrence->getRecurCount();
        } elseif ($recurrence->hasRecurEnd()) {
            $target->until = $recurrence->getRecurEnd();
        }
    }
}
