<?php

/**
 * Horde_ActiveSync_Message_OofMessage::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 * @since     2.21.0
 */
/**
 * Horde_ActiveSync_Message_OofMessage::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 * @since     2.21.0
 *
 * @property boolean $internal
 * @property boolean $externalknown
 * @property boolean $externalunknown
 * @property boolean $enabled
 * @property string  $reply
 * @property string  $bodytype
 */
class Horde_ActiveSync_Message_OofMessage extends Horde_ActiveSync_Message_Base
{
    public $internal;
    public $externalknown;
    public $externalunknown;

    /**
     * Property mapping
     *
     * @var array
     */
    protected $_mapping =  [
        Horde_ActiveSync_Request_Settings::SETTINGS_APPLIESTOINTERNAL   => [self::KEY_ATTRIBUTE => 'internal'],
        Horde_ActiveSync_Request_Settings::SETTINGS_APPLIESTOEXTERNALKNOWN  => [self::KEY_ATTRIBUTE => 'externalknown'],
        Horde_ActiveSync_Request_Settings::SETTINGS_APPLIESTOEXTERNALUNKNOWN    => [self::KEY_ATTRIBUTE => 'externalunknown'],
        Horde_ActiveSync_Request_Settings::SETTINGS_ENABLED => [self::KEY_ATTRIBUTE => 'enabled'],
        Horde_ActiveSync_Request_Settings::SETTINGS_REPLYMESSAGE   => [self::KEY_ATTRIBUTE => 'reply'],
        Horde_ActiveSync_Request_Settings::SETTINGS_BODYTYPE => [self::KEY_ATTRIBUTE => 'bodytype'],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'enabled' => false,
        'reply'   => false,
        'bodytype' => false,
    ];

    /**
     * Checks to see if we should send an empty value.
     *
     * @param string $tag  The tag name
     *
     * @return boolean
     */
    protected function _checkSendEmpty($tag)
    {
        return true;
    }
}
