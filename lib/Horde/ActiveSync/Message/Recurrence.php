<?php
/**
 * Horde_ActiveSync_Message_Recurrence::
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
 * Horde_ActiveSync_Message_Recurrence::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property integer   $type
 * @property Horde_Date   $until
 * @property string   $occurrences
 * @property integer   $interval
 * @property integer   $dayofweek
 * @property integer   $dayofmonth
 * @property integer   $weekofmonth
 * @property integer   $monthofyear
 */
class Horde_ActiveSync_Message_Recurrence extends Horde_ActiveSync_Message_Base
{
    /* MS AS Recurrence types */
    const TYPE_DAILY       = 0;
    const TYPE_WEEKLY      = 1;
    const TYPE_MONTHLY     = 2;
    const TYPE_MONTHLY_NTH = 3;
    const TYPE_YEARLY      = 5;
    const TYPE_YEARLYNTH   = 6;

    const CALENDAR_TYPE_DEFAULT                  = 0;
    const CALENDAR_TYPE_GREGORIAN                = 1;
    const CALENDAR_TYPE_GREGORIAN_US             = 2;
    const CALENDAR_TYPE_JAPANESE                 = 3;
    const CALENDAR_TYPE_TAIWAN                   = 4;
    const CALENDAR_TYPE_KOREAN                   = 5;
    const CALENDAR_TYPE_HIJRI                    = 6;
    const CALENDAR_TYPE_THAI                     = 7;
    const CALENDAR_TYPE_HEBREW                   = 8;
    const CALENDAR_TYPE_GREGORIAN_FRENCH         = 9;
    const CALENDAR_TYPE_GREGORIAN_ARABIC         = 10;
    const CALENDAR_TYPE_GREGORIAN_TRANSLITERATED = 11;

    /* FDOW mapping for EAS 14.1 */
    const FIRSTDAY_SUNDAY            = 0;
    const FIRSTDAY_MONDAY            = 1;
    const FIRSTDAY_TUESDAY           = 2;
    const FIRSTDAY_WEDNESDAY         = 3;
    const FIRSTDAY_THURSDAY          = 4;
    const FIRSTDAY_FRIDAY            = 5;
    const FIRSTDAY_SATURDAY          = 6;

    /**
     * Property mapping.
     *
     * @var array
     */
    protected $_mapping = array (
        Horde_ActiveSync_Message_Appointment::POOMCAL_TYPE        => array (self::KEY_ATTRIBUTE => 'type'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_UNTIL       => array (self::KEY_ATTRIBUTE => 'until', self::KEY_TYPE => self::TYPE_DATE),
        Horde_ActiveSync_Message_Appointment::POOMCAL_OCCURRENCES => array (self::KEY_ATTRIBUTE => 'occurrences'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_INTERVAL    => array (self::KEY_ATTRIBUTE => 'interval'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_DAYOFWEEK   => array (self::KEY_ATTRIBUTE => 'dayofweek'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_DAYOFMONTH  => array (self::KEY_ATTRIBUTE => 'dayofmonth'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_WEEKOFMONTH => array (self::KEY_ATTRIBUTE => 'weekofmonth'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_MONTHOFYEAR => array (self::KEY_ATTRIBUTE => 'monthofyear')
    );

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = array(
        'type'        => false,
        'until'       => false,
        'occurrences' => false,
        'interval'    => false,
        'dayofweek'   => false,
        'dayofmonth'  => false,
        'weekofmonth' => false,
        'monthofyear' => false,
    );

    /**
     * Const'r
     *
     * @see Horde_ActiveSync_Message_Base::__construct()
     */
    public function __construct(array $options = array())
    {
        parent::__construct($options);

        if ($this->_version >= Horde_ActiveSync::VERSION_FOURTEEN) {
            $this->_mapping += array(
                Horde_ActiveSync_Message_Appointment::POOMCAL_CALENDARTYPE => array(self::KEY_ATTRIBUTE => 'calendartype'),
                Horde_ActiveSync_Message_Appointment::POOMCAL_ISLEAPMONTH => array(self::KEY_ATTRIBUTE => 'isleapmonth'));

            $this->_properties += array(
                'calendartype' => false,
                'isleapmonth' => false);
        }
        if ($this->_version >= Horde_ActiveSync::VERSION_FOURTEENONE) {
            $this->_mapping += array(
                Horde_ActiveSync_Message_Appointment::POOMCAL_FIRSTDAYOFWEEK => array(self::KEY_ATTRIBUTE => 'firstdayofweek')
            );
            $this->_properties += array(
                'firstdayofweek' => false
            );
        }
    }

    protected function _validateDecodedValues()
    {
        // Ensure the DOW setting is at least greater than 0.
        if ($this->_properties['type'] == TYPE_WEEKLY && $this->_properties['dayofweek'] < 1) {
            return false;
        }

        return true;
    }

    /**
     * Give concrete classes the chance to enforce rules before encoding
     * messages to send to the client.
     *
     * @return boolean  True if values were valid (or could be made valid).
     *     False if values are unable to be validated.
     * @since  2.31.0
     */
    protected function _preEncodeValidation()
    {
        // Ensure the DOW setting is at least greater than 0.
        if ($this->_properties['type'] == TYPE_WEEKLY && $this->_properties['dayofweek'] < 1) {
            return false;
        }

        return true;
    }
}
