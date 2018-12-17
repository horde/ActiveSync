<?php
/**
 * Horde_ActiveSync_Message_Exception::
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
 *            NOTE: According to sec. 8 of the GENERAL PUBLIC LICENSE (GPL),
 *            Version 2, the distribution of the Horde_ActiveSync module in or
 *            to the United States of America is excluded from the scope of this
 *            license.
 * @copyright 2010-2017 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Horde_ActiveSync_Message_Exception::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *            NOTE: According to sec. 8 of the GENERAL PUBLIC LICENSE (GPL),
 *            Version 2, the distribution of the Horde_ActiveSync module in or
 *            to the United States of America is excluded from the scope of this
 *            license.
 * @copyright 2010-2017 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property mixed   $string|Horde_Date timezone
 * @property Horde_Date   $dtstamp
 * @property Horde_Date   $starttime
 * @property string       $subject
 * @property string       $organizername
 * @property string       $organizeremail
 * @property string       $location
 * @property Horde_Date   $endtime
 * @property integer      $sensitivity
 * @property integer      $busystatus
 * @property integer      $alldayevent
 * @property integer      $reminder
 * @property integer      $meetingstatus
 * @property Horde_Date   $exceptionstarttime (EAS <= 14.1 only).
 * @property integer      $deleted
 * @property array        $attendees
 * @property array        $categories
 * @property Horde_Date   $instanceid (EAS >= 16.0 only).
 */
