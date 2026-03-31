<?php

/**
 * Horde_ActiveSync_Message_TaskRecurrence::
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
 * Horde_ActiveSync_Message_TaskRecurrence::
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
 * @property integer   $type
 * @property Horde_Date   $start
 * @property Horde_Date   $until
 * @property string   $occurrences
 * @property integer   $interval
 * @property integer   $dayofweek
 * @property integer   $dayofmonth
 * @property integer   $weekofmonth
 * @property integer   $monthofyear
 */
class Horde_ActiveSync_Message_TaskRecurrence extends Horde_ActiveSync_Message_Base
{
    /* MS AS Recurrence types */
    public const TYPE_DAILY       = 0;
    public const TYPE_WEEKLY      = 1;
    public const TYPE_MONTHLY     = 2;
    public const TYPE_MONTHLY_NTH = 3;
    public const TYPE_YEARLY      = 5;
    public const TYPE_YEARLYNTH   = 6;

    /**
     * Property mapping.
     *
     * @var array
     */
    protected $_mapping =  [
        Horde_ActiveSync_Message_Task::POOMTASKS_REGENERATE     =>  [self::KEY_ATTRIBUTE => 'regenerate'],
        Horde_ActiveSync_Message_Task::POOMTASKS_INTERVAL       => [self::KEY_ATTRIBUTE => 'interval'],
        Horde_ActiveSync_Message_Task::POOMTASKS_START          => [self::KEY_ATTRIBUTE => 'start', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Task::POOMTASKS_TYPE           => [self::KEY_ATTRIBUTE => 'type'],
        Horde_ActiveSync_Message_Task::POOMTASKS_UNTIL          => [self::KEY_ATTRIBUTE => 'until', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync_Message_Task::POOMTASKS_OCCURRENCES    => [self::KEY_ATTRIBUTE => 'occurrences'],
        Horde_ActiveSync_Message_Task::POOMTASKS_DAYOFWEEK      => [self::KEY_ATTRIBUTE => 'dayofweek'],
        Horde_ActiveSync_Message_Task::POOMTASKS_DAYOFMONTH     => [self::KEY_ATTRIBUTE => 'dayofmonth'],
        Horde_ActiveSync_Message_Task::POOMTASKS_WEEKOFMONTH    => [self::KEY_ATTRIBUTE => 'weekofmonth'],
        Horde_ActiveSync_Message_Task::POOMTASKS_MONTHOFYEAR    => [self::KEY_ATTRIBUTE => 'monthofyear'],
        Horde_ActiveSync_Message_Task::POOMTASKS_DEADOCCUR      =>  [self::KEY_ATTRIBUTE => 'deadoccur'],
        Horde_ActiveSync_Message_Task::POOMTASKS_CALENDARTYPE   => [self::KEY_ATTRIBUTE => 'calendartype'],
        Horde_ActiveSync_Message_Task::POOMTASKS_ISLEAPMONTH    => [self::KEY_ATTRIBUTE => 'isleapmonth'],
        Horde_ActiveSync_Message_Task::POOMTASKS_FIRSTDAYOFWEEK => [self::KEY_ATTRIBUTE => 'firstdayofweek'],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'type'           => false,
        'start'          => false,
        'until'          => false,
        'occurrences'    => false,
        'interval'       => false,
        'dayofweek'      => false,
        'dayofmonth'     => false,
        'weekofmonth'    => false,
        'monthofyear'    => false,
        'regenerate'     => false,
        'deadoccur'      => false,
        'calendartype'   => false,
        'isleapmonth'    => false,
        'firstdayofweek' => false,
    ];

}
