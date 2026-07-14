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
 *   Consult LICENSE file for details
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2009-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Handle Sync requests
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2009-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 * @internal
 */
class Horde_ActiveSync_Request_Sync extends Horde_ActiveSync_Request_SyncBase
{
    /* Status */
    public const STATUS_SUCCESS                = 1;
    public const STATUS_VERSIONMISM            = 2;
    public const STATUS_KEYMISM                = 3;
    public const STATUS_PROTERROR              = 4;
    public const STATUS_SERVERERROR            = 5;
    public const STATUS_INVALID                = 6;
    public const STATUS_CONFLICT               = 7;
    public const STATUS_NOTFOUND               = 8;

    // 12.1
    public const STATUS_FOLDERSYNC_REQUIRED    = 12;
    public const STATUS_REQUEST_INCOMPLETE     = 13;
    public const STATUS_INVALID_WAIT_HEARTBEAT = 14;

    /* Maximum window size (12.1 only) */
    public const MAX_WINDOW_SIZE    = 512;

    /* Maximum HEARTBEAT value (seconds) (12.1 only) */
    public const MAX_HEARTBEAT      = 3540;

    /**
     * Collections manager.
     *
     * @var Horde_ActiveSync_Collections
     */
    protected $_collections;

    /**
     * Whether this Sync response is streamed to the client while the
     * handler is still running (opt-in via the 'streaming' sync setting).
     *
     * @var boolean
     */
    protected $_streaming = false;

    /**
     * Client-sent Sync commands queued during request parsing for deferred
     * import during response output (streaming only), keyed by collection
     * id. Kept outside the collection arrays since entries contain message
     * objects that must not end up in the sync cache or be serialized for
     * partial-sync comparison.
     *
     * Each entry: array of
     *   ['type' => SYNC_ADD|SYNC_MODIFY, 'serverid' => string|false,
     *    'clientid' => string|false, 'appdata' => message object|null]
     *
     * @var array
     */
    protected $_deferredCommands = [];

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

