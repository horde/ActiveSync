<?php
/**
 * Horde_ActiveSync_Folder_RI::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2014-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * The class contains functionality for maintaining state for the
 * Recipient Information Cache.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2014-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Folder_RI extends Horde_ActiveSync_Folder_Base implements Serializable
{
    const VERSION = 1;

    /**
     * The current list of recipient email addresses.
     *
     * @var array
     */
    protected $_contacts = array();
    protected $_serverid = 'RI';
    protected $_removed = array();
    protected $_added = array();

    /**
     * Set the current Recipient Cache
     *
     * @param array $contacts  An array of email addresses. Ordered by weight.
     */
    public function setChanges(array $contacts)
    {
        $contacts = array_reverse($contacts);

        // Calculate deletions.
        foreach ($this->_contacts as $weight => $email) {
            if (empty($contacts[$weight]) || $contacts[$weight] != $email) {
                $this->_removed[] = $email . ':' . $weight;
            }
        }

        // Additions
        foreach ($contacts as $weight => $email) {
            if (empty($this->_contacts[$weight]) || $this->_contacts[$weight] != $email) {
                $this->_added[] = $email . ':' . $weight;
            }
        }

        $this->_contacts = $contacts;
    }

    /**
     * Updates the internal UID cache, and clears the internal
     * update/deleted/changed cache.
     */
    public function updateState()
    {
        $this->haveInitialSync = true;
        $this->_removed = array();
        $this->_added = array();
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
     * Return the recipients that are to be added.
     *
     * @return array  An array of psuedo-uids consisting of the the email
     *                address, a colon, and the weighed rank. E.g.
     *                user@example.com:10
     */
    public function added()
    {
        return $this->_added;
    }

    /**
     * Return the recipients that are to be deleted.
     *
     * @return array  An array of psuedo-uids consisting of the the email
     *                address, a colon, and the weighed rank. E.g.
     *                user@example.com:10
     */
    public function removed()
    {
        return $this->_removed;
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
            'd' => $this->_contacts,
            'f' => $this->_serverid,
            'c' => $this->_class,
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
        $this->_contacts = $data['d'];
        $this->_serverid = $data['f'];
        $this->_class = $data['c'];
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
        $this->__unserialize($decoded);
    }

}
