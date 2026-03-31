<?php

/**
 * Horde_ActiveSync_Message_AirSyncBaseAdd::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2011-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Horde_ActiveSync_Message_AirSyncBaseAdd::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2011-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Message_AirSyncBaseAdd extends Horde_ActiveSync_Message_Base
{
    /**
     * Property mappings
     *
     * @var array
     */
    protected $_mapping = [
        Horde_ActiveSync::AIRSYNCBASE_CLIENTID => [self::KEY_ATTRIBUTE => 'clientid'],
        Horde_ActiveSync::AIRSYNCBASE_CONTENT => [self::KEY_ATTRIBUTE => 'content'],
        Horde_ActiveSync::AIRSYNCBASE_CONTENTID => [self::KEY_ATTRIBUTE => 'contentid'],
        Horde_ActiveSync::AIRSYNCBASE_CONTENTLOCATION => [self::KEY_ATTRIBUTE => 'contentlocation'],
        Horde_ActiveSync::AIRSYNCBASE_CONTENTTYPE => [self::KEY_ATTRIBUTE => 'contenttype'],
        Horde_ActiveSync::AIRSYNCBASE_DISPLAYNAME => [self::KEY_ATTRIBUTE => 'displayname'],
        Horde_ActiveSync::AIRSYNCBASE_ISINLINE => [self::KEY_ATTRIBUTE => 'isinline'],
        Horde_ActiveSync::AIRSYNCBASE_METHOD => [self::KEY_ATTRIBUTE => 'method'],
    ];

    /**
     * Property mapping.
     *
     * @var array
     */
    protected $_properties = [
        'clientid' => false,
        'content' => false,
        'contentid' => false,
        'contentlocation' => false,
        'contenttype' => false,
        'displayname' => false,
        'isinline' => false,
        'method' => false,
    ];

    /**
     * Return the type of message.
     *
     * @return string
     * @deprecated
     */
    public function getClass()
    {
        return 'AirSyncBaseAdd';
    }

}
