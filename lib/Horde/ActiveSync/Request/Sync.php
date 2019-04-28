<?php
/**
 * Horde_ActiveSync_Request_Sync::
 *
 * Portions of this class were ported from the Z-Push project:
 *   File      :   wbxml.php
 *   Project   :   Z-Push
 *   Descr     :   WBXML mapping file
 *
 *   Created   :   01.10.2007
 *
 *   © Zarafa Deutschland GmbH, www.zarafaserver.de
 *   This file is distributed under GPL-2.0.
 *   Consult COPYING file for details
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *            NOTE: According to sec. 8 of the GENERAL PUBLIC LICENSE (GPL),
 *            Version 2, the distribution of the Horde_ActiveSync module in or
 *            to the United States of America is excluded from the scope of this
 *            license.
 * @copyright 2009-2017 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Handle Sync requests
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *            NOTE: According to sec. 8 of the GENERAL PUBLIC LICENSE (GPL),
 *            Version 2, the distribution of the Horde_ActiveSync module in or
 *            to the United States of America is excluded from the scope of this
 *            license.
 * @copyright 2009-2017 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 * @internal
 */
class Horde_ActiveSync_Request_Sync extends Horde_ActiveSync_Request_SyncBase
{
    /* Status */
    const STATUS_SUCCESS                = 1;
    const STATUS_VERSIONMISM            = 2;
    const STATUS_KEYMISM                = 3;
    const STATUS_PROTERROR              = 4;
    const STATUS_SERVERERROR            = 5;
    const STATUS_INVALID                = 6;
    const STATUS_CONFLICT               = 7;
    const STATUS_NOTFOUND               = 8;

    // 12.1
    const STATUS_FOLDERSYNC_REQUIRED    = 12;
    const STATUS_REQUEST_INCOMPLETE     = 13;
    const STATUS_INVALID_WAIT_HEARTBEAT = 14;

    /* Maximum window size (12.1 only) */
    const MAX_WINDOW_SIZE    = 512;

    /* Maximum HEARTBEAT value (seconds) (12.1 only) */
    const MAX_HEARTBEAT      = 3540;

    /**
     * Collections manager.
     *
     * @var Horde_ActiveSync_Collections
     */
    protected $_collections;

