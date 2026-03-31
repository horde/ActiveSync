<?php
/**
 * Horde_ActiveSync_Folder_Collection::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2026 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * The class contains functionality for maintaining state for a generic
 * collection folder. This would include Appointments, Contacts, and Tasks.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2026 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Folder_Collection extends Horde_ActiveSync_Folder_Base implements Serializable
{
    const VERSION = 1;

    /**
     * Updates the internal UID cache, and clears the internal
     * update/deleted/changed cache.
     */
    public function updateState()
    {
        $this->haveInitialSync = true;
    }

    /**
     * Convert the instance into a string.
     *
     * @return string The string representation for this instance.
     */
    public function __toString()
    {
        return sprintf(
            'serverid: %s\nclass: %s\n',
            $this->serverid(),
            $this->collectionClass());
    }

    /**
     * Serialize this object using modern PHP serialization.
     *
     * @return array  The data to serialize.
     * @since 3.0.0-beta2
     */
    public function __serialize(): array
    {
        return array(
            's' => $this->_status,
            'f' => $this->_serverid,
            'c' => $this->_class,
            'lsd' => $this->_lastSinceDate,
            'sd' => $this->_softDelete,
            'i' => $this->haveInitialSync,
            'v' => self::VERSION
        );
    }

    /**
     * Reconstruct the object from serialized data using modern PHP serialization.
     *
     * @param array $data  The serialized data.
     * @throws Horde_ActiveSync_Exception_StaleState
     * @since 3.0.0-beta2
     */
    public function __unserialize(array $data): void
    {
        if (empty($data['v']) || $data['v'] != self::VERSION) {
            throw new Horde_ActiveSync_Exception_StaleState('Cache version change');
        }
        $this->_status = $data['s'];
        $this->_serverid = $data['f'];
        $this->_class = $data['c'];
        $this->haveInitialSync = $data['i'];
        $this->_lastSinceDate = empty($data['lsd']) ? 0 : $data['lsd'];
        $this->_softDelete = empty($data['sd']) ? 0 : $data['sd'];
    }

    /**
     * Serialize this object (legacy Serializable interface).
     *
     * Delegates to __serialize() and JSON-encodes for backward compatibility
     * with old "C" format data storage.
     *
     * @return string  The serialized data.
     */
    public function serialize()
    {
        return json_encode($this->__serialize());
    }

    /**
     * Reconstruct the object from serialized data (legacy Serializable interface).
     *
     * Supports both old "C" format (JSON-encoded) data and delegates to
     * __unserialize() for processing.
     *
     * @param string $data  The serialized data.
     * @throws Horde_ActiveSync_Exception_StaleState
     */
    public function unserialize($data)
    {
        $decoded = @json_decode($data, true);
        if (!is_array($decoded)) {
            throw new Horde_ActiveSync_Exception_StaleState('Invalid serialized data');
        }
        // Ensure version key exists for old data that might be missing it
        if (!isset($decoded['v'])) {
            $decoded['v'] = self::VERSION;
        }
        $this->__unserialize($decoded);
    }

}
