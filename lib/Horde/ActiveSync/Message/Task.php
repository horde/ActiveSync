<?php

/**
 * Horde_ActiveSync_Message_Task::
 *
 * Portions of this class were ported from the Z-Push project:
 *   File      :   wbxml.php
 *   Project   :   Z-Push
 *   Descr     :   WBXML mapping file
 *
 *   Created   :   01.10.2007
 *
 *   � Zarafa Deutschland GmbH, www.zarafaserver.de
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
 * Horde_ActiveSync_Message_Task::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property boolean   $complete           Completion flag
 * @property Horde_Date   $datecompleted   The date the task was completed, in UTC.
 * @property Horde_Date   $utcduedate      The date this task is due, in UTC.
 * @property integer   $importance         The importance flag.
 * @property Horde_ActiveSync_Message_TaskRecurrence   $recurrence
 *                                      The recurrence object.
 * @property integer   $sensitivity        The sensitivity flag.
 * @property Horde_Date   $utcstartdate    The date this task starts, in UTC.
 * @property string   $subject             The task subject.
 * @property array   $categories           An array of categories.
 * @property string   $body                The task body (EAS Version < 12.0)
 * @property boolean   $bodytruncated      Truncation flag (EAS Version < 12.0)
 * @property Horde_ActiveSync_Message_AirSyncBaseBody   $airsyncbasebody
 *                                      The task body (EAS Version >= 12.0)
 */
class Horde_ActiveSync_Message_Task extends Horde_ActiveSync_Message_Base
{
    /* POOMTASKS */
    public const POOMTASKS_BODY           = 'POOMTASKS:Body';
    public const POOMTASKS_BODYSIZE       = 'POOMTASKS:BodySize';
    public const POOMTASKS_BODYTRUNCATED  = 'POOMTASKS:BodyTruncated';
    public const POOMTASKS_CATEGORIES     = 'POOMTASKS:Categories';
    public const POOMTASKS_CATEGORY       = 'POOMTASKS:Category';
    public const POOMTASKS_COMPLETE       = 'POOMTASKS:Complete';
    public const POOMTASKS_DATECOMPLETED  = 'POOMTASKS:DateCompleted';
    public const POOMTASKS_DUEDATE        = 'POOMTASKS:DueDate';
    public const POOMTASKS_UTCDUEDATE     = 'POOMTASKS:UtcDueDate';
    public const POOMTASKS_IMPORTANCE     = 'POOMTASKS:Importance';
    public const POOMTASKS_RECURRENCE     = 'POOMTASKS:Recurrence';
    public const POOMTASKS_TYPE           = 'POOMTASKS:Type';
    public const POOMTASKS_START          = 'POOMTASKS:Start';
    public const POOMTASKS_UNTIL          = 'POOMTASKS:Until';
    public const POOMTASKS_OCCURRENCES    = 'POOMTASKS:Occurrences';
    public const POOMTASKS_INTERVAL       = 'POOMTASKS:Interval';
    public const POOMTASKS_DAYOFWEEK      = 'POOMTASKS:DayOfWeek';
    public const POOMTASKS_DAYOFMONTH     = 'POOMTASKS:DayOfMonth';
    public const POOMTASKS_WEEKOFMONTH    = 'POOMTASKS:WeekOfMonth';
    public const POOMTASKS_MONTHOFYEAR    = 'POOMTASKS:MonthOfYear';
    public const POOMTASKS_REGENERATE     = 'POOMTASKS:Regenerate';
    public const POOMTASKS_DEADOCCUR      = 'POOMTASKS:DeadOccur';
    public const POOMTASKS_REMINDERSET    = 'POOMTASKS:ReminderSet';
    public const POOMTASKS_REMINDERTIME   = 'POOMTASKS:ReminderTime';
    public const POOMTASKS_SENSITIVITY    = 'POOMTASKS:Sensitivity';
    public const POOMTASKS_STARTDATE      = 'POOMTASKS:StartDate';
    public const POOMTASKS_UTCSTARTDATE   = 'POOMTASKS:UtcStartDate';
    public const POOMTASKS_SUBJECT        = 'POOMTASKS:Subject';
    public const POOMTASKS_RTF            = 'POOMTASKS:Rtf';

