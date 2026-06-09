<?php

/**
 * Horde_ActiveSync_Folder_Imap::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * The class contains functionality for maintaining state for a single IMAP
 * folder, and generating server deltas.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Folder_Imap extends Horde_ActiveSync_Folder_Base implements Serializable
{
    /* Key names for various IMAP server status values */
    public const UIDVALIDITY    = 'uidvalidity';
    public const UIDNEXT        = 'uidnext';
    public const HIGHESTMODSEQ  = 'highestmodseq';
    public const MESSAGES       = 'messages';

    /* Serialize version */
    public const VERSION        = 2;

    /* The UID count at which UID lists will be compressed before serialization */
    public const COMPRESSION_LIMIT = 500;

    /**
     * The folder's current message list.
     * Note: This represents the folder list on the client and is affected by
     * the FILTERTYPE on the collection.
     *
     * @var array
     */
    protected $_messages = [];

    /**
     * Internal cache of message UIDs that have been added since last sync.
     * Used for transporting changes back to activesync.
     *
     * @var array
     */
    protected $_added = [];

    /**
     * Internal cache of message UIDs that have been modified on the server
     * since the last sync. Used for transporting changes back to activesync.
     *
     * @var array
     */
    protected $_changed = [];

    /**
     * Internal cache of message UIDs that have been expunged from the IMAP
     * server since last sync. Used for transporting changes back to activesync.
     *
     * @var array
     */
    protected $_removed = [];

    /**
     * Array of messages to be SOFTDELETEd from client. Only used when we have
     * a CONDSTORE server available, otherwise we calculate the uid list based
     * on the cached data.
     *
     * @var array
     */
    protected $_softDeleted = [];

    /**
     * Internal cache of message flag changes. Should be one entry for each UID
     * also listed in the $_changed array. Used for transporting changes back to
     * activesync. An array keyed by message UID:
     *   uid => array('read' => 1)
     *
     * @var array
     */
    protected $_flags = [];

    /**
     * Internal cache of custom message flags (i.e., categories). Should contain
     * one entry for each UID listed in the $_changed array. An array keyed on
     * message UID:
     *   uid => array('TestOne', 'TestTwo')
     *
     * @var array
     */
    protected $_categories = [];

    /**
     * Internal flag to indicate initial first sync/prime.
     *
     * @var boolean
     */
    protected $_primed = false;

    /**
     * PING watermark (IMAP STATUS last acknowledged to the client).
     *
     * Part of the three-state email sync model:
     *   - $_status / modseq()     — SYNC watermark (CHANGEDSINCE)
     *   - $_pingStatus            — PING watermark (this property)
     *   - horde_activesync_state.sync_pending — MOREAVAILABLE batch (SYNC only)
     *
     * @see Horde_ActiveSync_State_Base::getChanges() for the PING vs SYNC paths.
     *
     * @var array
     */
    protected $_pingStatus = [];

    /**
     * Set message changes.
     *
     * @param array $messages       An array of message UIDs.
     * @param array $flags          A hash of message read flags, keyed by UID.
     * @param array $categories     A hash of custom message flags, keyed by UID.
     *                              @since 2.17.0
     * @param boolean $resetMinUid  If true, reset the minimum UID. Should be
     *                              used when FilterType has widened and
     *                              messages older than originally selected are
     *                              being returned. @since 2.24.0
     */
    public function setChanges(
        array $messages,
        array $flags = [],
        array $categories = [],
        $resetMinUid = false
    ) {
        $uidnext = $this->uidnext();
        $minuid = $this->minuid();
        $modseq = $this->modseq();

        foreach ($messages as $uid) {
            if ($uid >= $uidnext) {
                // new (to us) message.
                $this->_added[] = $uid;
            } elseif ($uid >= $minuid || $resetMinUid) {
                if ($modseq > 0) {
                    // MODSEQ capable server, either a changed message, or
                    // one that was previously outside of FilterType but is now
                    // within.
                    if (array_search($uid, $this->_messages) !== false) {
                        $this->_changed[] = $uid;
                    } else {
                        $this->_added[] = $uid;
                    }
                } else {
                    if (empty($this->_messages[$uid])) {
                        // Do not know about this message
                        continue;
                    }
                    if ((isset($flags[$uid]['read']) && $flags[$uid]['read'] != $this->_messages[$uid]['read'])
                        || (isset($flags[$uid]['flagged']) && $flags[$uid]['flagged'] != $this->_messages[$uid]['flagged'])) {

                        $this->_changed[] = $uid;
                    }
                }
            }
        }

        foreach ($flags as $uid => $data) {
            if (!empty($this->_flags[$uid])) {
                $this->_flags[$uid] += $data;
            } else {
                $this->_flags[$uid] = $data;
            }
        }

        foreach ($categories as $uid => $data) {
            if (!empty($this->_categories[$uid])) {
                $this->_categories[$uid] += $data;
            } else {
                $this->_categories[$uid] = $data;
            }
        }
    }

    /**
     * Sets initial message collection for servers that support MODSEQ. Much
     * more effecient than using setChanges() when we know this is the initial
     * folder "priming".
     *
     * @param array $messages  Array of message UIDs for the initial message
     *                         set for this folder.
     */
    public function primeFolder($messages)
    {
        $this->_added = $messages;
        $this->_primed = true;
    }

    /**
     * Return a list of message uids that should be SOFTDELETEd from the client.
     * Must be called after setChanges and setRemoved, but before updateState.
     *
     * @return array
     */
    public function getSoftDeleted()
    {
        if (empty($this->_status[self::HIGHESTMODSEQ])) {
            // non-CONDSTORE server, we actually already have this data without
            // having to search the server again. If we don't have an entry in
            // $this->_flags, the message was not returned in the latest query,
            // so it is either deleted or outside the range.
            $good_uids = array_keys($this->_flags);
            $messages = array_diff(array_keys($this->_messages), $this->_removed);
            $soft = array_diff($messages, $good_uids);

            // Now remove them so we don't return them again next time, and we
            // don't need to remember them since once they are SOFTDELETED, we
            // no longer care about any changes to the message.
            foreach ($soft as $id) {
                unset($this->_messages[$id]);
            }

            return $soft;
        } else {
            return $this->_softDeleted;
        }
    }

    /**
     * Set the list of uids to be SOFTDELETEd. Only needed for CONDSTORE
     * servers.
     *
     * @param array $softDeleted  The message UID list.
     */
    public function setSoftDeleted(array $softDeleted)
    {
        $this->_softDeleted = $softDeleted;
        $this->_messages = array_diff($this->_messages, $this->_softDeleted);
    }

    /**
     * Check the validity of various values.
     *
     * @param array $params  A status array containing status to check.
     *
     * @throws Horde_ActiveSync_Exception_StaleState
     */
    public function checkValidity(array $params = [])
    {
        if (!$this->uidvalidity()) {
            throw new Horde_ActiveSync_Exception('State not initialized.');
        }
        if (!empty($params[self::UIDVALIDITY]) && $this->uidvalidity() != $params[self::UIDVALIDITY]) {
            throw new Horde_ActiveSync_Exception_StaleState('UIDVALIDTY no longer valid');
        }
    }

    /**
     * Set the list of expunged message UIDs.
     *
     * @param array $uids  An array of message UIDs that have been expunged.
     * @throws Horde_ActiveSync_Exception_StaleState
     */
    public function setRemoved(array $uids)
    {
        // Protect against HUGE numbers of UIDs from apparently broken(?) servers.
        if (count($uids)) {
            if ($uids[0] < $this->minuid()) {
                throw new Horde_ActiveSync_Exception_StaleState(
                    'BROKEN IMAP server has returned all VANISHED UIDs.'
                );
            }
        }

        $this->_removed = $uids;
    }

    /**
     * Updates the internal UID cache if needed and clears the internal
     * update/deleted/changed cache. To be called after all changes have
     * been dealt with by the activesync client.
     */
    public function updateState()
    {
        $deferInitialComplete = false;
        if (empty($this->_status[self::HIGHESTMODSEQ])) {
            $this->_messages = array_diff(array_keys($this->_messages), $this->_removed);
            foreach ($this->_added as $add) {
                $this->_messages[] = $add;
            }
            $this->_messages = $this->_flags + array_flip($this->_messages);
        } else {
            if ($this->_primed) {
                // CONDSTORE initial priming: the full UID list is exported via
                // sync_pending. Only add UIDs to _messages as they are actually
                // sent to the client (acknowledgeExportedMessage()).
                $this->_primed = false;
                $deferInitialComplete = true;
            } else {
                foreach ($this->_added as $add) {
                    $this->_messages[] = $add;
                }
                $this->_messages = array_diff($this->_messages, $this->_removed);
            }
        }

        // Clean up
        $this->_removed = [];
        $this->_added = [];
        $this->_changed = [];
        $this->_flags = [];
        $this->_softDeleted = [];
        if (!$deferInitialComplete) {
            $this->haveInitialSync = true;
        }
        $this->_syncPingStatus();
    }

    /**
     * Track a message UID successfully exported to the client.
     *
     * During CONDSTORE initial sync the server's _messages cache must reflect
     * only mail the client has actually received, not the full primed UID
     * list polled from IMAP.
     *
     * @param integer|string $uid  The IMAP UID.
     */
    public function acknowledgeExportedMessage($uid)
    {
        if ($uid === '' || $uid === null) {
            return;
        }

        if (empty($this->_status[self::HIGHESTMODSEQ])) {
            $this->_messages[$uid] = $uid;
        } else {
            $this->_messages[] = $uid;
        }
    }

    /**
     * Mark an initial folder sync as complete.
     *
     * Called once sync_pending is empty and all MOREAVAILABLE batches have
     * been delivered to the client.
     */
    public function markInitialSyncComplete()
    {
        $this->haveInitialSync = true;
    }

    /**
     * Return the MODSEQ value used for PING change detection.
     *
     * @return integer
     */
    public function pingModseq()
    {
        if (!empty($this->_pingStatus[self::HIGHESTMODSEQ])) {
            return $this->_pingStatus[self::HIGHESTMODSEQ];
        }

        return $this->modseq();
    }

    /**
     * Return the UIDNEXT value used for PING change detection.
     *
     * @return integer
     */
    public function pingUidnext()
    {
        if (array_key_exists(self::UIDNEXT, $this->_pingStatus)) {
            return $this->_pingStatus[self::UIDNEXT];
        }

        return $this->uidnext();
    }

    /**
     * Return the message count used for PING change detection.
     *
     * @return integer
     */
    public function pingTotalMessages()
    {
        if (array_key_exists(self::MESSAGES, $this->_pingStatus)) {
            return $this->_pingStatus[self::MESSAGES];
        }

        return $this->total_messages();
    }

    /**
     * Advance the PING checkpoint to the current IMAP STATUS.
     *
     * @param array $status  An IMAP STATUS result.
     */
    public function acknowledgePingStatus(array $status)
    {
        $this->_pingStatus = [
            self::UIDVALIDITY => $status[self::UIDVALIDITY] ?? $this->uidvalidity(),
            self::UIDNEXT => $status[self::UIDNEXT] ?? $this->uidnext(),
            self::HIGHESTMODSEQ => $status[self::HIGHESTMODSEQ] ?? 0,
            self::MESSAGES => $status[self::MESSAGES] ?? 0,
        ];
    }

    /**
     * Align the PING checkpoint with the current SYNC state.
     */
    protected function _syncPingStatus()
    {
        $synced = [
            self::UIDVALIDITY => $this->uidvalidity(),
            self::UIDNEXT => $this->uidnext(),
            self::HIGHESTMODSEQ => $this->modseq(),
            self::MESSAGES => $this->total_messages(),
        ];

        // Never move the PING checkpoint backward. Partial SYNC responses can
        // leave the SYNC modseq behind the server while the PING checkpoint was
        // already advanced during PING.
        if (empty($this->_pingStatus)) {
            $this->_pingStatus = $synced;
            return;
        }

        if (!empty($synced[self::HIGHESTMODSEQ])
            && (empty($this->_pingStatus[self::HIGHESTMODSEQ])
                || $synced[self::HIGHESTMODSEQ] > $this->_pingStatus[self::HIGHESTMODSEQ])) {
            $this->_pingStatus[self::HIGHESTMODSEQ] = $synced[self::HIGHESTMODSEQ];
        }
        if ($synced[self::UIDNEXT] > ($this->_pingStatus[self::UIDNEXT] ?? 0)) {
            $this->_pingStatus[self::UIDNEXT] = $synced[self::UIDNEXT];
        }
        if ($synced[self::MESSAGES] != ($this->_pingStatus[self::MESSAGES] ?? null)) {
            $this->_pingStatus[self::MESSAGES] = $synced[self::MESSAGES];
        }
        if (!empty($synced[self::UIDVALIDITY])) {
            $this->_pingStatus[self::UIDVALIDITY] = $synced[self::UIDVALIDITY];
        }
    }

    /**
     * Return the folder's UID validity.
     *
     * @return string|boolean The folder UID validity marker, or false if not set.
     */
    public function uidvalidity()
    {
        if (!array_key_exists(self::UIDVALIDITY, $this->_status)) {
            return false;
        }

        return $this->_status[self::UIDVALIDITY];
    }

    /**
     * Return the folder's next UID number.
     *
     * @return string The next UID number.
     */
    public function uidnext()
    {
        return empty($this->_status[self::UIDNEXT])
            ? 0
            : $this->_status[self::UIDNEXT];
    }

    /**
     * Return the folder's MODSEQ value.
     *
     * @return string  The MODSEQ number.
     */
    public function modseq()
    {
        return empty($this->_status[self::HIGHESTMODSEQ])
            ? 0
            : $this->_status[self::HIGHESTMODSEQ];
    }

    /**
     * Return the total, unfiltered number of messages in the folder.
     *
     * @return integer  The total number of messages.
     */
    public function total_messages()
    {
        return empty($this->_status[self::MESSAGES])
            ? 0
            : $this->_status[self::MESSAGES];
    }

    /**
     * Return the list of UIDs currently on the device.
     *
     * @return array The list of backend messages.
     */
    public function messages()
    {
        return empty($this->_status[self::HIGHESTMODSEQ])
            ? array_keys($this->_messages)
            : $this->_messages;
    }

    /**
     * Return the internal message flags changes cache.
     *
     * @return array  The array of message flag changes.
     */
    public function flags()
    {
        return $this->_flags;
    }

    /**
     * Return the internal message category cache.
     *
     * @return array  The array of message categories. @see self::$_categories
     */
    public function categories()
    {
        return $this->_categories;
    }

    /**
     * Return the list of UIDs that need to be added to the device.
     *
     * @return array  The list of UIDs.
     */
    public function added()
    {
        return $this->_added;
    }

    /**
     * Return the list of UIDs that need to have flag changes sent to the device
     *
     * @return array The list of UIDs.
     */
    public function changed()
    {
        return $this->_changed;
    }

    /**
     * Return the list of UIDs that need to be removed from the device.
     *
     * @return array  The list of UIDs.
     */
    public function removed()
    {
        return $this->_removed;
    }

    /**
     * Return the minimum IMAP UID contained in this folder.
     *
     * @return integer  The IMAP UID.
     */
    public function minuid()
    {
        if (empty($this->_messages)) {
            return 0;
        }

        if (empty($this->_status[self::HIGHESTMODSEQ])) {
            return min(array_keys($this->_messages));
        }

        return min($this->_messages);
    }

    /**
     * Serialize this object.
     *
     * @return string  The serialized data.
     */
    public function serialize()
    {
        if (!empty($this->_status[self::HIGHESTMODSEQ])) {
            $msgs = (count($this->_messages) > self::COMPRESSION_LIMIT)
               ? $this->_toSequenceString($this->_messages)
               : implode(',', $this->_messages);
        } else {
            $msgs = $this->_messages;
        }

        $data = [
            's' => $this->_status,
            'm' => $msgs,
            'f' => $this->_serverid,
            'c' => $this->_class,
            'lsd' => $this->_lastSinceDate,
            'sd' => $this->_softDelete,
            'hi' => $this->haveInitialSync,
            'v' => self::VERSION,
        ];
        if (!empty($this->_pingStatus)) {
            $data['ps'] = $this->_pingStatus;
        }

        return json_encode($data);
    }

    /**
     * Reconstruct the object from serialized data.
     *
     * @param string $data  The serialized data.
     * @throws Horde_ActiveSync_Exception_StaleState
     */
    public function unserialize($data)
    {
        $d_data = json_decode($data, true);
        if (!is_array($d_data) || empty($d_data['v']) || $d_data['v'] != self::VERSION) {
            // Try using the old serialization strategy, since this would save
            // an expensive resync of email collections.
            $d_data = @unserialize($data);
            if (!is_array($d_data) || empty($d_data['v']) || $d_data['v'] != 1) {
                throw new Horde_ActiveSync_Exception_StaleState('Cache version change');
            }
        }
        $this->_status = $d_data['s'];
        $this->_messages = $d_data['m'];
        $this->_serverid = $d_data['f'];
        $this->_class = $d_data['c'];
        $this->_lastSinceDate = $d_data['lsd'];
        $this->_softDelete = $d_data['sd'];
        $this->haveInitialSync = array_key_exists('hi', $d_data)
            ? (bool) $d_data['hi']
            : !empty($this->_messages);
        $this->_pingStatus = !empty($d_data['ps']) ? $d_data['ps'] : [];

        if (!empty($this->_status[self::HIGHESTMODSEQ]) && is_string($this->_messages)) {
            $this->_messages = $this->_fromSequenceString($this->_messages);
        }
    }

    /**
     * Convert the instance into a string.
     *
     * @return string The string representation for this instance.
     */
    public function __toString()
    {
        return sprintf(
            'status: %s\nchanged: %s\nadded: %s\nremoved: %s',
            join(', ', $this->_status),
            join(', ', $this->_changed),
            join(', ', $this->_added),
            join(', ', $this->_removed)
        );
    }

    /**
     * Create an IMAP message sequence string from a list of indices.
     *
     * Index Format: range_start:range_end,uid,uid2,...
     *
     * @param array $ids  An array of UIDs.
     *
     * @return string  The IMAP message sequence string.
     */
    protected function _toSequenceString(array $ids)
    {
        if (empty($ids)) {
            return '';
        }

        $in = $ids;
        sort($in, SORT_NUMERIC);
        $first = $last = array_shift($in);
        $i = count($in) - 1;
        $out = [];

        reset($in);
        foreach ($in as $key => $val) {
            if (($last + 1) == $val) {
                $last = $val;
            }

            if (($i == $key) || ($last != $val)) {
                if ($last == $first) {
                    $out[] = $first;
                    if ($i == $key) {
                        $out[] = $val;
                    }
                } else {
                    $out[] = $first . ':' . $last;
                    if (($i == $key) && ($last != $val)) {
                        $out[] = $val;
                    }
                }
                $first = $last = $val;
            }
        }

        return empty($out)
            ? $first
            : implode(',', $out);
    }

    /**
     * Parse an IMAP message sequence string into a list of indices.
     *
     * @see _toSequenceString()
     *
     * @param string $str  The IMAP message sequence string.
     *
     * @return array  An array of indices.
     */
    protected function _fromSequenceString($str)
    {
        $ids = [];
        $str = trim($str);

        if (!strlen($str)) {
            return $ids;
        }

        $idarray = explode(',', $str);
        if (strpos($str, ':') === false) {
            return $idarray;
        }
        foreach ($idarray as $val) {
            $range = explode(':', $val);
            if (isset($range[1])) {
                for ($i = min($range), $j = max($range); $i <= $j; ++$i) {
                    $ids[] = $i;
                }
            } else {
                $ids[] = $val;
            }
        }

        return $ids;
    }

}