        // Needed before parsing: when streaming, client-sent Sync commands
        // are queued during parse and imported during response output (so
        // response bytes flow while the server works; see
        // _runDeferredSyncCommands()).
        $syncSettings = $this->_driver->getSyncConfig();
        $this->_streaming = !empty($syncSettings['streaming']);
        $streaming = $this->_streaming;

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
            while (($sync_tag = ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_WINDOWSIZE) ? Horde_ActiveSync::SYNC_WINDOWSIZE
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERS) ? Horde_ActiveSync::SYNC_FOLDERS
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_PARTIAL) ? Horde_ActiveSync::SYNC_PARTIAL
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_WAIT) ? Horde_ActiveSync::SYNC_WAIT
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_HEARTBEATINTERVAL) ? Horde_ActiveSync::SYNC_HEARTBEATINTERVAL
                   : -1)))))) != -1) {

                switch ($sync_tag) {
                    case Horde_ActiveSync::SYNC_HEARTBEATINTERVAL:
                        if ($hbinterval = $this->_decoder->getElementContent()) {
                            $this->_collections->setHeartbeat(['hbinterval' => $hbinterval]);
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
                            $this->_collections->setHeartbeat(['wait' => $wait]);
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
                if ($this->_collections->hbinterval !== false
                    && $this->_collections->wait !== false) {

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
        $syncTimeBudget = !empty($syncSettings['maxresponsetime'])
            ? (int) $syncSettings['maxresponsetime']
            : 0;
        if ($streaming && $syncTimeBudget > 0) {
            /* Streaming supersedes the export-phase time budget: the
             * Commands buffering the budget needs for MOREAVAILABLE
             * reordering would defeat per-message flushing. */
            $this->_logger->meta('Ignoring maxresponsetime; Sync response streaming is enabled.');
            $syncTimeBudget = 0;
        }
        /* Count-based batch cap (streaming only). Capping the window before
         * the Commands section starts lets truncation reuse the
         * window-exceeded path, keeping MOREAVAILABLE before Commands
         * without buffering. */
        $maxMessagesPerResponse = $streaming
            ? (int) ($syncSettings['maxmessagesperresponse'] ?? 10)
            : 0;
        /* Soft per-message assembly cap and whole-request wall clock
         * (streaming only); both leave unsent changes in sync_pending. */
        $maxMessageTime = $streaming
            ? (int) ($syncSettings['maxmessagetime'] ?? 0)
            : 0;
        $maxRequestDuration = $streaming
            ? (int) ($syncSettings['maxrequestduration'] ?? 0)
            : 0;
        $requestServerVars = $this->_activeSync->request->getServerVars();
        $requestStart = !empty($requestServerVars['REQUEST_TIME_FLOAT'])
            ? (float) $requestServerVars['REQUEST_TIME_FLOAT']
            : microtime(true);

        // Override the total, per-request, WINDOWSIZE?
        if (!empty($pingSettings['maximumrequestwindowsize'])) {
            $this->_collections->setDefaultWindowSize($pingSettings['maximumrequestwindowsize'], true);
        }

        // If this is >= 12.1, see if we want a looping SYNC.
        if ($this->_collections->canDoLoopingSync()
            && $this->_device->version >= Horde_ActiveSync::VERSION_TWELVEONE
            && $this->_statusCode == self::STATUS_SUCCESS) {

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
                    case Horde_ActiveSync_Collections::COLLECTION_ERR_FOLDERSYNC_REQUIRED:
                        $this->_statusCode = self::STATUS_FOLDERSYNC_REQUIRED;
                        $this->_handleGlobalSyncError();
                        return true;
                    case Horde_ActiveSync_Collections::COLLECTION_ERR_SYNC_REQUIRED:
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
        if ($this->_device->version >= Horde_ActiveSync::VERSION_TWELVEONE
            && $this->_statusCode == self::STATUS_SUCCESS
            && empty($changes)
            && $this->_collections->canSendEmptyResponse()) {

            $this->_logger->info('Sending an empty SYNC response.');
            $this->_collections->lastsyncendnormal = time();
            $this->_collections->save(true);
            return true;
        }

        $this->_logger->info(
            sprintf(
                'Completed parsing incoming request. Peak memory usage: %d.',
                memory_get_peak_usage(true)
            )
        );

        // Start output to client
        $syncOutputStart = microtime(true);
        $this->_logger->info(sprintf(
            'SYNC: starting response output %.1fs after request start (streaming %s).',
            $syncOutputStart - $requestStart,
            $streaming ? 'on' : 'off'
        ));
        $this->_encoder->startWBXML();
        $this->_encoder->startTag(Horde_ActiveSync::SYNC_SYNCHRONIZE);
        $this->_encoder->startTag(Horde_ActiveSync::SYNC_STATUS);
        $this->_encoder->content(self::STATUS_SUCCESS);
        $this->_encoder->endTag();
        if ($streaming) {
            /* First body bytes on the wire; keeps clients with hard read
             * timeouts (Gmail ~30s) from aborting while messages are
             * assembled below. */
            $this->_encoder->flushOutput();
        }

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
                // Client-sent commands must still be imported (matching the
                // non-streaming flow, where imports happen during parsing
                // even for over-window collections). Replies are skipped,
                // exactly like the non-streaming over-window response; the
                // client re-sends and duplicate detection resolves it.
                if (!empty($this->_deferredCommands[$id])) {
                    try {
                        $this->_collections->initCollectionState($collection);
                        $this->_runDeferredSyncCommands($collection);
                    } catch (Horde_ActiveSync_Exception $e) {
                        $this->_logger->err(sprintf(
                            'Unable to import deferred commands for over-window collection %s: %s',
                            $id,
                            $e->getMessage()
                        ));
                    }
                }
                $this->_sendOverWindowResponse($collection);
                continue;
            }

            // Initialize this collection's state.
            try {
                $this->_collections->initCollectionState($collection);
            } catch (Horde_ActiveSync_Exception_StaleState $e) {
                $this->_logger->err(sprintf(
                    'Force resetting state for %s: %s',
                    $id,
                    $e->getMessage()
                ));
                $this->_state->loadState(
                    [],
                    null,
                    Horde_ActiveSync::REQUEST_TYPE_SYNC,
                    $id
                );
                $statusCode = self::STATUS_KEYMISM;
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

            // Import client-sent commands deferred during parsing
            // (streaming only). Runs at the same logical position as the
            // legacy inline imports - before change detection and synckey
            // generation - but now with response bytes already on the wire
            // and keep-alive tokens flushed between imports, so clients
            // with hard read timeouts (Gmail Android ~30s) do not abort
            // while a large batch (e.g. a full Drafts up-sync) is written
            // to the backend.
            if ($statusCode == self::STATUS_SUCCESS) {
                $this->_runDeferredSyncCommands($collection);
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
                        $e->getMessage()
                    ));
                    $this->_state->loadState(
                        [],
                        null,
                        Horde_ActiveSync::REQUEST_TYPE_SYNC,
                        $id
                    );
                    $statusCode = self::STATUS_KEYMISM;
                } catch (Horde_ActiveSync_Exception_StateGone $e) {
                    $this->_logger->warn('SYNCKEY not found. Reset required.');
                    $statusCode = self::STATUS_KEYMISM;
                } catch (Horde_ActiveSync_Exception_FolderGone $e) {
                    $this->_logger->warn('FOLDERSYNC required, collection gone.');
                    $statusCode = self::STATUS_FOLDERSYNC_REQUIRED;
                } catch (Horde_ActiveSync_Exception_TemporaryFailure $e) {
                    $this->_logger->err(
                        sprintf(
                            'Failure in polling for changes: "%s".',
                            $e->getMessage()
                        )
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
            if ($statusCode == self::STATUS_SUCCESS
                && (!empty($collection['importedchanges'])
                || !empty($changecount)
                || $collection['synckey'] == '0'
                || $this->_state->getSyncKeyCounter($collection['synckey']) == 1
                || !empty($collection['fetchids'])
                || $this->_collections->hasPingChangeFlag($id))) {

                try {
                    $collection['newsynckey'] = $this->_state->getNewSyncKeyWrapper($collection['synckey']);
                    $this->_logger->meta(
                        sprintf(
                            'Old SYNCKEY: %s, New SYNCKEY: %s',
                            $collection['synckey'],
                            $collection['newsynckey']
                        )
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
            $exporter->syncStatus($this->_resolveFolderSyncRequiredStatus($statusCode));

            if ($statusCode == self::STATUS_SUCCESS) {
                // Server changes
                if ($statusCode == self::STATUS_SUCCESS
                    && empty($forceChanges)
                    && !empty($collection['getchanges'])) {

                    $max_windowsize = !empty($pingSettings['maximumwindowsize'])
                        ? min($collection['windowsize'], $pingSettings['maximumwindowsize'])
                        : $collection['windowsize'];
                    $max_windowsize = $this->_streamingMaxWindowSize(
                        $max_windowsize,
                        $streaming,
                        $maxMessagesPerResponse
                    );

                    $countExceedsWindow = !empty($changecount)
                        && (($changecount > $max_windowsize)
                        || $cnt_global + $changecount > $this->_collections->getDefaultWindowSize());

                    $useCommandBuffer = $this->_useSyncCommandsBuffer(
                        $syncTimeBudget,
                        $changecount,
                        $max_windowsize,
                        $cnt_global,
                        $this->_collections->getDefaultWindowSize(),
                        $streaming
                    );

                    // MOREAVAILABLE?
                    if ($countExceedsWindow) {
                        $this->_logger->meta(
                            sprintf(
                                'Sending MOREAVAILABLE. WINDOWSIZE = %d, $changecount = %d, MAX_REQUEST_WINDOWSIZE = %d, $cnt_global = %d',
                                $max_windowsize,
                                $changecount,
                                $this->_collections->getDefaultWindowSize(),
                                $cnt_global
                            )
                        );
                        $this->_encoder->startTag(Horde_ActiveSync::SYNC_MOREAVAILABLE, false, true);
                        $over_window = ($cnt_global + $changecount > $this->_collections->getDefaultWindowSize());
                    }

                    // Send each message now.
                    if (!empty($changecount)) {
                        $exporter->setChanges($this->_collections->getCollectionChanges(false), $collection);

                        $commandsBuffer = null;
                        $mainStream = null;
                        if ($useCommandBuffer) {
                            $commandsBuffer = new Horde_Stream_Temp();
                            $mainStream = $this->_encoder->swapOutputStream($commandsBuffer);
                        }

                        $this->_encoder->startTag(Horde_ActiveSync::SYNC_COMMANDS);
                        $cnt_collection = 0;
                        $timeBudgetExceeded = false;
                        /* sendNextChange() returns true on successful export,
                         * false when no more changes remain or on non-fatal
                         * error (remaining batch preserved in sync_pending). */
                        while ($cnt_collection < $max_windowsize
                               && $cnt_global < $this->_collections->getDefaultWindowSize()) {
                            if ($exporter->hasPendingChanges() && $this->_isSyncTimeBudgetExceeded(
                                $syncOutputStart,
                                $syncTimeBudget,
                                $cnt_collection
                            )) {
                                $this->_logger->info(sprintf(
                                    'Sync time budget (%ds) reached after %d change(s) in collection %s (%.1fs elapsed); MOREAVAILABLE.',
                                    $syncTimeBudget,
                                    $cnt_collection,
                                    $collection['id'],
                                    microtime(true) - $syncOutputStart
                                ));
                                $timeBudgetExceeded = true;
                                break;
                            }

                            $msgStart = microtime(true);
                            try {
                                $progress = $exporter->sendNextChange();
                            } catch (Horde_Exception $e) {
                                if (!$streaming) {
                                    throw $e;
                                }
                                /* Post-commit abort: body bytes are already
                                 * on the wire, so finish a valid WBXML
                                 * envelope instead of letting the RPC layer
                                 * attempt an HTTP 500. Unsent changes stay
                                 * in sync_pending. */
                                $this->_logger->err(sprintf(
                                    'Streaming SYNC: aborting export for collection %s after error: %s',
                                    $collection['id'],
                                    $e->getMessage()
                                ));
                                break;
                            }
                            if ($progress !== true) {
                                break;
                            }
                            if ($streaming) {
                                $this->_encoder->flushOutput();
                            }
                            $this->_logger->meta(
                                sprintf(
                                    'Peak memory usage after message: %d',
                                    memory_get_peak_usage(true)
                                )
                            );
                            ++$cnt_collection;
                            ++$cnt_global;

                            if (!$exporter->hasPendingChanges()) {
                                continue;
                            }
                            if ($this->_isGuardExceeded($msgStart, $maxMessageTime)) {
                                $this->_logger->warn(sprintf(
                                    'SYNC: single message took %.1fs (cap %ds) in collection %s; stopping batch, remaining changes stay in sync_pending.',
                                    microtime(true) - $msgStart,
                                    $maxMessageTime,
                                    $collection['id']
                                ));
                                break;
                            }
                            if ($this->_isGuardExceeded($requestStart, $maxRequestDuration)) {
                                $this->_logger->warn(sprintf(
                                    'SYNC: request duration %.1fs exceeds cap (%ds) after %d change(s) in collection %s; stopping batch, remaining changes stay in sync_pending.',
                                    microtime(true) - $requestStart,
                                    $maxRequestDuration,
                                    $cnt_collection,
                                    $collection['id']
                                ));
                                break;
                            }
                        }
                        $this->_encoder->endTag();

                        if ($useCommandBuffer) {
                            $this->_encoder->swapOutputStream($mainStream);
                            if ($timeBudgetExceeded || $exporter->hasPendingChanges()) {
                                $this->_encoder->startTag(Horde_ActiveSync::SYNC_MOREAVAILABLE, false, true);
                            }
                            $this->_encoder->appendOutputStream($commandsBuffer);
                        }
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
                    if ($this->_device->version >= Horde_ActiveSync::VERSION_SIXTEEN
                        && !empty($collection['modifiedids'])) {
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
                        ['newsynckey' => true, 'unsetChanges' => true, 'unsetPingChangeFlag' => true]
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
            if ($streaming) {
                $this->_encoder->flushOutput();
            }
            $this->_logger->meta(
                sprintf(
                    'Collection output peak memory usage: %d',
                    memory_get_peak_usage(true)
                )
            );
        }

        // End SYNC_FOLDERS
        $this->_encoder->endTag();

        // End SYNC_SYNCHRONIZE
        $this->_encoder->endTag();
        if ($streaming) {
            $this->_encoder->flushOutput();
        }

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

    /**
     * Should Sync Commands be buffered so MOREAVAILABLE can precede Commands
     * when a time budget stops the batch early?
     *
     * Never buffer when streaming: buffering would hold all Commands bytes
     * back until the batch completes, defeating per-message flushing.
     * Truncation ordering is handled up front via the count-capped window
     * instead (@see _streamingMaxWindowSize()).
     *
     * @param integer $syncTimeBudget
     * @param integer $changecount
     * @param integer $maxWindowsize
     * @param integer $cntGlobal
     * @param integer $defaultWindowSize
     * @param boolean $streaming
     *
     * @return boolean
     */
    protected function _useSyncCommandsBuffer(
        $syncTimeBudget,
        $changecount,
        $maxWindowsize,
        $cntGlobal,
        $defaultWindowSize,
        $streaming = false
    ) {
        $countExceedsWindow = !empty($changecount)
            && (($changecount > $maxWindowsize)
            || $cntGlobal + $changecount > $defaultWindowSize);

        return !$streaming
            && $syncTimeBudget > 0
            && !empty($changecount)
            && !$countExceedsWindow;
    }

    /**
     * Reduce the effective per-collection window when streaming with a
     * count-based response cap (maxmessagesperresponse).
     *
     * Capping the window before the Commands section starts means the
     * truncation decision is known up front, so MOREAVAILABLE can be
     * emitted before Commands (the only MS-ASCMD-valid ordering) without
     * buffering the Commands output.
     *
     * @param integer $maxWindowsize          Effective window so far.
     * @param boolean $streaming              Streaming enabled?
     * @param integer $maxMessagesPerResponse Count cap, 0 = disabled.
     *
     * @return integer
     */
    protected function _streamingMaxWindowSize(
        $maxWindowsize,
        $streaming,
        $maxMessagesPerResponse
    ) {
        if ($streaming && $maxMessagesPerResponse > 0) {
            return min($maxWindowsize, $maxMessagesPerResponse);
        }

        return $maxWindowsize;
    }

    /**
     * Has an elapsed-time guard been exceeded?
     *
     * @param float $start    Start time (microtime).
     * @param integer $limit  Limit in seconds, 0 = disabled.
     *
     * @return boolean
     */
    protected function _isGuardExceeded($start, $limit)
    {
        return $limit > 0 && (microtime(true) - $start) >= $limit;
    }

    /**
     * Has the per-response sync time budget been reached?
     *
     * The first change in a batch is always allowed even if it exceeds the
     * budget, so clients never receive an empty Commands block with
     * MOREAVAILABLE.
     *
     * @param float $syncOutputStart
     * @param integer $budget
     * @param integer $cntCollection
     *
     * @return boolean
     */
    protected function _isSyncTimeBudgetExceeded(
        $syncOutputStart,
        $budget,
        $cntCollection
    ) {
        return $budget > 0
            && $cntCollection > 0
            && (microtime(true) - $syncOutputStart) >= $budget;
    }

    /**
     * Import a single client-sent ADD or MODIFY command and record the
     * result in the collection array (replies, failures, atchash, ...).
     *
     * Shared by the inline (non-streaming) parse-phase import and the
     * deferred (streaming) output-phase import.
     *
     * @param Horde_ActiveSync_Connector_Importer $importer  The importer.
     * @param array $collection    The collection array, updated in place.
     * @param string $commandType  SYNC_ADD or SYNC_MODIFY.
     * @param string|boolean $serverid  Server id for MODIFY, false for ADD.
     * @param string|boolean $clientid  Client id for ADD, false for MODIFY.
     * @param Horde_ActiveSync_Message_Base $appdata  The message data.
     */
    protected function _importSyncCommand(
        $importer,
        array &$collection,
        $commandType,
        $serverid,
        $clientid,
        $appdata
    ) {
        switch ($commandType) {
            case Horde_ActiveSync::SYNC_MODIFY:
                $ires = $importer->importMessageChange(
                    $serverid,
                    $appdata,
                    $this->_device,
                    false,
                    $collection['class'],
                    $collection['synckey']
                );
                if (is_array($ires) && !empty($ires['error'])) {
                    $collection['importfailures'][$ires[0]] = $ires['error'];
                } elseif (is_array($ires)) {
                    $collection['importedchanges'] = true;
                    if (empty($collection['modifiedids'])) {
                        $collection['modifiedids'] = [];
                    }
                    $collection['modifiedids'][] = $ires['id'];
                    $collection['atchash'][$ires['id']] = !empty($ires['atchash'])
                        ? $ires['atchash']
                        : [];
                }
                break;

            case Horde_ActiveSync::SYNC_ADD:
                $ires = $importer->importMessageChange(
                    false,
                    $appdata,
                    $this->_device,
                    $clientid,
                    $collection['class']
                );
                if (!$ires || !empty($ires['error'])) {
                    $collection['clientids'][$clientid] = false;
                } elseif ($clientid && is_array($ires)) {
                    $collection['clientids'][$clientid] = $ires['id'];
                    $collection['atchash'][$ires['id']] = !empty($ires['atchash'])
                        ? $ires['atchash']
                        : [];
                    if (!empty($ires['conversationid'])) {
                        $collection['conversations'][$ires['id']]
                            = [$ires['conversationid'],
                                $ires['conversationindex']];
                    }
                    $collection['importedchanges'] = true;
                } elseif ($clientid && is_string($ires)) {
                    // Duplicate addition; client never received UID.
                    $collection['clientids'][$clientid] = $ires;
                    $collection['importedchanges'] = true;
                } elseif ($clientid) {
                    $collection['clientids'][$clientid] = false;
                }
                break;
        }
    }

    /**
     * Import a batch of client-sent REMOVE commands.
     *
     * @param Horde_ActiveSync_Connector_Importer $importer  The importer.
     * @param array $collection      The collection array, updated in place.
     * @param array $removes         Server uids to remove.
     * @param boolean $deletesasmoves  Move to trash instead of deleting.
     */
    protected function _importRemoves(
        $importer,
        array &$collection,
        array $removes,
        $deletesasmoves
    ) {
        if ($deletesasmoves
            && $folderid = $this->_driver->getWasteBasket($collection['class'])) {
            $results = $importer->importMessageMove($removes, $folderid);
        } else {
            $results = $importer->importMessageDeletion($removes, $collection['class']);
            if (is_array($results)) {
                $results['results'] = $results;
                $results['missing'] = array_diff($removes, $results['results']);
            }
        }
        if (!empty($results['missing'])) {
            $collection['missing'] = $results['missing'];
        }
        $collection['importedchanges'] = true;
    }

    /**
     * Import client-sent EAS 16.0 instance deletions.
     *
     * @param Horde_ActiveSync_Connector_Importer $importer  The importer.
     * @param array $collection         The collection array.
     * @param array $instanceidRemoves  Hash of uid => instanceid.
     */
    protected function _importInstanceIdRemoves(
        $importer,
        array &$collection,
        array $instanceidRemoves
    ) {
        foreach ($instanceidRemoves as $uid => $instanceid) {
            $importer->importMessageDeletion([$uid => $instanceid], $collection['class'], true);
        }
    }

    /**
     * Import client-sent Sync commands that were queued during request
     * parsing (streaming only).
     *
     * Runs during response output, after the WBXML preamble has been
     * flushed, at the same logical position the inline imports of the
     * non-streaming flow occupy: before server-change detection and synckey
     * generation for the collection. A WBXML keep-alive token is flushed
     * after every imported command so clients with hard read timeouts keep
     * receiving response body bytes during large up-sync batches.
     *
     * Import errors are recorded per command (reply status), never thrown:
     * response bytes are already on the wire, so the request must finish
     * with a valid WBXML envelope.
     *
     * @param array $collection  The collection array, updated in place.
     */
    protected function _runDeferredSyncCommands(array &$collection)
    {
        if (empty($this->_deferredCommands[$collection['id']])) {
            return;
        }
        $deferred = $this->_deferredCommands[$collection['id']];
        unset($this->_deferredCommands[$collection['id']]);

        $importer = $this->_activeSync->getImporter();
        $importer->init($this->_state, $collection['id'], $collection['conflict']);

        $start = microtime(true);
        $count = 0;
        foreach ($deferred['commands'] ?? [] as $command) {
            try {
                $this->_importSyncCommand(
                    $importer,
                    $collection,
                    $command['type'],
                    $command['serverid'],
                    $command['clientid'],
                    $command['appdata']
                );
            } catch (Horde_Exception $e) {
                $this->_logger->err(sprintf(
                    'Deferred import failed for collection %s: %s',
                    $collection['id'],
                    $e->getMessage()
                ));
                if ($command['type'] == Horde_ActiveSync::SYNC_ADD
                    && $command['clientid']) {
                    $collection['clientids'][$command['clientid']] = false;
                } elseif ($command['serverid']) {
                    $collection['importfailures'][$command['serverid']]
                        = self::STATUS_SERVERERROR;
                }
            }
            ++$count;
            $this->_encoder->keepAlive();
        }

        if (!empty($deferred['removes'])) {
            try {
                $this->_importRemoves(
                    $importer,
                    $collection,
                    $deferred['removes'],
                    !empty($deferred['deletesasmoves'])
                );
            } catch (Horde_Exception $e) {
                $this->_logger->err(sprintf(
                    'Deferred remove failed for collection %s: %s',
                    $collection['id'],
                    $e->getMessage()
                ));
            }
            $count += count($deferred['removes']);
            $this->_encoder->keepAlive();
        }
        if (!empty($deferred['instanceid_removes'])) {
            try {
                $this->_importInstanceIdRemoves(
                    $importer,
                    $collection,
                    $deferred['instanceid_removes']
                );
            } catch (Horde_Exception $e) {
                $this->_logger->err(sprintf(
                    'Deferred instance remove failed for collection %s: %s',
                    $collection['id'],
                    $e->getMessage()
                ));
            }
            $count += count($deferred['instanceid_removes']);
            $this->_encoder->keepAlive();
        }

        $this->_logger->info(sprintf(
            'SYNC: imported %d deferred incoming change(s) for collection %s in %.1fs (streaming).',
            $count,
            $collection['id'],
            microtime(true) - $start
        ));
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
            while (($folder_tag = ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERTYPE) ? Horde_ActiveSync::SYNC_FOLDERTYPE
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_SYNCKEY) ? Horde_ActiveSync::SYNC_SYNCKEY
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERID) ? Horde_ActiveSync::SYNC_FOLDERID
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_SUPPORTED) ? Horde_ActiveSync::SYNC_SUPPORTED
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_DELETESASMOVES) ? Horde_ActiveSync::SYNC_DELETESASMOVES
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_GETCHANGES) ? Horde_ActiveSync::SYNC_GETCHANGES
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_WINDOWSIZE) ? Horde_ActiveSync::SYNC_WINDOWSIZE
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_CONVERSATIONMODE) ? Horde_ActiveSync::SYNC_CONVERSATIONMODE
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_OPTIONS) ? Horde_ActiveSync::SYNC_OPTIONS
                   : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_COMMANDS) ? Horde_ActiveSync::SYNC_COMMANDS
                   : -1))))))))))) != -1) {

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
                                $collection['supported'] = [Horde_ActiveSync::ALL_GHOSTED];
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
                                $this->_device->supported = [];
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

            if (isset($collection['filtertype'])
                && !$this->_collections->checkFilterType($collection['id'], $collection['filtertype'])) {
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
            } catch (Horde_ActiveSync_Exception_FolderGone $e) {
                $this->_statusCode = self::STATUS_FOLDERSYNC_REQUIRED;
                $this->_handleError($collection);
                return false;
            } catch (Horde_ActiveSync_Exception_StateGone $e) {
                $this->_statusCode = self::STATUS_NOTFOUND;
                $this->_handleError($collection);
                return false;
            }

            // Deferred (not yet imported) commands count as imported changes
            // here: the flags gate looping sync and the empty-response
            // shortcut, and a request carrying client commands must always
            // produce a full response with replies.
            if (!empty($collection['importedchanges'])
                || !empty($this->_deferredCommands[$collection['id']])) {
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
            $this->_state->loadState(
                [],
                null,
                Horde_ActiveSync::REQUEST_TYPE_SYNC,
                $collection['id']
            );
            $this->_statusCode = self::STATUS_KEYMISM;
            $this->_handleError($collection);
            return false;
        } catch (Horde_ActiveSync_Exception_FolderGone $e) {
            $this->_logger->notice($e->getMessage());
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

        /* When streaming, queue ADD/MODIFY imports and REMOVE batches for
         * execution during response output (_runDeferredSyncCommands()), so
         * the potentially slow backend writes happen while response bytes
         * are already flowing to the client. */
        $deferring = $this->_streaming && !empty($collection['synckey']);
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
            if (($commandType == Horde_ActiveSync::SYNC_MODIFY
                 || $commandType == Horde_ActiveSync::SYNC_REMOVE
                 || $commandType == Horde_ActiveSync::SYNC_FETCH)
                && $this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_SERVERENTRYID)) {

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
            if ($this->_activeSync->device->version > Horde_ActiveSync::VERSION_TWELVEONE
                && $commandType == Horde_ActiveSync::SYNC_ADD
                && $this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERTYPE)) {

                $collection['class'] = $this->_decoder->getElementContent();
                if (!$this->_decoder->getElementEndTag()) {
                    $this->_statusCode = self::STATUS_PROTERROR;
                    $this->_handleGlobalSyncError();
                    $this->_logger->err('Parsing Error - expecting </SYNC_FOLDERTYPE>');
                    return false;
                }
            }

            // Only sent during SYNC_ADD
            if ($commandType == Horde_ActiveSync::SYNC_ADD
                && $this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_CLIENTENTRYID)) {
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
            if (($commandType == Horde_ActiveSync::SYNC_ADD
                || $commandType == Horde_ActiveSync::SYNC_MODIFY)
                && ($el = $this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_DATA))) {
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
                            if (!empty($instanceid)
                                && $commandType == Horde_ActiveSync::SYNC_MODIFY) {
                                try {
                                    $appdata->instanceid = new Horde_Date($instanceid, 'UTC');
                                } catch (Horde_Date_Exception $e) {
                                    $this->_logger->err(sprintf(
                                        'Invalid calendar InstanceId %s.',
                                        $instanceid
                                    ));
                                    $appdata->instanceid = $instanceid;
                                }
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
                    case Horde_ActiveSync::SYNC_ADD:
                        if (isset($appdata)) {
                            if ($deferring) {
                                $this->_deferredCommands[$collection['id']]['commands'][] = [
                                    'type' => $commandType,
                                    'serverid' => $serverid,
                                    'clientid' => $clientid,
                                    'appdata' => $appdata,
                                ];
                            } else {
                                $this->_importSyncCommand(
                                    $importer,
                                    $collection,
                                    $commandType,
                                    $serverid,
                                    $clientid,
                                    $appdata
                                );
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

        if ($deferring) {
            // Hand REMOVE batches to the deferred runner as well.
            if (!empty($collection['removes'])) {
                $this->_deferredCommands[$collection['id']]['removes']
                    = $collection['removes'];
                $this->_deferredCommands[$collection['id']]['deletesasmoves']
                    = !empty($collection['deletesasmoves']);
                unset($collection['removes']);
            }
            if (!empty($collection['instanceid_removes'])) {
                $this->_deferredCommands[$collection['id']]['instanceid_removes']
                    = $collection['instanceid_removes'];
                unset($collection['instanceid_removes']);
            }
            $this->_logger->info(sprintf(
                'Queued %d incoming changes for deferred import (streaming).',
                $nchanges
            ));
        } else {
            // Do all the SYNC_REMOVE requests at once
            if (!empty($collection['removes'])
                && !empty($collection['synckey'])) {
                $this->_importRemoves(
                    $importer,
                    $collection,
                    $collection['removes'],
                    !empty($collection['deletesasmoves'])
                );
                unset($collection['removes']);
            }
            // EAS 16.0 instance deletions.
            if (!empty($collection['instanceid_removes'])
                && !empty($collection['synckey'])) {
                $this->_importInstanceIdRemoves(
                    $importer,
                    $collection,
                    $collection['instanceid_removes']
                );
                unset($collection['instanceid_removes']);
            }

            $this->_logger->info(sprintf('Processed %d incoming changes', $nchanges));
        }

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
        $options = [];
        $haveElement = false;

        // These can be sent in any order.
        while (1) {
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
                        $depth = $this->_decoder->isEmptyElement($e)
                            ? $depth
                            : $depth + 1;
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
     * Apply the FOLDERSYNC_REQUIRED loop guard if needed.
     *
     * @param integer $status  The status code about to be sent.
     *
     * @return integer
     */
    protected function _resolveFolderSyncRequiredStatus($status)
    {
        if ($status !== self::STATUS_FOLDERSYNC_REQUIRED) {
            return $status;
        }

        return $this->_collections->folderSyncRequiredStatus(
            self::STATUS_FOLDERSYNC_REQUIRED,
            self::STATUS_KEYMISM
        );
    }

    /**
     * Helper for sending error status results.
     *
     * @param boolean $limit  Send the SYNC_LIMIT error if true.
     */
    protected function _handleGlobalSyncError($limit = false)
    {
        $this->_statusCode = $this->_resolveFolderSyncRequiredStatus($this->_statusCode);

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
     *
     * @see MS-ASCMD 2.2.2.18 Sync command response structure
     * @see MS-ASCMD 2.2.3.29.4 Class element in EAS > 12.1 sent via OPTIONS
     * @see Line 643-648 for similar CollectionId requirement handling
     * @see Line 706-711 for class retrieval pattern
     * @see Collections.php:1063-1065 for FolderGone exception pattern
     */
    protected function _handleError(array $collection)
    {
        $this->_encoder->startWBXML();
        $this->_encoder->startTag(Horde_ActiveSync::SYNC_SYNCHRONIZE);

        $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERS);

        // Generate new synckey for initial sync, state reset, or when synckey missing/invalid
        if ($this->_statusCode == self::STATUS_KEYMISM
            || !empty($collection['importedchanges'])
            || !empty($collection['getchanges'])
            || empty($collection['synckey'])
            || $collection['synckey'] == '0') {

            $collection['newsynckey'] = Horde_ActiveSync_State_Base::getNewSyncKey(($this->_statusCode == self::STATUS_KEYMISM) ? 0 : ($collection['synckey'] ?? 0));
            if (!empty($collection['synckey']) && $collection['synckey'] != '0') {
                $this->_state->removeState(['synckey' => $collection['synckey']]);
            }
        }

        // Collection-specific error response requires CollectionId per MS-ASCMD; fallback to global error
        if (empty($collection['id'])) {
            if (isset($this->_logger)) {
                $this->_logger->err('Cannot send SYNC error response: collection has no ID. This indicates a very early protocol error.');
                $this->_logger->debug(sprintf(
                    'Collection state at error: synckey=%s, class=%s, available_keys=[%s]',
                    $collection['synckey'] ?? 'NONE',
                    $collection['class'] ?? 'NONE',
                    implode(',', array_keys($collection))
                ));
            }
            $this->_statusCode = self::STATUS_PROTERROR;
            $this->_handleGlobalSyncError();
            return;
        }

        // Collection class may be missing in EAS > 12.1; retrieve from cache or signal folder resync
        if (empty($collection['class'])) {
            $collection['class'] = $this->_collections->getCollectionClass($collection['id']);
            if (!$collection['class']) {
                // Cannot determine class - collection may be deleted/invalid, signal FOLDERSYNC required
                if (isset($this->_logger)) {
                    $this->_logger->err(sprintf(
                        'Cannot determine collection class for id=%s - collection may be deleted or cache invalid',
                        $collection['id']
                    ));
                }
                $this->_statusCode = self::STATUS_FOLDERSYNC_REQUIRED;
                // Use fallback class value to prevent encoder crash while sending FOLDERSYNC_REQUIRED status
                $collection['class'] = 'Email';
            } elseif (isset($this->_logger)) {
                $this->_logger->debug(sprintf(
                    'Retrieved missing collection class from cache: %s for id=%s',
                    $collection['class'],
                    $collection['id']
                ));
            }
        }

        // Log after status/class resolution so diagnostics match the WBXML response.
        if (isset($this->_logger)) {
            $this->_logger->err(sprintf(
                'SYNC ERROR: status=%s, collection_id=%s, class=%s, synckey=%s, keys=[%s]',
                $this->_statusCode ?? 'UNKNOWN',
                $collection['id'] ?? 'MISSING',
                $collection['class'] ?? 'MISSING',
                $collection['synckey'] ?? 'MISSING',
                implode(',', array_keys($collection))
            ));
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
        $this->_encoder->content($this->_resolveFolderSyncRequiredStatus($this->_statusCode));
        $this->_encoder->endTag();

        $this->_encoder->endTag(); // Horde_ActiveSync::SYNC_FOLDER
        $this->_encoder->endTag();
        $this->_encoder->endTag();
    }

}