    // EAS 12.0
    public const POOMTASKS_ORDINALDATE    = 'POOMTASKS:OrdinalDate';
    public const POOMTASKS_SUBORDINALDATE = 'POOMTASKS:SubOrdinalDate';

    // EAS 14
    public const POOMTASKS_CALENDARTYPE   = 'POOMTASKS:CalendarType';
    public const POOMTASKS_ISLEAPMONTH    = 'POOMTASKS:IsLeapMonth';
    public const POOMTASKS_FIRSTDAYOFWEEK = 'POOMTASKS:FirstDayOfWeek';

    public const TASK_COMPLETE_TRUE      = 1;
    public const TASK_COMPLETE_FALSE     = 0;

    public const IMPORTANCE_LOW          = 0;
    public const IMPORTANCE_NORMAL       = 1;
    public const IMPORTANCE_HIGH         = 2;

    public const REMINDER_SET_FALSE      = 0;
    public const REMINDER_SET_TRUE       = 1;

    /**
     * DOW mapping for DATE to MASK
     *
     * @var array
     */
    protected $_dayOfWeekMap = [
        Horde_Date::DATE_SUNDAY    => Horde_Date::MASK_SUNDAY,
        Horde_Date::DATE_MONDAY    => Horde_Date::MASK_MONDAY,
        Horde_Date::DATE_TUESDAY   => Horde_Date::MASK_TUESDAY,
        Horde_Date::DATE_WEDNESDAY => Horde_Date::MASK_WEDNESDAY,
        Horde_Date::DATE_THURSDAY  => Horde_Date::MASK_THURSDAY,
        Horde_Date::DATE_FRIDAY    => Horde_Date::MASK_FRIDAY,
        Horde_Date::DATE_SATURDAY  => Horde_Date::MASK_SATURDAY,
    ];

