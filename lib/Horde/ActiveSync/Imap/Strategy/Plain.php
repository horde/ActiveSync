<?php

/**
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */

/**
 * Handles fetching changes for servers that do NOT support CONDSTORE/QRESYNC.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Imap_Strategy_Plain extends Horde_ActiveSync_Imap_Strategy_Base
{
    /**
     * Return a folder object containing all IMAP server change information.
     *
     * @param array $options  An array of options.
     *        @see Horde_ActiveSync_Imap_Adapter::getMessageChanges
     *
     * @return Horde_ActiveSync_Folder_Base  The populated folder object.
     */
    public function getChanges(array $options)
    {
        $this->_logger->meta(
            sprintf(
                'NO CONDSTORE or per mailbox MODSEQ. minuid: %s, total_messages: %s',
                $this->_folder->minuid(),
                $this->_status['messages']
            )
        );

        $query = new Horde_Imap_Client_Search_Query();
        if (!empty($options['sincedate'])) {
            $query->dateSearch(
                new Horde_Date($options['sincedate']),
                Horde_Imap_Client_Search_Query::DATE_SINCE
            );
        }

        try {
            $search_ret = $this->_imap_ob->search(
                $this->_mbox,
                $query,
                ['results' => [Horde_Imap_Client::SEARCH_RESULTS_MATCH]]
            );
        } catch (Horde_Imap_Client_Exception $e) {
            $this->_logger->err($e->getMessage());
            throw new Horde_ActiveSync_Exception($e);
        }

        $cnt = ($search_ret['count'] / Horde_ActiveSync_Imap_Adapter::MAX_FETCH) + 1;
        $query = new Horde_Imap_Client_Fetch_Query();
        $query->flags();
        $flags = [];
        // Native Exchange semantics: EAS cannot represent a message flagged
        // for deletion. A tracked message that gained \Deleted is removed
        // from the client; an untracked one is never exported.
        $tracked = $this->_folder->messages();
        $suppressed = [];
        $flag_deleted = [];
        for ($i = 0; $i <= $cnt; $i++) {
            $ids = new Horde_Imap_Client_Ids(
                array_slice(
                    $search_ret['match']->ids,
                    $i * Horde_ActiveSync_Imap_Adapter::MAX_FETCH,
                    Horde_ActiveSync_Imap_Adapter::MAX_FETCH
                )
            );
            try {
                $fetch_ret = $this->_imap_ob->fetch(
                    $this->_mbox,
                    $query,
                    ['ids' => $ids]
                );
            } catch (Horde_Imap_Client_Exception $e) {
                $this->_logger->err($e->getMessage());
                throw new Horde_ActiveSync_Exception($e);
            }
            foreach ($fetch_ret as $uid => $data) {
                if (array_search(Horde_Imap_Client::FLAG_DELETED, $data->getFlags()) !== false) {
                    if (!in_array($uid, $suppressed)) {
                        $suppressed[] = $uid;
                        if (in_array($uid, $tracked)) {
                            $flag_deleted[] = $uid;
                        }
                    }
                    continue;
                }
                $flags[$uid] = [
                    'read' => (array_search(Horde_Imap_Client::FLAG_SEEN, $data->getFlags()) !== false) ? 1 : 0,
                ];
                if (($options['protocolversion']) > Horde_ActiveSync::VERSION_TWOFIVE) {
                    $flags[$uid]['flagged']
                    = (array_search(Horde_Imap_Client::FLAG_FLAGGED, $data->getFlags()) !== false) ? 1 : 0;
                }
            }
        }
        if (!empty($flags)) {
            $this->_folder->setChanges(
                array_diff($search_ret['match']->ids, $suppressed),
                $flags
            );
        }
        $this->_folder->setRemoved(
            array_merge(
                $this->_imap_ob->vanished(
                    $this->_mbox,
                    null,
                    ['ids' => new Horde_Imap_Client_Ids($this->_folder->messages())]
                )->ids,
                $flag_deleted
            )
        );

        return $this->_folder;
    }

}
