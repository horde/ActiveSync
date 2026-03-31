<?php

/**
 * Horde_ActiveSync_Message_DocumentLibrary:: Defines an object representing
 * a DOCUMENTLIBRARY search result.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2014-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Horde_ActiveSync_Message_DocumentLibrary
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2014-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 */
class Horde_ActiveSync_Message_DocumentLibrary extends Horde_ActiveSync_Message_Base
{
    /**
     * Property mapping
     *
     * @var array
     */
    protected $_mapping = [
        Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_LINKID           => [self::KEY_ATTRIBUTE => 'linkid'],
        Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_DISPLAYNAME      => [self::KEY_ATTRIBUTE => 'displayname'],
        Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_ISFOLDER         => [self::KEY_ATTRIBUTE => 'isfolder'],
        Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_CREATIONDATE     => [self::KEY_ATTRIBUTE => 'creationdate', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_LASTMODIFIEDDATE => [self::KEY_ATTRIBUTE => 'lastmodifieddate', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_ISHIDDEN         => [self::KEY_ATTRIBUTE => 'ishidden'],
        Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_CONTENTLENGTH    => [self::KEY_ATTRIBUTE => 'contentlength'],
        Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_CONTENTTYPE      => [self::KEY_ATTRIBUTE => 'contenttype'],
    ];

    /**
     * Property values
     *
     * @var array
     */
    protected $_properties = [
        'linkid'           => false,
        'displayname'      => false,
        'isfolder'         => false,
        'creationdate'     => false,
        'lastmodifieddate' => false,
        'ishidden'         => false,
        'contentlength'    => false,
        'contenttype'      => 'application/octet-stream',
    ];

}
