<?php

/**
 * Horde_ActiveSync_Message_Attachment
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
 * Horde_ActiveSync_Message_Attachment
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property string   $attmethod    The attachment method.
 * @property integer   $attsize     The attachment size.
 * @property string   $displayname  The attachment's display name.
 * @property string   $attname      The attachment's name.
 * @property string   $attoid       The ObjectID of the attachment.
 * @property integer   $attremoved  @todo
 */
class Horde_ActiveSync_Message_Attachment extends Horde_ActiveSync_Message_Base
{
    /* Wbxml constants */
    public const POOMMAIL_ATTNAME           = 'POOMMAIL:AttName';
    public const POOMMAIL_ATTSIZE           = 'POOMMAIL:AttSize';
    public const POOMMAIL_ATTOID            = 'POOMMAIL:AttOid';
    public const POOMMAIL_ATTMETHOD         = 'POOMMAIL:AttMethod';
    public const POOMMAIL_ATTREMOVED        = 'POOMMAIL:AttRemoved';
    public const POOMMAIL_DISPLAYNAME       = 'POOMMAIL:DisplayName';

    /* Attachement types */
    public const ATT_TYPE_NORMAL   = 1;
    public const ATT_TYPE_EMBEDDED = 5;
    public const ATT_TYPE_OLE      = 6;

    /**
     * Property mappings
     *
     * @var array
     */
    protected $_mapping = [
        self::POOMMAIL_ATTMETHOD   =>  [self::KEY_ATTRIBUTE => 'attmethod'],
        self::POOMMAIL_ATTSIZE     =>  [self::KEY_ATTRIBUTE => 'attsize'],
        self::POOMMAIL_DISPLAYNAME =>  [self::KEY_ATTRIBUTE => 'displayname'],
        self::POOMMAIL_ATTNAME     =>  [self::KEY_ATTRIBUTE => 'attname'],
        self::POOMMAIL_ATTOID      =>  [self::KEY_ATTRIBUTE => 'attoid'],
        self::POOMMAIL_ATTREMOVED  =>  [self::KEY_ATTRIBUTE => 'attremoved'],
    ];

    /**
     * Property values
     *
     * @var array
     */
    protected $_properties = [
        'attmethod'   => false,
        'attsize'     => false,
        'displayname' => false,
        'attname'     => false,
        'attoid'      => false,
        'attremoved'  => false,
    ];

    /**
     * Return the message type.
     *
     * @return string
     */
    public function getClass()
    {
        return 'Attachment';
    }

}
