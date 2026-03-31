<?php

/**
 * Horde_ActiveSync_Message_AirSyncBaseBody::
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
 * @copyright 2011-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Horde_ActiveSync_Message_AirSyncBaseBody::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2011-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property integer   $type  The content type of the body.
 *     A Horde_ActiveSync::BODYPREF_TYPE_* constant.
 * @property integer   $estimateddatasize  The estimated size of the untruncated body.
 * @property integer   $truncated  The truncated flag. 0 == not truncated, 1 == truncated
 * @property mixed   $string|stream  $data  The body data.
 */
class Horde_ActiveSync_Message_AirSyncBaseBody extends Horde_ActiveSync_Message_Base
{
    /**
     * Property mapping
     *
     * @var array
     */
    protected $_mapping = [
        Horde_ActiveSync::AIRSYNCBASE_TYPE              => [self::KEY_ATTRIBUTE => 'type'],
        Horde_ActiveSync::AIRSYNCBASE_ESTIMATEDDATASIZE => [self::KEY_ATTRIBUTE => 'estimateddatasize'],
        Horde_ActiveSync::AIRSYNCBASE_TRUNCATED         => [self::KEY_ATTRIBUTE => 'truncated'],
        Horde_ActiveSync::AIRSYNCBASE_DATA              => [self::KEY_ATTRIBUTE => 'data'],
    ];

    /**
     * Property values
     *
     * @var array
     */
    protected $_properties = [
        'type'              => false,
        'estimateddatasize' => false,
        'truncated'         => false,
        'data'              => false,
    ];

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
                Horde_ActiveSync::AIRSYNCBASE_PREVIEW => [self::KEY_ATTRIBUTE => 'preview'],
            ];
            $this->_properties += [
                'preview' => false,
            ];
        }
    }

    public function __destruct()
    {
        $this->_properties['data'] = null;
    }

    /**
     * Return the message type.
     *
     * @return string
     */
    public function getClass()
    {
        return 'AirSyncBaseBody';
    }
}
