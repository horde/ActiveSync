<?php

/**
 * Horde_ActiveSync_Message_SendMailSource::
 *
 * Portions of this class were ported from the Z-Push project:
 * File      :   syncsendmail.php
 * Project   :   Z-Push
 * Descr     :   WBXML sendmail entities that
 *               can be parsed directly (as a
 *               stream) from WBXML.
 *               It is automatically decoded
 *               according to $mapping,
 *               and the Sync WBXML mappings.
 *
 * Created   :   30.01.2012
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
 * Horde_ActiveSync_Message_SendMailSource::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2013-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property string   $folderid    The item's folderid.
 * @property string   $itemid      The item's itemid.
 * @property string   $longid      The item's longid.
 * @property string   $instanceid  The item's instanceid.
 */
class Horde_ActiveSync_Message_SendMailSource extends Horde_ActiveSync_Message_Base
{
    public const COMPOSEMAIL_FOLDERID        = 'ComposeMail:FolderId';
    public const COMPOSEMAIL_ITEMID          = 'ComposeMail:ItemId';
    public const COMPOSEMAIL_LONGID          = 'ComposeMail:LongId';
    public const COMPOSEMAIL_INSTANCEID      = 'ComposeMail:InstanceId';

    /**
     * Property mapping
     *
     * @var array
     */
    protected $_mapping =  [
        self::COMPOSEMAIL_FOLDERID   => [self::KEY_ATTRIBUTE => 'folderid'],
        self::COMPOSEMAIL_ITEMID     => [self::KEY_ATTRIBUTE => 'itemid'],
        self::COMPOSEMAIL_LONGID     => [self::KEY_ATTRIBUTE => 'longid'],
        self::COMPOSEMAIL_INSTANCEID => [self::KEY_ATTRIBUTE => 'instanceid'],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'folderid'   => false,
        'itemid'     => false,
        'longid'     => false,
        'instanceid' => false,
    ];

    /**
     * Return this object's folder class
     *
     * @return string
     */
    public function getClass()
    {
        return 'SendMailSource';
    }

}
