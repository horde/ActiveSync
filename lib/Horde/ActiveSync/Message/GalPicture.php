<?php

/**
 * Horde_ActiveSync_Message_GalPicture::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2013-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Horde_ActiveSync_Message_Picture:: Encapsulate the data to send in a
 * GAL response.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2013-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 */
class Horde_ActiveSync_Message_GalPicture extends Horde_ActiveSync_Message_Base
{
    /**
     * Property mapping
     *
     * @var array
     */
    protected $_mapping = [
        Horde_ActiveSync::GAL_STATUS => [self::KEY_ATTRIBUTE => 'status'],
        Horde_ActiveSync::GAL_DATA   => [self::KEY_ATTRIBUTE => 'data'],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'status' => false,
        'data'   => false,
    ];

}