class Horde_ActiveSync_Message_Exception extends Horde_ActiveSync_Message_Appointment
{
    /**
     * Property mapping.
     *
     * @var array
     */
    protected $_mapping = array(
        Horde_ActiveSync_Message_Appointment::POOMCAL_TIMEZONE           => array(self::KEY_ATTRIBUTE => 'timezone'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_DTSTAMP            => array(self::KEY_ATTRIBUTE => 'dtstamp', self::KEY_TYPE => self::TYPE_DATE),
        Horde_ActiveSync_Message_Appointment::POOMCAL_STARTTIME          => array(self::KEY_ATTRIBUTE => 'starttime', self::KEY_TYPE => self::TYPE_DATE),
        Horde_ActiveSync_Message_Appointment::POOMCAL_SUBJECT            => array(self::KEY_ATTRIBUTE => 'subject'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_ORGANIZERNAME      => array(self::KEY_ATTRIBUTE => 'organizername'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_ORGANIZEREMAIL     => array (self::KEY_ATTRIBUTE => 'organizeremail'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_ENDTIME            => array(self::KEY_ATTRIBUTE => 'endtime', self::KEY_TYPE => self::TYPE_DATE),
        Horde_ActiveSync_Message_Appointment::POOMCAL_SENSITIVITY        => array(self::KEY_ATTRIBUTE => 'sensitivity'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_BUSYSTATUS         => array(self::KEY_ATTRIBUTE => 'busystatus'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_ALLDAYEVENT        => array(self::KEY_ATTRIBUTE => 'alldayevent'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_REMINDER           => array(self::KEY_ATTRIBUTE => 'reminder'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_MEETINGSTATUS      => array(self::KEY_ATTRIBUTE => 'meetingstatus'),
        Horde_ActiveSync_Message_Appointment::POOMCAL_ATTENDEES          => array(self::KEY_ATTRIBUTE => 'attendees', self::KEY_TYPE => 'Horde_ActiveSync_Message_Attendee', self::KEY_VALUES => Horde_ActiveSync_Message_Appointment::POOMCAL_ATTENDEE),
        Horde_ActiveSync_Message_Appointment::POOMCAL_CATEGORIES         => array(self::KEY_ATTRIBUTE => 'categories', self::KEY_VALUES => Horde_ActiveSync_Message_Appointment::POOMCAL_CATEGORY),
        Horde_ActiveSync_Message_Appointment::POOMCAL_DELETED            => array(self::KEY_ATTRIBUTE => 'deleted'),
    );

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = array(
        'timezone'           => false,
        'dtstamp'            => false,
        'starttime'          => false,
        'subject'            => false,
        'organizeremail'     => false,
        'organizername'      => false,
        'endtime'            => false,
        'sensitivity'        => false,
        'busystatus'         => false,
        'alldayevent'        => false,
        'reminder'           => false,
        'meetingstatus'      => false,
        'deleted'            => false,
        'attendees'          => array(),
        'categories'         => array(),
    );

    /**
     * The parent appointment for this exception. Only set when client code
     * retrieves list of exceptions from the parent.
     *
     * @var Horde_ActiveSync_Message_Appointment
     */
    private $_parentAppointment = null;

    /**
     * Const'r
     *
     * @see Horde_ActiveSync_Message_Base::__construct()
     */
    public function __construct(array $options = array())
    {
        parent::__construct($options);

        // Removed in 16.0
        if ($this->_version <= Horde_ActiveSync::VERSION_FOURTEENONE) {
            $this->_mapping += array(
                Horde_ActiveSync_Message_Appointment::POOMCAL_EXCEPTIONSTARTTIME => array(self::KEY_ATTRIBUTE => 'exceptionstarttime', self::KEY_TYPE => self::TYPE_DATE),
                Horde_ActiveSync_Message_Appointment::POOMCAL_LOCATION           => array(self::KEY_ATTRIBUTE => 'location'),
            );
            $this->_properties += array(
                'exceptionstarttime' => false,
                'location' => false,
            );
        }
        if ($this->_version >= Horde_ActiveSync::VERSION_SIXTEEN) {
            $this->_mapping += array(
                Horde_ActiveSync::AIRSYNCBASE_LOCATION => array(self::KEY_ATTRIBUTE => 'location', self::KEY_TYPE => Horde_ActiveSync_Message_AirSyncBaseLocation),
                Horde_ActiveSync::AIRSYNCBASE_INSTANCEID => array(self::KEY_ATTRIBUTE => 'instanceid', self::KEY_TYPE => self::TYPE_DATE)
            );
            $this->_properties += array(
                'location' => false,
                'instanceid' => false,
            );
        }
    }

    /**
     * Sets the DELETED field on this exception
     *
     * @param boolean $flag
     */
    public function setDeletedFlag($flag)
    {
        $this->_properties['deleted'] = $flag;
    }

    /**
     * Exception start time. This field seems to have different usages depending
     * on if this is a command request from the client or from the server. If
     * it's part of a request from client, then it represents the date of the
     * exception that is to be deleted. If it is from server, it represents the
     * date of the *original* recurring event.
     *
     * @return Horde_Date  The exception's start time
     * @deprecated
     */
    public function getExceptionStartTime()
    {
        return $this->_getAttribute('exceptionstarttime');
    }

    /**
     * Set the exceptionStartTime value.
     *
     * @param Horde_Date $date  The exceptionStartTime.
     * @deprecated
     */
    public function setExceptionStartTime($date)
    {
        $this->exceptionstarttime = $date;
    }

    public function setParentAppointment(Horde_ActiveSync_Message_Appointment $parent)
    {
        $this->_parentAppointment = $parent;
    }

    /**
     * Get the appointment's time data
     *
     * @return array  An array containing:
     *   - start: (Horde_Date) The start time.
     *   - end: (Horde_Date) The end time.
     *   - allday: (boolean) If true, this is an allday event.
     *  @deprecated
     */
    public function getDatetime()
    {
        return array(
            'start' => $this->getStarttime(),
            'end' => $this->getEndtime(),
            'allday' => !empty($this->_properties['alldayevent']) ? true : false
        );
    }

    /**
     * Return the starttime for this exception, honoring MS-ASCAL 2.2.2.42 and
     * returning the parent's starttime if emtpy.
     *
     * @return Horde_Date
     */
    public function getStarttime()
    {
        if (empty($this->_properties['starttime'])) {
            return $this->_parentAppointment->getStarttime();
        }
        return $this->_properties['starttime'];
    }

    /**
     * Return the endtime for this exception, honoring MS-ASCAL 2.2.2.42 and
     * returning the parent's endtime if emtpy.
     *
     * @return Horde_Date
     */
    public function getEndtime()
    {
        if (empty($this->_properties['endtime'])) {
            return $this->_parentAppointment->getEndtime();
        }
        return $this->_properties['endtime'];
    }

    /**
     * Give concrete classes the chance to enforce rules before encoding
     * messages to send to the client.
     *
     * @return boolean  True if values were valid (or could be made valid).
     *     False if values are unable to be validated.
     */
    protected function _preEncodeValidation()
    {
        if ($this->_properties['alldayevent']) {
            if ($this->_properties['starttime']) {
                $this->_properties['starttime']->hour = 0;
                $this->_properties['starttime']->min = 0;
                $this->_properties['starttime']->sec = 0;
            }
            if ($this->_properties['endtime']) {
                $this->_properties['endtime']->hour = 0;
                $this->_properties['endtime']->min = 0;
                $this->_properties['endtime']->sec = 0;
            }

            // For EAS 16, timezone cannot be sent for allday events. The
            // event is interpreted to be on the given date regardless of
            // timezone...as such, we need to manually convert to UTC here
            // and (re)set the date to be sure it matches the desired date.
            if ($this->_version == Horde_ActiveSync::VERSION_SIXTEEN) {
                $this->_properties['timezone'] = false;
                $mday = $this->_properties['starttime']->mday;
                if ($this->_properties['starttime']) {
                    $this->_properties['starttime']->setTimezone('UTC');
                    $this->_properties['starttime']->mday = $mday;
                    $this->_properties['starttime']->hour = 0;
                    $this->_properties['starttime']->min = 0;
                    $this->_properties['starttime']->sec = 0;
                }

                if ($this->_properties['endtime']) {
                    $mday = $this->_properties['endtime']->mday;
                    $this->_properties['endtime']->setTimezone('UTC');
                    $this->_properties['endtime']->mday = $mday;
                    $this->_properties['endtime']->hour = 0;
                    $this->_properties['endtime']->min = 0;
                    $this->_properties['endtime']->sec = 0;
                }
            }
        }

        return true;
    }

}