    /**
     * Property mapping
     *
     * @var array
     */
    protected $_mapping =  [
        self::POOMTASKS_COMPLETE      =>  [self::KEY_ATTRIBUTE => 'complete'],
        self::POOMTASKS_DATECOMPLETED =>  [self::KEY_ATTRIBUTE => 'datecompleted', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        self::POOMTASKS_DUEDATE       =>  [self::KEY_ATTRIBUTE => 'duedate', self::KEY_TYPE => self::TYPE_DATE_LOCAL],
        self::POOMTASKS_UTCDUEDATE    =>  [self::KEY_ATTRIBUTE => 'utcduedate', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        self::POOMTASKS_IMPORTANCE    =>  [self::KEY_ATTRIBUTE => 'importance'],
        self::POOMTASKS_RECURRENCE    =>  [self::KEY_ATTRIBUTE => 'recurrence', self::KEY_TYPE => 'Horde_ActiveSync_Message_TaskRecurrence'],
        self::POOMTASKS_REMINDERSET   =>  [self::KEY_ATTRIBUTE => 'reminderset'],
        self::POOMTASKS_REMINDERTIME  =>  [self::KEY_ATTRIBUTE => 'remindertime', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        self::POOMTASKS_SENSITIVITY   =>  [self::KEY_ATTRIBUTE => 'sensitiviy'],
        self::POOMTASKS_STARTDATE     =>  [self::KEY_ATTRIBUTE => 'startdate', self::KEY_TYPE => self::TYPE_DATE_LOCAL],
        self::POOMTASKS_UTCSTARTDATE  =>  [self::KEY_ATTRIBUTE => 'utcstartdate', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        self::POOMTASKS_SUBJECT       =>  [self::KEY_ATTRIBUTE => 'subject'],
        self::POOMTASKS_CATEGORIES    =>  [self::KEY_ATTRIBUTE => 'categories', self::KEY_VALUES => self::POOMTASKS_CATEGORY],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'subject'       => false,
        'importance'    => false,
        'categories'    => [],
        'startdate'     => false,
        'duedate'       => false,
        'utcduedate'    => false,
        'complete'      => false,
        'datecompleted' => false,
        'remindertime'  => false,
        'sensitiviy'    => false,
        'reminderset'   => false,
        'deadoccur'     => false,
        'recurrence'    => false,
        'regenerate'    => false,
        'sensitiviy'    => false,
        'utcstartdate'  => false,
    ];

    /**
     * Const'r
     *
     * @see Horde_ActiveSync_Message_Base::__construct()
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        if ($this->_version < Horde_ActiveSync::VERSION_TWELVE) {
            $this->_mapping += [
                self::POOMTASKS_BODY          => [self::KEY_ATTRIBUTE => 'body'],
                self::POOMTASKS_RTF           => [self::KEY_ATTRIBUTE => 'rtf'],
                self::POOMTASKS_BODYTRUNCATED => [self::KEY_ATTRIBUTE => 'bodytruncated'],
            ];

            $this->_properties += [
                'body' => false,
                'rtf'  => false,
                'bodytruncated' => 0,
            ];
        } else {
            $this->_mapping += [
                Horde_ActiveSync::AIRSYNCBASE_BODY => [self::KEY_ATTRIBUTE => 'airsyncbasebody', self::KEY_TYPE => 'Horde_ActiveSync_Message_AirSyncBaseBody'],
            ];

            $this->_properties += [
                'airsyncbasebody' => false,
            ];
        }
    }

    /**
     * Set the importance
     *
     * @param integer $importance  A IMPORTANCE_* flag
     */
    public function setImportance($importance)
    {
        if (is_null($importance)) {
            $importance = self::IMPORTANCE_NORMAL;
        }

        $this->_properties['importance'] = $importance;
    }

    /**
     * Get the task importance level
     *
     * @return integer  A IMPORTANCE_* constant
     */
    public function getImportance()
    {
        return $this->_getAttribute('importance', self::IMPORTANCE_NORMAL);
    }

    /**
     * Set the reminder datetime
     *
     * @param Horde_Date $datetime  The time to trigger the alarm in local tz.
     */
    public function setReminder(Horde_Date $datetime)
    {
        $this->_properties['remindertime'] = $datetime;
        $this->_properties['reminderset'] = self::REMINDER_SET_TRUE;
    }

    /**
     * Get the reminder time.
     *
     * @return Horde_Date  in local tz
     */
    public function getReminder()
    {
        if (!$this->_getAttribute('reminderset')) {
            return false;
        }
        return $this->_getAttribute('remindertime');
    }

    /**
     * Set recurrence information for this task
     *
     * @param Horde_Date_Recurrence $recurrence
     */
    public function setRecurrence(Horde_Date_Recurrence $recurrence)
    {
        $r = Horde_ActiveSync::messageFactory('TaskRecurrence');

        // Map the type fields
        switch ($recurrence->recurType) {
            case Horde_Date_Recurrence::RECUR_DAILY:
                $r->type = Horde_ActiveSync_Message_Recurrence::TYPE_DAILY;
                break;
            case Horde_Date_Recurrence::RECUR_WEEKLY:
                $r->type = Horde_ActiveSync_Message_Recurrence::TYPE_WEEKLY;
                $r->dayofweek = $recurrence->getRecurOnDays();
                break;
            case Horde_Date_Recurrence::RECUR_MONTHLY_DATE:
                $r->type = Horde_ActiveSync_Message_Recurrence::TYPE_MONTHLY;
                $r->dayofmonth = $recurrence->start->mday;
                break;
            case Horde_Date_Recurrence::RECUR_MONTHLY_WEEKDAY:
                $r->type = Horde_ActiveSync_Message_Recurrence::TYPE_MONTHLY_NTH;
                $r->weekofmonth = ceil($recurrence->start->mday / 7);
                $r->dayofweek = $this->_dayOfWeekMap[$recurrence->start->dayOfWeek()];
                break;
            case Horde_Date_Recurrence::RECUR_YEARLY_DATE:
                $r->type = Horde_ActiveSync_Message_Recurrence::TYPE_YEARLY;
                break;
            case Horde_Date_Recurrence::RECUR_YEARLY_WEEKDAY:
                $r->type = Horde_ActiveSync_Message_Recurrence::TYPE_YEARLYNTH;
                $r->dayofweek = $this->_dayOfWeekMap[$recurrence->start->dayOfWeek()];
                $r->weekofmonth = ceil($recurrence->start->mday / 7);
                $r->monthofyear = $recurrence->start->month;
                break;
        }
        if (!empty($recurrence->recurInterval)) {
            $r->interval = $recurrence->recurInterval;
        }

        // AS messages can only have one or the other (or none), not both
        if ($recurrence->hasRecurCount()) {
            $r->occurrences = $recurrence->getRecurCount();
        } elseif ($recurrence->hasRecurEnd()) {
            $r->until = $recurrence->getRecurEnd();
        }

        // Set the start of the recurrence series.
        $r->start = clone $this->duedate;

        $this->_properties['recurrence'] = $r;
    }

    /**
     * Obtain a recurrence object. Note this returns a Horde_Date_Recurrence
     * object, not Horde_ActiveSync_Message_Recurrence.
     *
     * @return Horde_Date_Recurrence
     */
    public function getRecurrence()
    {
        if (!$recurrence = $this->_getAttribute('recurrence')) {
            return false;
        }

        $d = clone($this->getDueDate());
        //  $d->setTimezone($this->getTimezone());

        $rrule = new Horde_Date_Recurrence($d);

        /* Map MS AS type field to Horde_Date_Recurrence types */
        switch ($recurrence->type) {
            case Horde_ActiveSync_Message_Recurrence::TYPE_DAILY:
                $rrule->setRecurType(Horde_Date_Recurrence::RECUR_DAILY);
                break;
            case Horde_ActiveSync_Message_Recurrence::TYPE_WEEKLY:
                $rrule->setRecurType(Horde_Date_Recurrence::RECUR_WEEKLY);
                $rrule->setRecurOnDay($recurrence->dayofweek);
                break;
            case Horde_ActiveSync_Message_Recurrence::TYPE_MONTHLY:
                $rrule->setRecurType(Horde_Date_Recurrence::RECUR_MONTHLY_DATE);
                break;
            case Horde_ActiveSync_Message_Recurrence::TYPE_MONTHLY_NTH:
                $rrule->setRecurType(Horde_Date_Recurrence::RECUR_MONTHLY_WEEKDAY);
                $rrule->setRecurOnDay($recurrence->dayofweek);
                break;
            case Horde_ActiveSync_Message_Recurrence::TYPE_YEARLY:
                $rrule->setRecurType(Horde_Date_Recurrence::RECUR_YEARLY_DATE);
                break;
            case Horde_ActiveSync_Message_Recurrence::TYPE_YEARLYNTH:
                $rrule->setRecurType(Horde_Date_Recurrence::RECUR_YEARLY_WEEKDAY);
                $rrule->setRecurOnDay($recurrence->dayofweek);
                break;
        }

        if ($rcnt = $recurrence->occurrences) {
            $rrule->setRecurCount($rcnt);
        }
        if ($runtil = $recurrence->until) {
            $rrule->setRecurEnd(new Horde_Date($runtil));
        }
        if ($interval = $recurrence->interval) {
            $rrule->setRecurInterval($interval);
        }

        return $rrule;
    }

    /**
     * Return this object's folder class
     *
     * @return string
     */
    public function getClass()
    {
        return 'Tasks';
    }

    /**
     * Check if a field should be sent to the device even if it is empty.
     *
     * @param string $tag  The field tag.
     *
     * @return boolean
     */
    protected function _checkSendEmpty($tag)
    {
        if ($tag == self::POOMTASKS_BODYTRUNCATED && $this->bodysize > 0) {
            return true;
        }

        return false;
    }

}
