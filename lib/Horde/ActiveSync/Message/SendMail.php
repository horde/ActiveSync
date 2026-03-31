<?php

/**
 * Horde_ActiveSync_Message_SendMail::
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
 * Horde_ActiveSync_Message_SendMail::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property string   $clientid         The client's temporary clientid for this
 *                                      item.
 * @property boolean   $saveinsent      Flag to indicate whether to save in sent
 *                                      mail.
 * @property boolean   $replacemime     Flag to indicate we are replacing the
 *                                      Full MIME data (i.e., not a SMART item).
 * @property string   $accountid        The accountid.
 * @property Horde_ActiveSync_Message_SendMailSource   $source
 *                                      The email source.
 * @property string|stream mime         The MIME contents of the message.
 * @property string  $templateid        The templateid.
 * @property string  $forwardees        An array of Forwardee objects.
 *                                      EAS 16.0 Only.
 */
class Horde_ActiveSync_Message_SendMail extends Horde_ActiveSync_Message_Base
{
    public const COMPOSEMAIL_SENDMAIL        = 'ComposeMail:SendMail';
    public const COMPOSEMAIL_SMARTFORWARD    = 'ComposeMail:SmartForward';
    public const COMPOSEMAIL_SMARTREPLY      = 'ComposeMail:SmartReply';
    public const COMPOSEMAIL_SAVEINSENTITEMS = 'ComposeMail:SaveInSentItems';
    public const COMPOSEMAIL_REPLACEMIME     = 'ComposeMail:ReplaceMime';
    public const COMPOSEMAIL_TYPE            = 'ComposeMail:Type';
    public const COMPOSEMAIL_SOURCE          = 'ComposeMail:Source';
    public const COMPOSEMAIL_MIME            = 'ComposeMail:MIME';
    public const COMPOSEMAIL_CLIENTID        = 'ComposeMail:ClientId';
    public const COMPOSEMAIL_STATUS          = 'ComposeMail:Status';
    public const COMPOSEMAIL_ACCOUNTID       = 'ComposeMail:AccountId';

    // 16.0
    public const COMPOSEMAIL_FORWARDEES      = 'ComposeMail:Forwardees';
    public const COMPOSEMAIL_FORWARDEE       = 'ComposeMail:Forwardee';
    public const COMPOSEMAIL_FORWARDEENAME   = 'ComposeMail:ForwardeeName';
    public const COMPOSEMAIL_FORWARDEEEMAIL  = 'ComposeMail:ForwardeeEmail';


    /**
     * Property mapping
     *
     * @var array
     */
    protected $_mapping =  [
        self::COMPOSEMAIL_CLIENTID        => [self::KEY_ATTRIBUTE => 'clientid'],
        self::COMPOSEMAIL_SAVEINSENTITEMS => [self::KEY_ATTRIBUTE => 'saveinsent'],
        self::COMPOSEMAIL_REPLACEMIME     => [self::KEY_ATTRIBUTE => 'replacemime'],
        self::COMPOSEMAIL_ACCOUNTID       => [self::KEY_ATTRIBUTE => 'accountid'],
        self::COMPOSEMAIL_SOURCE          => [self::KEY_ATTRIBUTE => 'source', self::KEY_TYPE => 'Horde_ActiveSync_Message_SendMailSource'],
        self::COMPOSEMAIL_MIME            => [self::KEY_ATTRIBUTE => 'mime'],
        Horde_ActiveSync::RM_TEMPLATEID   => [self::KEY_ATTRIBUTE => 'templateid'],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'clientid'    => false,
        'saveinsent'  => false,
        'replacemime' => false,
        'accountid'   => false,
        'source'      => false,
        'mime'        => false,
        'templateid'  => false,
    ];

    /**
     * Const'r
     *
     * @see Horde_ActiveSync_Message_Base::__construct()
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        if ($this->_version >= Horde_ActiveSync::VERSION_SIXTEEN) {
            $this->_mapping += [
                self::COMPOSEMAIL_FORWARDEES => [self::KEY_ATTRIBUTE => 'forwardees', self::KEY_TYPE => 'Horde_ActiveSync_Message_Forwardee', self::KEY_VALUES => self::COMPOSEMAIL_FORWARDEE],
            ];
            $this->_properties += [
                'forwardees'     => false,
            ];
        }
    }

    public function &__get($property)
    {
        // The saveinsent is an empty tag, and is considered true if it is
        // present.
        // Deal with the empty tags that are considered true if they are present
        switch ($property) {
            case 'saveinsent':
            case 'replacemime':
                $return = $this->_properties[$property] !== false;
                return $return;
        }

        return parent::__get($property);
    }

    /**
     * Return this object's folder class
     *
     * @return string
     */
    public function getClass()
    {
        return 'SendMail';
    }

    /**
     * Check if a field should be sent to the device even if it is empty.
     *
     * @param string $tag  The field tag.
     *
     * @return boolean
     */
    protected function _checkSendEmpty($tag)
    {
        if ($tag == self::COMPOSEMAIL_SAVEINSENTITEMS
            || $tag == self::COMPOSEMAIL_REPLACEMIME) {
            return true;
        }

        return false;
    }

}
