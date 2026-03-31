<?php

/**
 * Horde_ActiveSync_Message_Attendee
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
 * Horde_ActiveSync_Message_Attendee
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property string   $email   The attendee's email address.
 * @property string   $name    The attendee's name.
 * @property integer  $status  The attendee's status (a STATUS_* constant).
 * @property integer  $type    The attendee type (a TYPE_* constant)
 */
class Horde_ActiveSync_Message_Attendee extends Horde_ActiveSync_Message_Base
{
    /* Attendee Type Constants */
    public const TYPE_REQUIRED     = 1;
    public const TYPE_OPTIONAL     = 2;
    public const TYPE_RESOURCE     = 3;

    /* Attendee Status */
    public const STATUS_UNKNOWN    = 0;
    public const STATUS_TENTATIVE  = 2;
    public const STATUS_ACCEPT     = 3;
    public const STATUS_DECLINE    = 4;
    public const STATUS_NORESPONSE = 5;

    /**
     * Property mapping.
     *
     * @var array
     */
    protected $_mapping = [
        Horde_ActiveSync_Message_Appointment::POOMCAL_EMAIL =>  [self::KEY_ATTRIBUTE => 'email'],
        Horde_ActiveSync_Message_Appointment::POOMCAL_NAME  =>  [self::KEY_ATTRIBUTE => 'name'],
        Horde_ActiveSync_Message_Appointment::POOMCAL_ATTENDEESTATUS => [self::KEY_ATTRIBUTE => 'status'],
        Horde_ActiveSync_Message_Appointment::POOMCAL_ATTENDEETYPE => [self::KEY_ATTRIBUTE => 'type'],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'email' => false,
        'name'  => false,
        'status' => false,
        'type' => false,
    ];

    /**
     * Give concrete classes the chance to enforce rules on property values.
     *
     * @return boolean  True on success, otherwise false.
     */
    protected function _validateDecodedValues()
    {
        if ($this->_version == Horde_ActiveSync::VERSION_SIXTEEN
            && !empty($this->_properties['status'])) {
            return false;
        }

        return true;
    }

}
