<?php

/**
 * Horde_ActiveSync_Message_Flag::
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
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Horde_ActiveSync_Message_Flag::
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
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property integer      $flagstatus
 * @property integer      $flagtype
 * @property Horde_Date   $startdate
 * @property Horde_Date   $utcstartdate
 * @property Horde_Date   $duedate
 * @property Horde_Date   $utcduedate
 * @property Horde_Date   $datecompleted
 * @property integer      $reminderset
 * @property integer      $remindertime
 * @property string       $subject
 * @property Horde_Date   $ordinaldate
 * @property string       $subordinaldate
 * @property integer      $completetime
 */
class Horde_ActiveSync_Message_Flag extends Horde_ActiveSync_Message_Base
{
    public const POOMMAIL_FLAGSTATUS   = 'POOMMAIL:FlagStatus';
    public const POOMMAIL_FLAGTYPE     = 'POOMMAIL:FlagType';
    public const POOMMAIL_COMPLETETIME = 'POOMMAIL:CompleteTime';

    public const FLAG_STATUS_CLEAR     = 0;
    public const FLAG_STATUS_COMPLETE  = 1;
    public const FLAG_STATUS_ACTIVE    = 2;

    /**
     * Property mapping
     *
     * @var array
     */
    protected $_mapping = [
        self::POOMMAIL_FLAGSTATUS                               => [self::KEY_ATTRIBUTE => 'flagstatus'],
        self::POOMMAIL_FLAGTYPE                                 => [self::KEY_ATTRIBUTE => 'flagtype'],
        Horde_ActiveSync_Message_Task::POOMTASKS_STARTDATE      => [self::KEY_ATTRIBUTE => 'startdate', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Task::POOMTASKS_UTCSTARTDATE   => [self::KEY_ATTRIBUTE => 'utcstartdate', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Task::POOMTASKS_DUEDATE        => [self::KEY_ATTRIBUTE => 'duedate', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Task::POOMTASKS_UTCDUEDATE     => [self::KEY_ATTRIBUTE => 'utcduedate', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Task::POOMTASKS_DATECOMPLETED  => [self::KEY_ATTRIBUTE => 'datecompleted', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Task::POOMTASKS_REMINDERSET    => [self::KEY_ATTRIBUTE => 'reminderset'],
        Horde_ActiveSync_Message_Task::POOMTASKS_REMINDERTIME   => [self::KEY_ATTRIBUTE => 'remindertime', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Task::POOMTASKS_SUBJECT        => [self::KEY_ATTRIBUTE => 'subject'],
        Horde_ActiveSync_Message_Task::POOMTASKS_ORDINALDATE    => [self::KEY_ATTRIBUTE => 'ordinaldate', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Task::POOMTASKS_SUBORDINALDATE => [self::KEY_ATTRIBUTE => 'subordinaldate'],
        self::POOMMAIL_COMPLETETIME                             => [self::KEY_ATTRIBUTE => 'completetime'],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'flagstatus'     => false,
        'flagtype'       => false,
        'startdate'      => false,
        'utcstartdate'   => false,
        'duedate'        => false,
        'utcduedate'     => false,
        'datecompleted'  => false,
        'reminderset'    => false,
        'remindertime'   => false,
        'subject'        => false,
        'ordinaldate'    => false,
        'subordinaldate' => false,
        'completetime'   => false,
    ];

    /**
     * Return the message class.
     *
     * @return string
     */
    public function getClass()
    {
        return 'Flag';
    }

}
