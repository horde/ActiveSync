<?php

/**
 * Horde_ActiveSync_Message_Note::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Horde_ActiveSync_Message_Note::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property string   $subject  The note's subject.
 * @property Horde_ActiveSync_Message_AirSyncBaseBody   $body  The note's body.
 * @property string   $messageclass  The note's message class.
 * @property array   $categories  The note's categories.
 * @property Horde_Date   $lastmodified  The note's last modification date.
 */
class Horde_ActiveSync_Message_Note extends Horde_ActiveSync_Message_Base
{
    public const SUBJECT          = 'Notes:Subject';
    public const MESSAGECLASS     = 'Notes:MessageClass';
    public const LASTMODIFIEDDATE = 'Notes:LastModifiedDate';
    public const CATEGORIES       = 'Notes:Categories';
    public const CATEGORY         = 'Notes:Category';

    public $categories = [];

    /**
     * Property mapping
     *
     * @var array
     */
    protected $_mapping =  [
        Horde_ActiveSync::AIRSYNCBASE_BODY      => [self::KEY_ATTRIBUTE => 'body', self::KEY_TYPE => 'Horde_ActiveSync_Message_AirSyncBaseBody'],
        self::CATEGORIES                        => [self::KEY_ATTRIBUTE => 'categories', self::KEY_VALUES => self::CATEGORY],
        self::LASTMODIFIEDDATE                  => [self::KEY_ATTRIBUTE => 'lastmodified', self::KEY_TYPE => self::TYPE_DATE],
        self::MESSAGECLASS                      => [self::KEY_ATTRIBUTE => 'messageclass'],
        self::SUBJECT                           => [self::KEY_ATTRIBUTE => 'subject'],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'body'         => false,
        'lastmodified' => false,
        'messageclass' => false,
        'subject'      => false,
    ];

    /**
     * Return this object's folder class
     *
     * @return string
     */
    public function getClass()
    {
        return 'Notes';
    }
}