    /**
     * Handle the sync request
     *
     * @return boolean
     * @throws Horde_ActiveSync_Exception
     */
    protected function _handle()
    {
        $this->_logger->meta('Handling SYNC command.');

        // Check policy
        if (!$this->checkPolicyKey($this->_activeSync->getPolicyKey(), Horde_ActiveSync::SYNC_SYNCHRONIZE)) {
            return true;
        }

        // Check global errors.
        if ($error = $this->_activeSync->checkGlobalError()) {
            $this->_statusCode = $error;
            $this->_handleGlobalSyncError();
            return true;
        }

        // Defaults
        $this->_statusCode = self::STATUS_SUCCESS;
        $partial = false;

        try {
            $this->_collections = $this->_activeSync->getCollectionsObject();
        } catch (Horde_ActiveSync_Exception $e) {
            $this->_statusCode = self::STATUS_SERVERERROR;
            $this->_handleGlobalSyncError();
            return true;
        }

        // Sanity check
        if ($this->_device->version >= Horde_ActiveSync::VERSION_TWELVEONE) {
            // We don't have a previous FOLDERSYNC.
            if (!$this->_collections->haveHierarchy()) {
                $this->_logger->info('No HIERARCHY SYNCKEY in sync_cache, invalidating.');
                $this->_statusCode = self::STATUS_FOLDERSYNC_REQUIRED;
                $this->_handleGlobalSyncError();
                return true;
            }
        }

        // Start decoding request
        if (!$this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_SYNCHRONIZE)) {
            if ($this->_device->version >= Horde_ActiveSync::VERSION_TWELVEONE) {
                $this->_logger->meta('Empty Sync request, taking info from SyncCache.');
                if ($this->_collections->cachedCollectionCount() == 0) {
                    $this->_logger->warn('Empty SYNC request but no SyncCache or SyncCache with no collections.');
                    $this->_statusCode = self::STATUS_REQUEST_INCOMPLETE;
                    $this->_handleGlobalSyncError();
                    return true;
                } else {
                    if (!$this->_collections->initEmptySync()) {
                        $this->_statusCode = self::STATUS_REQUEST_INCOMPLETE;
                        $this->_handleGlobalSyncError();
                        return true;
                    }
                }
            } else {
                $this->_statusCode = self::STATUS_REQUEST_INCOMPLETE;
                $this->_handleGlobalSyncError();
                $this->_logger->err('Empty Sync request and protocolversion < 12.1');
                return true;
            }
        } else {
            // Start decoding request.
            $this->_collections->hangingSync = false;
            while (($sync_tag = ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_WINDOWSIZE) ? Horde_ActiveSync::SYNC_WINDOWSIZE :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERS) ? Horde_ActiveSync::SYNC_FOLDERS :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_PARTIAL) ? Horde_ActiveSync::SYNC_PARTIAL :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_WAIT) ? Horde_ActiveSync::SYNC_WAIT :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_HEARTBEATINTERVAL) ? Horde_ActiveSync::SYNC_HEARTBEATINTERVAL :
                   -1)))))) != -1 ) {

                switch($sync_tag) {
                case Horde_ActiveSync::SYNC_HEARTBEATINTERVAL:
                    if ($hbinterval = $this->_decoder->getElementContent()) {
                        $this->_collections->setHeartbeat(array('hbinterval' => $hbinterval));
                        $this->_collections->hangingSync = true;
                        $this->_decoder->getElementEndTag();
                        if ($hbinterval > (self::MAX_HEARTBEAT)) {
                            $this->_logger->err('HeartbeatInterval outside of allowed range.');
                            $this->_statusCode = self::STATUS_INVALID_WAIT_HEARTBEAT;
                            $this->_handleGlobalSyncError(self::MAX_HEARTBEAT);
                            return true;
                        }
                    }
                    break;
                case Horde_ActiveSync::SYNC_WAIT:
                    if ($wait = $this->_decoder->getElementContent()) {
                        $this->_collections->setHeartbeat(array('wait' => $wait));
                        $this->_collections->hangingSync = true;
                        $this->_decoder->getElementEndTag();
                        if ($wait > (self::MAX_HEARTBEAT / 60)) {
                            $this->_logger->err('Wait value outside of allowed range.');
                            $this->_statusCode = self::STATUS_INVALID_WAIT_HEARTBEAT;
                            $this->_handleGlobalSyncError(self::MAX_HEARBEAT / 60);
                            return true;
                        }
                    }
                    break;
                case Horde_ActiveSync::SYNC_PARTIAL:
                    if ($this->_decoder->getElementContent(Horde_ActiveSync::SYNC_PARTIAL)) {
                        $this->_decoder->getElementEndTag();
                    }
                    $partial = true;
                    break;
                case Horde_ActiveSync::SYNC_WINDOWSIZE:
                    $this->_collections->setDefaultWindowSize($this->_decoder->getElementContent());
                    if (!$this->_decoder->getElementEndTag()) {
                        $this->_logger->err('PROTOCOL ERROR');
                        return false;
                    }
                    break;
                case Horde_ActiveSync::SYNC_FOLDERS:
                    if (!$this->_parseSyncFolders()) {
                        // Any errors are handled in _parseSyncFolders() and
                        // appropriate error codes sent to device.
                        return true;
                    }
                }
            }

            if ($this->_device->version >= Horde_ActiveSync::VERSION_TWELVEONE) {
                // These are not allowed in the same request.
                if ($this->_collections->hbinterval !== false &&
                    $this->_collections->wait !== false) {

                    $this->_logger->err('Received both HBINTERVAL and WAIT interval in same request.');
                    $this->_statusCode = Horde_ActiveSync_Status::INVALID_XML;
                    $this->_handleGlobalSyncError();
                    return true;
                }

                // Fill in missing sticky data from cache.
                $this->_collections->validateFromCache();
            }

            // Ensure we have OPTIONS values.
            $this->_collections->ensureOptions();

            // Full or partial sync request?
            if ($partial === true) {
                $this->_logger->info('Executing a PARTIAL SYNC.');
                if (!$this->_collections->initPartialSync()) {
                    $this->_statusCode = self::STATUS_REQUEST_INCOMPLETE;
                    $this->_handleGlobalSyncError();
                    return true;
                }
            } else {
                // Full request.
                $this->_collections->initFullSync();
            }

            // End SYNC tag.
            if (!$this->_decoder->getElementEndTag()) {
                $this->_statusCode = self::STATUS_PROTERROR;
                $this->_handleGlobalSyncError();
                $this->_logger->err('PROTOCOL ERROR: Missing closing SYNC tag');
                return false;
            }

            // We MUST have syncable collections by now.
            if (!$this->_collections->haveSyncableCollections($this->_device->version)) {
                $this->_statusCode = self::STATUS_KEYMISM;
                $this->_handleGlobalSyncError();
                return true;
            }

            // Update the syncCache with the new collection data.
            $this->_collections->updateCache();

            // Save.
            $this->_collections->save(true);

            $this->_logger->meta('All synckeys confirmed. Continuing with SYNC');
        }

        $pingSettings = $this->_driver->getHeartbeatConfig();

        // Override the total, per-request, WINDOWSIZE?
        if (!empty($pingSettings['maximumrequestwindowsize'])) {
            $this->_collections->setDefaultWindowSize($pingSettings['maximumrequestwindowsize'], true);
        }

        // If this is >= 12.1, see if we want a looping SYNC.
        if ($this->_collections->canDoLoopingSync() &&
            $this->_device->version >= Horde_ActiveSync::VERSION_TWELVEONE &&
            $this->_statusCode == self::STATUS_SUCCESS) {

            // Calculate the heartbeat
            if (!$heartbeat = $this->_collections->getHeartbeat()) {
                $heartbeat = !empty($pingSettings['heartbeatdefault'])
                    ? $pingSettings['heartbeatdefault']
                    : 10;
            }

            // Wait for changes.
            $changes = $this->_collections->pollForChanges($heartbeat, $pingSettings['waitinterval']);
            if ($changes !== true && $changes !== false) {
                switch ($changes) {
                case Horde_ActiveSync_Collections::COLLECTION_ERR_STALE:
                    $this->_logger->info('Changes in cache detected during looping SYNC exiting here.');
                    return true;
                case Horde_ActiveSync_Collections::COLLECTION_ERR_FOLDERSYNC_REQUIRED;
                    $this->_statusCode = self::STATUS_FOLDERSYNC_REQUIRED;
                    $this->_handleGlobalSyncError();
                    return true;
                case Horde_ActiveSync_Collections::COLLECTION_ERR_SYNC_REQUIRED;
                    $this->_statusCode = self::STATUS_REQUEST_INCOMPLETE;
                    $this->_handleGlobalSyncError();
                    return true;
                default:
                    $this->_statusCode = self::STATUS_SERVERERROR;
                    $this->_handleGlobalSyncError();
                    return true;
                }
            }
        }

        // See if we can do an empty response
        if ($this->_device->version >= Horde_ActiveSync::VERSION_TWELVEONE &&
            $this->_statusCode == self::STATUS_SUCCESS &&
            empty($changes) &&
            $this->_collections->canSendEmptyResponse()) {

            $this->_logger->info('Sending an empty SYNC response.');
            $this->_collections->lastsyncendnormal = time();
            $this->_collections->save(true);
            return true;
        }

        $this->_logger->info(sprintf(
            'Completed parsing incoming request. Peak memory usage: %d.',
             memory_get_peak_usage(true))
        );

        // Start output to client
        $this->_encoder->startWBXML();
        $this->_encoder->startTag(Horde_ActiveSync::SYNC_SYNCHRONIZE);
        $this->_encoder->startTag(Horde_ActiveSync::SYNC_STATUS);
        $this->_encoder->content(self::STATUS_SUCCESS);
        $this->_encoder->endTag();

        // Start SYNC_FOLDERS
        $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERS);

        // Get the exporter.
        $exporter = new Horde_ActiveSync_Connector_Exporter_Sync(
            $this->_activeSync,
            $this->_encoder
        );

        // Loop through each collection and send all changes, replies, fetchids
        // etc...
        $cnt_global = 0;
        $over_window = false;
        foreach ($this->_collections as $id => $collection) {
            $statusCode = self::STATUS_SUCCESS;
            $changecount = 0;

            if ($over_window || $cnt_global > $this->_collections->getDefaultWindowSize()) {
                $this->_sendOverWindowResponse($collection);
                continue;
            }

            // Initialize this collection's state.
            try {
                $this->_collections->initCollectionState($collection);
            } catch (Horde_ActiveSync_Exception_StateGone $e) {
                $this->_logger->warn('SYNC terminating, state not found');
                $statusCode = self::STATUS_KEYMISM;
            } catch (Horde_ActiveSync_Exception_FolderGone $e) {
                // This isn't strictly correct, but at least some versions of
                // iOS need this in order to catch missing state.
                $this->_logger->err($e->getMessage());
                $statusCode = self::STATUS_FOLDERSYNC_REQUIRED;
            } catch (Horde_ActiveSync_Exception $e) {
                $this->_logger->err($e->getMessage());
                return false;
            }

            // Clients are allowed to NOT request changes. We still must check
            // for them since this would otherwise screw up conflict detection
            // (we can't update sync_ts until we actually check for changes). In
            // this case, we just don't send the changes back to the client
            // until the next SYNC that does set GETCHANGES using the
            // MOREAVAILABLE mechanism.
            if (!empty($collection['importedchanges']) && empty($collection['getchanges'])) {
                $forceChanges = true;
                $collection['getchanges'] = true;
                $this->_logger->notice('Forcing a GETCHANGES due to incoming changes.');
            }

            // Check for server-side changes, if requested.
            if ($statusCode == self::STATUS_SUCCESS && !empty($collection['getchanges'])) {
                try {
                    $changecount = $this->_collections->getCollectionChangeCount();
                } catch (Horde_ActiveSync_Exception_StaleState $e) {
                    $this->_logger->err(sprintf(
                        'Force restting of state for %s: %s',
                        $id,
                        $e->getMessage()));
                    $this->_state->loadState(
                        array(),
                        null,
                        Horde_ActiveSync::REQUEST_TYPE_SYNC,
                        $id);
                    $statusCode = self::STATUS_KEYMISM;
                } catch (Horde_ActiveSync_Exception_StateGone $e) {
                    $this->_logger->warn('SYNCKEY not found. Reset required.');
                        $statusCode = self::STATUS_KEYMISM;
                } catch (Horde_ActiveSync_Exception_FolderGone $e) {
                    $this->_logger->warn('FOLDERSYNC required, collection gone.');
                    $statusCode = self::STATUS_FOLDERSYNC_REQUIRED;
                } catch (Horde_ActiveSync_Exception_TemporaryFailure $e) {
                    $this->_logger->err(sprintf(
                        'Failure in polling for changes: "%s".',
                        $e->getMessage())
                    );
                    $statusCode = Horde_ActiveSync_Status::SERVER_ERROR_RETRY;
                } catch (Horde_Exception_AuthenticationFailure $e) {
                    $this->_logger->err('Lost authentication during SYNC!!');
                    $statusCode = self::STATUS_SERVERERROR;
                }
            }

            // Get new synckey if needed. We need a new synckey if any of the
            // following are true:
            //    - There are any changes (incoming or outgoing).
            //    - This is the initial sync pairing of the collection.
            //    - We received a SYNC due to changes found during a PING
            //      (See Bug: 12075).
            if ($statusCode == self::STATUS_SUCCESS &&
                (!empty($collection['importedchanges']) ||
                !empty($changecount) ||
                $collection['synckey'] == '0' ||
                $this->_state->getSyncKeyCounter($collection['synckey']) == 1 ||
                !empty($collection['fetchids']) ||
                $this->_collections->hasPingChangeFlag($id))) {

                try {
                    $collection['newsynckey'] = $this->_state->getNewSyncKeyWrapper($collection['synckey']);
                    $this->_logger->meta(sprintf(
                        'Old SYNCKEY: %s, New SYNCKEY: %s',
                        $collection['synckey'],
                        $collection['newsynckey'])
                    );
                } catch (Horde_ActiveSync_Exception $e) {
                    $statusCode = self::STATUS_KEYMISM;
                }
            }

            // Start SYNC_FOLDER
            $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDER);

            //SYNC_FOLDERTYPE
            $exporter->syncFolderType($collection);

            // SYNC_KEY
            $exporter->syncKey($collection);

            // SYNC_FOLDERID
            $exporter->syncFolderId($collection);

            // SYNC_STATUS
            $exporter->syncStatus($statusCode);

            if ($statusCode == self::STATUS_SUCCESS) {
                // Server changes
                if ($statusCode == self::STATUS_SUCCESS &&
                    empty($forceChanges) &&
                    !empty($collection['getchanges'])) {

                    $max_windowsize = !empty($pingSettings['maximumwindowsize'])
                        ? min($collection['windowsize'], $pingSettings['maximumwindowsize'])
                        : $collection['windowsize'];

                    // MOREAVAILABLE?
                    if (!empty($changecount) &&
                        (($changecount > $max_windowsize) || $cnt_global + $changecount > $this->_collections->getDefaultWindowSize())) {
                        $this->_logger->meta(sprintf(
                            'Sending MOREAVAILABLE. WINDOWSIZE = %d, $changecount = %d, MAX_REQUEST_WINDOWSIZE = %d, $cnt_global = %d',
                            $max_windowsize, $changecount, $this->_collections->getDefaultWindowSize(), $cnt_global)
                        );
                        $this->_encoder->startTag(Horde_ActiveSync::SYNC_MOREAVAILABLE, false, true);
                        $over_window = ($cnt_global + $changecount > $this->_collections->getDefaultWindowSize());
                    }

                    // Send each message now.
                    if (!empty($changecount)) {
                        $exporter->setChanges($this->_collections->getCollectionChanges(false), $collection);
                        $this->_encoder->startTag(Horde_ActiveSync::SYNC_COMMANDS);
                        $cnt_collection = 0;
                        while ($cnt_collection < $max_windowsize &&
                               $cnt_global < $this->_collections->getDefaultWindowSize() &&
                               $progress = $exporter->sendNextChange()) {
                            $this->_logger->meta(sprintf(
                                'Peak memory usage after message: %d',
                                memory_get_peak_usage(true))
                            );
                            if ($progress === true) {
                                ++$cnt_collection;
                                ++$cnt_global;
                            }
                        }
                        $this->_encoder->endTag();
                    }
                }

                // Check for SYNC_REPLIES
                if (!empty($collection['clientids']) || !empty($collection['fetchids'])
                    || !empty($collection['missing']) || !empty($collection['importfailures'])
                    || !empty($collection['modifiedids'])) {

                    // Start SYNC_REPLIES
                    $this->_encoder->startTag(Horde_ActiveSync::SYNC_REPLIES);

                    // SYNC_MODIFY failures
                    if (!empty($collection['importfailures'])) {
                        $exporter->modifyFailures($collection);
                    }

                    // EAS 16. CHANGED responses for items that need one. This
                    // is basically the results of any AirSyncBaseAttachments
                    // actions on Appointment or Draft Email items.
                    if ($this->_device->version >= Horde_ActiveSync::VERSION_SIXTEEN &&
                        !empty($collection['modifiedids'])) {
                        $exporter->syncModifiedResponse($collection);
                    }

                    // Server IDs for new items we received from client
                    if (!empty($collection['clientids'])) {
                        $exporter->syncAddResponse($collection);
                    }

                    // Errors from missing messages in REMOVE requests.
                    if (!empty($collection['missing'])) {
                        $exporter->missingRemove($collection);
                    }

                    if (!empty($collection['fetchids'])) {
                        $exporter->fetchIds($this->_driver, $collection);
                    }

                    // End SYNC_REPLIES
                    $this->_encoder->endTag();
                }

                // Save state
                if (!empty($collection['newsynckey'])) {
                    $this->_state->setNewSyncKey($collection['newsynckey']);
                    $this->_state->save();
                    // Add the new synckey to the syncCache
                    $this->_collections->addConfirmedKey($collection['newsynckey']);
                    $this->_collections->updateCollection(
                        $collection,
                        array('newsynckey' => true, 'unsetChanges' => true, 'unsetPingChangeFlag' => true)
                    );
                } elseif (!isset($changes)) {
                    // See if we could benefit from updating the collection's
                    // syncStamp value even though there were no changes. If
                    // $changes is set, we did a looping sync and already took
                    // care of this.
                    try {
                        $this->_state->updateSyncStamp();
                    } catch (Horde_ActiveSync_Exception $e) {
                        $this->_logger->err($e->getMessage());
                    }
                }
            }

            // End SYNC_FOLDER
            $this->_encoder->endTag();
            $this->_logger->meta(sprintf(
                'Collection output peak memory usage: %d',
                memory_get_peak_usage(true))
            );
        }

        // End SYNC_FOLDERS
        $this->_encoder->endTag();

        // End SYNC_SYNCHRONIZE
        $this->_encoder->endTag();

        if ($this->_device->version >= Horde_ActiveSync::VERSION_TWELVEONE) {
            if ($this->_collections->checkStaleRequest()) {
                $this->_logger->info('Changes detected in sync_cache during wait interval, exiting without updating cache.');
                return true;
            } else {
                $this->_collections->lastsyncendnormal = time();
                $this->_collections->save(true);
            }
        } else {
            $this->_collections->save(true);
        }

        return true;
    }


    protected function _sendOverWindowResponse($collection)
    {
        $this->_logger->meta('Over window maximum, skip polling for this request.');
        $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDER);

        // Not sent in > 12.0
        if ($this->_device->version <= Horde_ActiveSync::VERSION_TWELVE) {
            $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERTYPE);
            $this->_encoder->content($collection['class']);
            $this->_encoder->endTag();
        }

        $this->_encoder->startTag(Horde_ActiveSync::SYNC_SYNCKEY);
        $this->_encoder->content($collection['synckey']);
        $this->_encoder->endTag();

        $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERID);
        $this->_encoder->content($collection['id']);
        $this->_encoder->endTag();

        $this->_encoder->startTag(Horde_ActiveSync::SYNC_STATUS);
        $this->_encoder->content(self::STATUS_SUCCESS); //??
        $this->_encoder->endTag();
        $this->_encoder->startTag(Horde_ActiveSync::SYNC_MOREAVAILABLE, false, true);

        $this->_encoder->endTag();
        return;
    }

    /**
     * Helper method for parsing incoming SYNC_FOLDERS nodes.
     *
     * @return  boolean  False if any errors were encountered and handled.
     *                   Otherwise, true.
     *
     * @throws  Horde_ActiveSync_Exception when an error cannot be handled
     *          gracefully, and thus not able to send status code to client.
     *
     */
    protected function _parseSyncFolders()
    {
        while ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDER)) {
            $collection = $this->_collections->getNewCollection();
            while (($folder_tag = ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERTYPE) ? Horde_ActiveSync::SYNC_FOLDERTYPE :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_SYNCKEY) ? Horde_ActiveSync::SYNC_SYNCKEY :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERID) ? Horde_ActiveSync::SYNC_FOLDERID :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_SUPPORTED) ? Horde_ActiveSync::SYNC_SUPPORTED :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_DELETESASMOVES) ? Horde_ActiveSync::SYNC_DELETESASMOVES :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_GETCHANGES) ? Horde_ActiveSync::SYNC_GETCHANGES :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_WINDOWSIZE) ? Horde_ActiveSync::SYNC_WINDOWSIZE :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_CONVERSATIONMODE) ? Horde_ActiveSync::SYNC_CONVERSATIONMODE :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_OPTIONS) ? Horde_ActiveSync::SYNC_OPTIONS :
                   ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_COMMANDS) ? Horde_ActiveSync::SYNC_COMMANDS :
                   -1))))))))))) != -1) {

                switch ($folder_tag) {
                case Horde_ActiveSync::SYNC_FOLDERTYPE:
                    // According to docs, in 12.1 this is sent here, in > 12.1
                    // it is NOT sent here, it is sent in the ADD command ONLY.
                    // BUT, I haven't seen any 12.1 client actually send this.
                    // Only < 12.1 - leave version sniffing out in this case.
                    $collection['class'] = $this->_decoder->getElementContent();
                    if (!$this->_decoder->getElementEndTag()) {
                        throw new Horde_ActiveSync_Exception('Protocol error');
                    }
                    break;

                case Horde_ActiveSync::SYNC_SYNCKEY:
                    $collection['synckey'] = $this->_decoder->getElementContent();
                    if (!$this->_decoder->getElementEndTag()) {
                        throw new Horde_ActiveSync_Exception('Protocol error');
                    }
                    break;

                case Horde_ActiveSync::SYNC_FOLDERID:
                    $collection['id'] = $this->_decoder->getElementContent();
                    if ($collection['id'] === false) {
                        // Log this case explicitly since we can't send back
                        // a protocol error status (the response requires a
                        // collection id and we obviously don't have one).
                        $this->_logger->err('PROTOCOL ERROR. Client sent an empty SYNC_FOLDERID value.');
                        throw new Horde_ActiveSync_Exception('Protocol error');
                    }
                    if (!$this->_decoder->getElementEndTag()) {
                        throw new Horde_ActiveSync_Exception('Protocol error');
                    }
                    break;

                case Horde_ActiveSync::SYNC_WINDOWSIZE:
                    $collection['windowsize'] = $this->_decoder->getElementContent();
                    if (!$this->_decoder->getElementEndTag()) {
                        $this->_statusCode = self::STATUS_PROTERROR;
                        $this->_handleError($collection);
                        return false;
                    }
                    if ($collection['windowsize'] < 1 || $collection['windowsize'] > self::MAX_WINDOW_SIZE) {
                        $this->_logger->err('Bad windowsize sent, defaulting to 512');
                        $collection['windowsize'] = self::MAX_WINDOW_SIZE;
                    }
                    break;

                case Horde_ActiveSync::SYNC_CONVERSATIONMODE:
                    // Optional element, but if it's present with an empty value
                    // it defaults to true.
                    $collection['conversationmode'] = $this->_decoder->getElementContent();
                    if ($collection['conversationmode'] !== false && !$this->_decoder->getElementEndTag()) {
                        throw new Horde_ActiveSync_Exception('Protocol Error');
                    } elseif ($collection['conversationmode'] === false) {
                        $collection['conversationmode'] = true;
                    }

                    break;

                case Horde_ActiveSync::SYNC_SUPPORTED:
                    // Only allowed on initial sync request
                    if ($collection['synckey'] != '0') {
                        $this->_statusCode = self::STATUS_PROTERROR;
                        $this->_handleError($collection);
                        return false;
                    }
                    while (1) {
                        $el = $this->_decoder->getElement();
                        if ($this->_decoder->isEmptyElement($this->_decoder->getLastStartElement())) {
                            // MS-ASCMD 2.2.3.168 An empty SUPPORTED tag
                            // indicates that ALL elements able to be ghosted
                            // ARE ghosted.
                            $collection['supported'] = array(Horde_ActiveSync::ALL_GHOSTED);
                            break;
                        }
                        if ($el[Horde_ActiveSync_Wbxml::EN_TYPE] == Horde_ActiveSync_Wbxml::EN_TYPE_ENDTAG) {
                            break;
                        }
                        $collection['supported'][] = $el[2];
                    }
                    if (!empty($collection['supported'])) {
                        // Initial sync and we have SUPPORTED data - save it
                        if (empty($this->_device->supported)) {
                            $this->_device->supported = array();
                        }
                        // Not all clients send the $collection['class'] in more
                        // recent EAS versions. Grab it from the collection
                        // handler if needed.
                        if (empty($collection['class'])) {
                            $collection['class'] = $this->_collections->getCollectionClass($collection['id']);
                        }
                        $this->_device->supported[$collection['class']] = $collection['supported'];
                        $this->_device->save();
                    }
                    break;

                case Horde_ActiveSync::SYNC_DELETESASMOVES:
                    // Optional element, but if it's present with an empty value
                    // it defaults to true.
                    $collection['deletesasmoves'] = $this->_decoder->getElementContent();
                    if ($collection['deletesasmoves'] !== false && !$this->_decoder->getElementEndTag()) {
                        throw new Horde_ActiveSync_Exception('Protocol Error');
                    } elseif ($collection['deletesasmoves'] === false) {
                        $collection['deletesasmoves'] = true;
                    }
                    break;

                case Horde_ActiveSync::SYNC_GETCHANGES:
                    // Optional element, but if it's present with an empty value
                    // it defaults to true.
                    $collection['getchanges'] = $this->_decoder->getElementContent();
                    if ($collection['getchanges'] !== false && !$this->_decoder->getElementEndTag()) {
                        // Present, has a value, but no closing tag.
                        throw new Horde_ActiveSync_Exception('Protocol Error');
                    } elseif ($collection['getchanges'] === false) {
                        // Present, but is an empty tag, so defaults to true.
                        $collection['getchanges'] = true;
                    }
                    break;

                case Horde_ActiveSync::SYNC_OPTIONS:
                    if (!$this->_decoder->isEmptyElement($this->_decoder->getLastStartElement())) {
                        $this->_parseSyncOptions($collection);
                    }
                    break;

                case Horde_ActiveSync::SYNC_COMMANDS:
                    if (!$this->_parseSyncCommands($collection)) {
                        return false;
                    }
                }
            }

            if (!$this->_decoder->getElementEndTag()) {
                $this->_statusCode = self::STATUS_PROTERROR;
                $this->_handleError($collection);
                return false;
            }

            if (isset($collection['filtertype']) &&
                !$this->_collections->checkFilterType($collection['id'], $collection['filtertype'])) {
                $this->_logger->meta('Updated filtertype, will force a SOFTDELETE.');
                $collection['forcerefresh'] = true;
            }

            // Default value if missing is TRUE if we have a non-empty synckey,
            // otherwise FALSE.
            if (!isset($collection['getchanges'])) {
                $collection['getchanges'] = !empty($collection['synckey']);
            }

            try {
                $this->_collections->addCollection($collection);
            } catch (Horde_ActiveSync_Exception_StateGone $e) {
                $this->_statusCode = self::STATUS_NOTFOUND;
                $this->_handleError($collection);
                return false;
            }

            if (!empty($collection['importedchanges'])) {
                $this->_collections->importedChanges = true;
            }
            if ($this->_collections->collectionExists($collection['id']) && !empty($collection['windowsize'])) {
                $this->_collections->updateWindowSize($collection['id'], $collection['windowsize']);
            }
        }

        if (!$this->_decoder->getElementEndTag()) {
            $this->_logger->err('Parsing Error');
            return false;
        }

        return true;
    }

    /**
     * Handle incoming SYNC nodes
     *
     * @param array $collection  The current collection array.
     *
     * @return boolean
     */
    protected function _parseSyncCommands(&$collection)
    {
        // Some broken clients send SYNC_COMMANDS with a synckey of 0.
        // This is a violation of the spec, and could lead to all kinds
        // of data integrity issues.
        if (empty($collection['synckey'])) {
            $this->_logger->warn('Attempting a SYNC_COMMANDS, but device failed to send synckey. Ignoring.');
        }

        try {
            $this->_collections->initCollectionState($collection);
        } catch (Horde_ActiveSync_Exception_StateGone $e) {
            $this->_logger->warn('State not found sending STATUS_KEYMISM');
            $this->_statusCode = self::STATUS_KEYMISM;
            $this->_handleError($collection);
            return false;
        } catch (Horde_ActiveSync_Exception_StaleState $e) {
            $this->_logger->notice($e->getMessage());
            $this->_statusCode = self::STATUS_SERVERERROR;
            $this->_handleGlobalSyncError();
            return false;
        } catch (Horde_ActiveSync_Exception_FolderGone $e) {
            $this->_statusCode = self::STATUS_FOLDERSYNC_REQUIRED;
            $this->_handleError($collection);
            return false;
        } catch (Horde_ActiveSync_Exception $e) {
            $this->_logger->err($e->getMessage());
            $this->_statusCode = self::STATUS_SERVERERROR;
            $this->_handleGlobalSyncError();
            return false;
        }

        // Configure importer with last state
        if (!empty($collection['synckey'])) {
            $importer = $this->_activeSync->getImporter();
            $importer->init($this->_state, $collection['id'], $collection['conflict']);
        }
        $nchanges = 0;
        while (1) {
            // SYNC_MODIFY, SYNC_REMOVE, SYNC_ADD or SYNC_FETCH
            $element = $this->_decoder->getElement();
            if ($element[Horde_ActiveSync_Wbxml::EN_TYPE] != Horde_ActiveSync_Wbxml::EN_TYPE_STARTTAG) {
                $this->_decoder->_ungetElement($element);
                break;
            }
            $nchanges++;
            $commandType = $element[Horde_ActiveSync_Wbxml::EN_TAG];
            $instanceid = false;
            // Only sent during SYNC_MODIFY/SYNC_REMOVE/SYNC_FETCH
            if (($commandType == Horde_ActiveSync::SYNC_MODIFY ||
                 $commandType == Horde_ActiveSync::SYNC_REMOVE ||
                 $commandType == Horde_ActiveSync::SYNC_FETCH) &&
                $this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_SERVERENTRYID)) {

                $serverid = $this->_decoder->getElementContent();
                // Work around broken clients (Blackberry) that can send empty
                // $serverid values as a single empty <SYNC_SERVERENTRYID /> tag.
                if ($serverid !== false && !$this->_decoder->getElementEndTag()) {
                    $this->_statusCode = self::STATUS_PROTERROR;
                    $this->_handleGlobalSyncError();
                    $this->_logger->err('Parsing Error - expecting </SYNC_SERVERENTRYID>');
                    return false;
                }

                if ($this->_activeSync->device->version >= Horde_ActiveSync::VERSION_SIXTEEN) {
                    if ($this->_decoder->getElementStartTag(Horde_ActiveSync::AIRSYNCBASE_INSTANCEID)) {
                        $instanceid = $this->_decoder->getElementContent();
                        if ($instanceid !== false && !$this->_decoder->getElementEndTag()) {
                            $this->_statusCode = self::STATUS_PROTERROR;
                            $this->_handleGlobalSyncError();
                            $this->_logger->err('Parsing Error - expecting </AIRSYNCBASE_INSTANCEID>');
                            return false;
                        }
                    }
                }
            } else {
                $serverid = false;
            }

            // This tag is only sent here during > 12.1 and SYNC_ADD requests...
            // and it's not even sent by all clients. Parse it if it's there,
            // ignore it if not.
            if ($this->_activeSync->device->version > Horde_ActiveSync::VERSION_TWELVEONE &&
                $commandType == Horde_ActiveSync::SYNC_ADD &&
                $this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERTYPE)) {

                $collection['class'] = $this->_decoder->getElementContent();
                if (!$this->_decoder->getElementEndTag()) {
                    $this->_statusCode = self::STATUS_PROTERROR;
                    $this->_handleGlobalSyncError();
                    $this->_logger->err('Parsing Error - expecting </SYNC_FOLDERTYPE>');
                    return false;
                }
            }

            // Only sent during SYNC_ADD
            if ($commandType == Horde_ActiveSync::SYNC_ADD &&
                $this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_CLIENTENTRYID)) {
                $clientid = $this->_decoder->getElementContent();
                if (!$this->_decoder->getElementEndTag()) {
                    $this->_statusCode = self::STATUS_PROTERROR;
                    $this->_handleGlobalSyncError();
                    $this->_logger->err('Parsing Error - expecting </SYNC_CLIENTENTRYID>');
                    return false;
                }
            } else {
                $clientid = false;
            }

            // Create Message object from messages passed from client.
            // Only passed during SYNC_ADD or SYNC_MODIFY
            if (($commandType == Horde_ActiveSync::SYNC_ADD ||
                $commandType == Horde_ActiveSync::SYNC_MODIFY) &&
                ($el = $this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_DATA))) {
                if ($this->_decoder->isEmptyElement($el)) {
                    $this->_logger->err('Client sent an empty <Data> element. This is a protocol error, but attempting to ignore.');
                } else {
                    switch ($collection['class']) {
                    case Horde_ActiveSync::CLASS_EMAIL:
                        $appdata = Horde_ActiveSync::messageFactory('Mail');
                        $appdata->decodeStream($this->_decoder);
                        break;
                    case Horde_ActiveSync::CLASS_CONTACTS:
                        $appdata = Horde_ActiveSync::messageFactory('Contact');
                        $appdata->decodeStream($this->_decoder);
                        break;
                    case Horde_ActiveSync::CLASS_CALENDAR:
                        $appdata = Horde_ActiveSync::messageFactory('Appointment');
                        $appdata->decodeStream($this->_decoder);
                        // EAS 16.0 sends instanceid/serverid for exceptions.
                        if (!empty($instanceid) &&
                            $commandType == Horde_ActiveSync::SYNC_MODIFY) {
                            $appdata->instanceid = $instanceid;
                        }
                        break;
                    case Horde_ActiveSync::CLASS_TASKS:
                        $appdata = Horde_ActiveSync::messageFactory('Task');
                        $appdata->decodeStream($this->_decoder);
                        break;
                    case Horde_ActiveSync::CLASS_NOTES:
                        $appdata = Horde_ActiveSync::messageFactory('Note');
                        $appdata->decodeStream($this->_decoder);
                        break;
                    case Horde_ActiveSync::CLASS_SMS:
                        $appdata = Horde_ActiveSync::messageFactory('Mail');
                        $appdata->decodeStream($this->_decoder);
                        break;
                    }

                    if (!$this->_decoder->getElementEndTag()) {
                        // End application data
                        $this->_statusCode = self::STATUS_PROTERROR;
                        $this->_handleGlobalSyncError();
                        return false;
                    }
                    $appdata->commandType = $commandType;
                }
            }

            if (!empty($collection['synckey'])) {
                switch ($commandType) {
                case Horde_ActiveSync::SYNC_MODIFY:
                    if (isset($appdata)) {
                        $ires = $importer->importMessageChange(
                            $serverid, $appdata, $this->_device, false,
                            $collection['class'], $collection['synckey']
                        );
                        if (is_array($ires) && !empty($ires['error'])) {
                            $collection['importfailures'][$ires[0]] = $ires['error'];
                        } elseif (is_array($ires)) {
                            $collection['importedchanges'] = true;
                            if (empty($collection['modifiedids'])) {
                                $collection['modifiedids'] = array();
                            }
                            $collection['modifiedids'][] = $ires['id'];
                            $collection['atchash'][$serverid] = !empty($ires['atchash'])
                                ? $ires['atchash']
                                : array();
                        }
                    }
                    break;

                case Horde_ActiveSync::SYNC_ADD:
                    if (isset($appdata)) {
                        $ires = $importer->importMessageChange(
                            false, $appdata, $this->_device, $clientid,
                            $collection['class']
                        );
                        if (!$ires || !empty($ires['error'])) {
                            $collection['clientids'][$clientid] = false;
                        } elseif ($clientid && is_array($ires)) {
                            $collection['clientids'][$clientid] = $ires['id'];
                            $collection['atchash'][$ires['id']] = !empty($ires['atchash'])
                                ? $ires['atchash']
                                : array();
                            if (!empty($ires['conversationid'])) {
                                $collection['conversations'][$ires['id']] =
                                    array($ires['conversationid'],
                                          $ires['conversationindex']);
                            }
                            $collection['importedchanges'] = true;
                        } elseif (!$id || is_array($id)) {
                            $collection['clientids'][$clientid] = false;
                        }
                    }
                    break;

                case Horde_ActiveSync::SYNC_REMOVE:
                    if ($instanceid) {
                        $collection['instanceid_removes'][$serverid] = $instanceid;
                    } elseif ($serverid) {
                        // Work around broken clients that send empty $serverid.
                        $collection['removes'][] = $serverid;
                    }
                    break;

                case Horde_ActiveSync::SYNC_FETCH:
                    $collection['fetchids'][] = $serverid;
                    break;
                }
            }

            if (!$this->_decoder->getElementEndTag()) {
                $this->_statusCode = self::STATUS_PROTERROR;
                $this->_handleGlobalSyncError();
                $this->_logger->err('Parsing error');
                return false;
            }
        }

        // Do all the SYNC_REMOVE requests at once
        if (!empty($collection['removes']) &&
            !empty($collection['synckey'])) {
            if (!empty($collection['deletesasmoves']) && $folderid = $this->_driver->getWasteBasket($collection['class'])) {
                $results = $importer->importMessageMove($collection['removes'], $folderid);
            } else {
                $results = $importer->importMessageDeletion($collection['removes'], $collection['class']);
                if (is_array($results)) {
                    $results['results'] = $results;
                    $results['missing'] = array_diff($collection['removes'], $results['results']);
                }
            }
            if (!empty($results['missing'])) {
                $collection['missing'] = $results['missing'];
            }
            unset($collection['removes']);
            $collection['importedchanges'] = true;
        }
        // EAS 16.0 instance deletions.
        if (!empty($collection['instanceid_removes']) && !empty($collection['synckey'])) {
                foreach ($collection['instanceid_removes'] as $uid => $instanceid) {
                    $importer->importMessageDeletion(array($uid => $instanceid), $collection['class'], true);
                }
            unset($collection['instanceid_removes']);
        }

        $this->_logger->info(sprintf('Processed %d incoming changes', $nchanges));

        if (!$this->_decoder->getElementEndTag()) {
            // end commands
            $this->_statusCode = self::STATUS_PROTERROR;
            $this->_handleGlobalSyncError();
            $this->_logger->err('PARSING ERROR');
            return false;
        }

        return true;
    }

    /**
     * Helper method to handle incoming OPTIONS nodes.
     *
     * @param array $collection  The current collection array.
     */
    public function _parseSyncOptions(&$collection)
    {
        $options = array();
        $haveElement = false;

        // These can be sent in any order.
        while(1) {
            if ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FILTERTYPE)) {
                $options['filtertype'] = $this->_decoder->getElementContent();
                $haveElement = true;
                if (!$this->_decoder->getElementEndTag()) {
                    $this->_statusCode = self::STATUS_PROTERROR;
                    $this->_handleError($collection);
                    exit;
                }
            }

            // EAS > 12.1 the Collection Class can be part of OPTIONS.
            if ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERTYPE)) {
                $haveElement = true;
                $options['class'] = $this->_decoder->getElementContent();
                if (!$this->_decoder->getElementEndTag()) {
                    $this->_statusCode = self::STATUS_PROTERROR;
                    $this->_handleError($collection);
                    exit;
                }
            }

            if ($this->_decoder->getElementStartTag(Horde_ActiveSync::AIRSYNCBASE_BODYPREFERENCE)) {
                $this->_bodyPrefs($options);
            }

            if ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_CONFLICT)) {
                $haveElement = true;
                $options['conflict'] = $this->_decoder->getElementContent();
                if (!$this->_decoder->getElementEndTag()) {
                    $this->_statusCode = self::STATUS_PROTERROR;
                    $this->_handleError($collection);
                    exit;
                }
            }

            if ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_MIMESUPPORT)) {
                $haveElement = true;
                $this->_mimeSupport($options);
            }

            // SYNC_MIMETRUNCATION is used when no SYNC_BODYPREFS element is sent.
            if ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_MIMETRUNCATION)) {
                $haveElement = true;
                $options['mimetruncation'] = Horde_ActiveSync::getMIMETruncSize($this->_decoder->getElementContent());
                if (!$this->_decoder->getElementEndTag()) {
                    $this->_statusCode = self::STATUS_PROTERROR;
                    $this->_handleError($collection);
                    exit;
                }
            }

            // SYNC_TRUNCATION only applies to the body of non-email collections
            // or the BODY element of an Email in EAS 2.5.
            if ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_TRUNCATION)) {
                $haveElement = true;
                $options['truncation'] = Horde_ActiveSync::getTruncSize($this->_decoder->getElementContent());
                if (!$this->_decoder->getElementEndTag()) {
                    $this->_statusCode = self::STATUS_PROTERROR;
                    $this->_handleError($collection);
                    exit;
                }
            }

            // @todo This seems to no longer be supported by the specs? Probably
            // a leftover from EAS 1 or 2.0. Remove in H6.
            if ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_RTFTRUNCATION)) {
                $haveElement = true;
                $options['rtftruncation'] = $this->_decoder->getElementContent();
                if (!$this->_decoder->getElementEndTag()) {
                    $this->_statusCode = self::STATUS_PROTERROR;
                    $this->_handleError($collection);
                    exit;
                }
            }

            if ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_MAXITEMS)) {
                $haveElement = true;
                $options['maxitems'] = $this->_decoder->getElementContent();
                if (!$this->_decoder->getElementEndTag()) {
                    $this->_statusCode = self::STATUS_PROTERROR;
                    $this->_handleError($collection);
                    exit;
                }
            }

            // EAS 14.1
            if ($this->_device->version >= Horde_ActiveSync::VERSION_FOURTEENONE) {
                if ($this->_decoder->getElementStartTag(Horde_ActiveSync::RM_SUPPORT)) {
                    $haveElement = true;
                    $this->_rightsManagement($options);
                }
                if ($this->_decoder->getElementStartTag(Horde_ActiveSync::AIRSYNCBASE_BODYPARTPREFERENCE)) {
                    $haveElement = true;
                    $this->_bodyPartPrefs($options);
                }
            }

            $e = $this->_decoder->peek();
            if ($e[Horde_ActiveSync_Wbxml::EN_TYPE] == Horde_ActiveSync_Wbxml::EN_TYPE_ENDTAG) {
                $this->_decoder->getElementEndTag();
                break;
            } elseif (!$haveElement) {
                $depth = 0;
                while (1) {
                    $e = $this->_decoder->getElement();
                    if ($e === false) {
                        $this->_logger->err('Unexpected end of stream.');
                        $this->_statusCode = self::STATUS_PROTERROR;
                        $this->_handleError($collection);
                        exit;
                    } elseif ($e[Horde_ActiveSync_Wbxml::EN_TYPE] == Horde_ActiveSync_Wbxml::EN_TYPE_STARTTAG) {
                        $depth = $this->_decoder->isEmptyElement($e) ?
                            $depth :
                            $depth + 1;
                    } elseif ($e[Horde_ActiveSync_Wbxml::EN_TYPE] == Horde_ActiveSync_Wbxml::EN_TYPE_ENDTAG) {
                        $depth--;
                    }
                    if ($depth == 0) {
                        break;
                    }
                }
            }
        }

        // Default to no filter as per the specs.
        if (!isset($options['filtertype'])) {
            $options['filtertype'] = '0';
        }

        if (!empty($options['class']) && $options['class'] == 'SMS') {
            return;
        }

        $collection = array_merge($collection, $options);
    }

    /**
     * Helper for sending error status results.
     *
     * @param boolean $limit  Send the SYNC_LIMIT error if true.
     */
    protected function _handleGlobalSyncError($limit = false)
    {
        $this->_encoder->StartWBXML();
        $this->_encoder->startTag(Horde_ActiveSync::SYNC_SYNCHRONIZE);
        $this->_encoder->startTag(Horde_ActiveSync::SYNC_STATUS);
        $this->_encoder->content($this->_statusCode);
        $this->_encoder->endTag();
        if ($limit !== false) {
            $this->_encoder->startTag(Horde_ActiveSync::SYNC_LIMIT);
            $this->_encoder->content($limit);
            $this->_encoder->endTag();
        }
        $this->_encoder->endTag();
    }

    /**
     * Helper for handling sync errors
     *
     * @param array $collection
     */
    protected function _handleError(array $collection)
    {
        $this->_encoder->startWBXML();
        $this->_encoder->startTag(Horde_ActiveSync::SYNC_SYNCHRONIZE);

        $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERS);

        // Get new synckey if needed
        if ($this->_statusCode == self::STATUS_KEYMISM ||
            !empty($collection['importedchanges']) ||
            !empty($collection['getchanges']) ||
            $collection['synckey'] == '0') {

            $collection['newsynckey'] = Horde_ActiveSync_State_Base::getNewSyncKey(($this->_statusCode == self::STATUS_KEYMISM) ? 0 : $collection['synckey']);
            if ($collection['synckey'] != '0') {
                $this->_state->removeState(array('synckey' => $collection['synckey']));
            }
        }

        $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDER);

        $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERTYPE);
        $this->_encoder->content($collection['class']);
        $this->_encoder->endTag();

        $this->_encoder->startTag(Horde_ActiveSync::SYNC_SYNCKEY);
        if (!empty($collection['newsynckey'])) {
            $this->_encoder->content($collection['newsynckey']);
        } else {
            $this->_encoder->content($collection['synckey']);
        }
        $this->_encoder->endTag();

        $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERID);
        $this->_encoder->content($collection['id']);
        $this->_encoder->endTag();

        $this->_encoder->startTag(Horde_ActiveSync::SYNC_STATUS);
        $this->_encoder->content($this->_statusCode);
        $this->_encoder->endTag();

        $this->_encoder->endTag(); // Horde_ActiveSync::SYNC_FOLDER
        $this->_encoder->endTag();
        $this->_encoder->endTag();
    }

}
