<?php

/**
 * Horde_ActiveSync_Message_RecipientInformation::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Horde_ActiveSync_Message_RecipientInformation::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property string   $email1address
 * @property string   $fileas
 * @property string   $alias (EAS >= 14.0 only)
 * @property string   $weightedrank (EAS >= 14.0 only)
 */
class Horde_ActiveSync_Message_RecipientInformation extends Horde_ActiveSync_Message_Base
{
    /**
     * Property mapping.
     *
     * @var array
     */
    protected $_mapping = [
        Horde_ActiveSync_Message_Contact::EMAIL1ADDRESS  => [self::KEY_ATTRIBUTE => 'email1address'],
        Horde_ActiveSync_Message_Contact::FILEAS         => [self::KEY_ATTRIBUTE => 'fileas'],
        Horde_ActiveSync_Message_Contact::ALIAS          => [self::KEY_ATTRIBUTE => 'alias'],
        Horde_ActiveSync_Message_Contact::WEIGHTEDRANK   => [self::KEY_ATTRIBUTE => 'weightedrank'],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'email1address' => false,
        'fileas'        => false,
        'alias'         => false,
        'weightedrank'   => false,
    ];

    /**
     * Return message type
     *
     * @return string
     */
    public function getClass()
    {
        return 'RI';
    }

}
