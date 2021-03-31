<?php
/**
 * Copyright 2014-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information. If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2011-2020 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @package   Horde_ActiveSync
 * @subpackage UnitTestss
 */
namespace Horde\ActiveSync\Stub;

/**
 * Stub needed for the Imap Adapter tests.
 *
 * @author     Michael J Rubinsky <mrubinsk@horde.org>
 * @category   Horde
 * @copyright  2014-2020 Horde LLC
 * @ignore
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @package    Horde_ActiveSync
 * @subpackage UnitTests
 */
class ImapFactory implements Horde_ActiveSync_Interface_ImapFactory
{

    /**
     * Holds a mock Horde_Imap_Client_Socket object.
     * @var [type]
     */
    public $fixture;

    /**
     * Return a Horde_Imap_Client
     *
     * @return Horde_Imap_Client_Base
     * @throws Horde_ActiveSync_Exception
     */
    public function getImapOb()
    {
        return $this->fixture;
    }

    /**
     * Return an array of email folders.
     *
     * @param boolean $force  If true, will force a refresh of the folder list.
     *
     * @return array  An array of folder information. Each entry is keyed by
     *                the mailbox UTF-8 name and contains:
     *                - level: How many parents a folder has, 0 is the root.
     *                - label: The display label for the mailbox.
     *                - d:     The delimiter.
     *
     * @throws Horde_ActiveSync_Exception
     * @todo
     */
    public function getMailboxes($force = false)
    {
        return array();
    }

    /**
     * Return a list of the special mailboxes available on this server.
     *
     * @return array An array of special mailboxes.
     * @throws Horde_ActiveSync_Exception
     * @todo
     */
    public function getSpecialMailboxes()
    {
        return array();
    }

    /**
     * Return a list of user-defined flags.
     *
     * @return array  An array of flag arrays keyed by the RFC 3501 flag name.
     * @todo
     */
    public function getMsgFlags()
    {
        return array();
    }
}
