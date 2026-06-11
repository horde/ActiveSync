<?php

/**
 * Horde_ActiveSync_Request_ItemOperations::
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
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * ActiveSync Handler for ItemOperations requests
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 * @internal
 */
class Horde_ActiveSync_Request_ItemOperations extends Horde_ActiveSync_Request_SyncBase
{
    public const ITEMOPERATIONS_ITEMOPERATIONS     = 'ItemOperations:ItemOperations';
    public const ITEMOPERATIONS_FETCH              = 'ItemOperations:Fetch';
    public const ITEMOPERATIONS_STORE              = 'ItemOperations:Store';
    public const ITEMOPERATIONS_OPTIONS            = 'ItemOperations:Options';
    public const ITEMOPERATIONS_RANGE              = 'ItemOperations:Range';
    public const ITEMOPERATIONS_TOTAL              = 'ItemOperations:Total';
    public const ITEMOPERATIONS_PROPERTIES         = 'ItemOperations:Properties';
    public const ITEMOPERATIONS_DATA               = 'ItemOperations:Data';
    public const ITEMOPERATIONS_STATUS             = 'ItemOperations:Status';
    public const ITEMOPERATIONS_RESPONSE           = 'ItemOperations:Response';
    public const ITEMOPERATIONS_VERSION            = 'ItemOperations:Version';
    public const ITEMOPERATIONS_SCHEMA             = 'ItemOperations:Schema';
    public const ITEMOPERATIONS_PART               = 'ItemOperations:Part';
    public const ITEMOPERATIONS_EMPTYFOLDERCONTENT = 'ItemOperations:EmptyFolderContent';
    public const ITEMOPERATIONS_DELETESUBFOLDERS   = 'ItemOperations:DeleteSubFolders';
    public const ITEMOPERATIONS_USERNAME           = 'ItemOperations:UserName';
    public const ITEMOPERATIONS_PASSWORD           = 'ItemOperations:Password';

    // 14.0
    public const ITEMOPERATIONS_MOVE               = 'ItemOperations:Move';
    public const ITEMOPERATIONS_DSTFLDID           = 'ItemOperations:DstFldId';
    public const ITEMOPERATIONS_CONVERSATIONID     = 'ItemOperations:ConversationId';
    public const ITEMOPERATIONS_MOVEALWAYS         = 'ItemOperations:MoveAlways';

    /* Status */
    public const STATUS_SUCCESS         = 1;
    public const STATUS_PROTERR         = 2;
    public const STATUS_SERVERERR       = 3;
    // 4 - 13 are Document library related.
    public const STATUS_OBJECTNOTFOUND  = 6;
    public const STATUS_ATTINVALID      = 15;
    public const STATUS_POLICYERR       = 16;
    public const STATUS_PARTSUCCESS     = 17;
    public const STATUS_CREDENTIALS     = 18;
    public const STATUS_PROTERR_OPTIONS = 155;
    public const STATUS_NOT_SUPPORTED   = 156;


    /**
     * Handle the request.
     *
     * @return string  The Content-Type of the attachment data.
     */
    protected function _handle()
    {
        $this->_logger->meta('Handling ITEMOPERATIONS command.');
        $this->_statusCode = self::STATUS_SUCCESS;

        if (!$this->_decoder->getElementStartTag(self::ITEMOPERATIONS_ITEMOPERATIONS)) {
            throw new Horde_ActiveSync_Exception('Protocol Error');
        }

        // The current itemoperation task
        $thisio = [];
        $mimesupport = 0;
        while (($reqtype = ($this->_decoder->getElementStartTag(self::ITEMOPERATIONS_FETCH) ? self::ITEMOPERATIONS_FETCH
                  : ($this->_decoder->getElementStartTag(self::ITEMOPERATIONS_EMPTYFOLDERCONTENT) ? self::ITEMOPERATIONS_EMPTYFOLDERCONTENT : -1))) != -1) {

            if ($reqtype == self::ITEMOPERATIONS_FETCH) {
                $thisio['type'] = 'fetch';

                while (($reqtag = ($this->_decoder->getElementStartTag(self::ITEMOPERATIONS_STORE) ? self::ITEMOPERATIONS_STORE
                              : ($this->_decoder->getElementStartTag(self::ITEMOPERATIONS_OPTIONS) ? self::ITEMOPERATIONS_OPTIONS
                              : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_SERVERENTRYID) ? Horde_ActiveSync::SYNC_SERVERENTRYID
                              : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERID) ? Horde_ActiveSync::SYNC_FOLDERID
                              : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_LINKID) ? Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_LINKID
                              : ($this->_decoder->getElementStartTag(Horde_ActiveSync::AIRSYNCBASE_FILEREFERENCE) ? Horde_ActiveSync::AIRSYNCBASE_FILEREFERENCE
                              : ($this->_decoder->getElementStartTag(Horde_ActiveSync_Request_Search::SEARCH_LONGID) ? Horde_ActiveSync_Request_Search::SEARCH_LONGID
                              : -1)))))))) != -1) {

                    if ($reqtag == self::ITEMOPERATIONS_OPTIONS) {
                        while (($thisoption = ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_MIMESUPPORT) ? Horde_ActiveSync::SYNC_MIMESUPPORT
                               : ($this->_decoder->getElementStartTag(Horde_ActiveSync::AIRSYNCBASE_BODYPREFERENCE) ? Horde_ActiveSync::AIRSYNCBASE_BODYPREFERENCE
                               : ($this->_decoder->getElementStartTag(Horde_ActiveSync::AIRSYNCBASE_BODYPARTPREFERENCE) ? Horde_ActiveSync::AIRSYNCBASE_BODYPARTPREFERENCE
                               : ($this->_decoder->getElementStartTag(self::ITEMOPERATIONS_SCHEMA) ? self::ITEMOPERATIONS_SCHEMA
                               : ($this->_decoder->getElementStartTag(self::ITEMOPERATIONS_RANGE) ? self::ITEMOPERATIONS_RANGE
                               : ($this->_decoder->getElementStartTag(self::ITEMOPERATIONS_USERNAME) ? self::ITEMOPERATIONS_USERNAME
                               : ($this->_decoder->getElementStartTag(self::ITEMOPERATIONS_PASSWORD) ? self::ITEMOPERATIONS_PASSWORD
                               : ($this->_decoder->getElementStartTag(Horde_ActiveSync::RM_SUPPORT) ? Horde_ActiveSync::RM_SUPPORT
                               : -1))))))))) != -1) {

                            switch ($thisoption) {
                                case Horde_ActiveSync::SYNC_MIMESUPPORT:
                                    $mimesupport = $this->_decoder->getElementContent();
                                    $this->_decoder->getElementEndTag();
                                    break;
                                case Horde_ActiveSync::AIRSYNCBASE_BODYPREFERENCE:
                                    $this->_bodyPrefs($thisio);
                                    break;
                                case Horde_ActiveSync::AIRSYNCBASE_BODYPARTPREFERENCE:
                                    $this->_bodyPartPrefs($thisio);
                                    break;
                                case Horde_ActiveSync::RM_SUPPORT:
                                    $this->_rightsManagement($thisio);
                                    break;
                                case self::ITEMOPERATIONS_PASSWORD:
                                    $thisio['password'] = $this->_decoder->getElementContent();
                                    break;
                                case self::ITEMOPERATIONS_USERNAME:
                                    $thisio['username'] = $this->_decoder->getElementContent();
                                    break;

                                case self::ITEMOPERATIONS_RANGE:
                                    $thisio['range'] = $this->_decoder->getElementContent();
                                    break;

                                case self::ITEMOPERATIONS_SCHEMA:
                                    while (1) {
                                        $el = $this->_decoder->getElement();
                                        $e = $this->_decoder->peek();
                                        if ($e[Horde_ActiveSync_Wbxml::EN_TYPE] == Horde_ActiveSync_Wbxml::EN_TYPE_ENDTAG) {
                                            $this->_decoder->getElementEndTag();
                                            break;
                                        }
                                    }
                            }
                        }
                    } elseif ($reqtag == self::ITEMOPERATIONS_STORE) {
                        $thisio['store'] = $this->_decoder->getElementContent();
                    } elseif ($reqtag == Horde_ActiveSync_Request_Search::SEARCH_LONGID) {
                        $thisio['searchlongid'] = $this->_decoder->getElementContent();
                    } elseif ($reqtag == Horde_ActiveSync::AIRSYNCBASE_FILEREFERENCE) {
                        $thisio['airsyncbasefilereference'] = $this->_decoder->getElementContent();
                    } elseif ($reqtag == Horde_ActiveSync::SYNC_SERVERENTRYID) {
                        $thisio['serverentryid'] = $this->_decoder->getElementContent();
                    } elseif ($reqtag == Horde_ActiveSync::SYNC_FOLDERID) {
                        $thisio['folderid'] = $this->_decoder->getElementContent();
                    } elseif ($reqtag == Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_LINKID) {
                        $thisio['documentlibrarylinkid'] = $this->_decoder->getElementContent();
                    }
                    $e = $this->_decoder->peek();
                    if ($e[Horde_ActiveSync_Wbxml::EN_TYPE] == Horde_ActiveSync_Wbxml::EN_TYPE_ENDTAG) {
                        $this->_decoder->getElementEndTag();
                    }
                }
                $itemoperations[] = $thisio;
                $this->_decoder->getElementEndTag(); // end SYNC_ITEMOPERATIONS_FETCH
            } elseif ($reqtype == self::ITEMOPERATIONS_EMPTYFOLDERCONTENT) {
                $thisio['type'] = 'empty';
                while (($tag = ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERID) ? Horde_ActiveSync::SYNC_FOLDERID
                          : ($this->_decoder->getElementStartTag(self::ITEMOPERATIONS_OPTIONS) ? self::ITEMOPERATIONS_OPTIONS : -1))) != -1) {

                    if ($tag == Horde_ActiveSync::SYNC_FOLDERID) {
                        $thisio['folderid'] = $this->_decoder->getElementContent();
                    } elseif ($tag == self::ITEMOPERATIONS_OPTIONS) {
                        $this->_decoder->getElementStartTag(self::ITEMOPERATIONS_DELETESUBFOLDERS);
                        $thisio['delete_subfolders'] = $this->_decoder->getElementContent();
                        $this->_decoder->getElementEndTag();
                    }
                    $this->_decoder->getElementEndTag();
                }
                $this->_decoder->getElementEndTag(); // SYNC_ITEMSOPERATIONS_EMPTYFOLDERCONTENT
                $itemoperations[] = $thisio;
            }
        }
        $this->_decoder->getElementEndTag(); // end SYNC_ITEMOPERATIONS_ITEMOPERATIONS
        $this->_encoder->startWBXML($this->_activeSync->multipart);
        $this->_encoder->startTag(self::ITEMOPERATIONS_ITEMOPERATIONS);

        $this->_encoder->startTag(self::ITEMOPERATIONS_STATUS);
        $this->_encoder->content(self::STATUS_SUCCESS);
        $this->_encoder->endTag();

        $this->_encoder->startTag(self::ITEMOPERATIONS_RESPONSE);
        $collections = $this->_activeSync->getCollectionsObject();
        foreach ($itemoperations as $value) {
            switch ($value['type']) {
                case 'fetch':
                    switch (Horde_String::lower($value['store'])) {
                        case 'mailbox':
                            // Yes, even though this is a "mailbox" store, this is
                            // how EAS identifies calendar attachments too since
                            // they are not documentLibrary items. The backend
                            // needs to be able to identify where to get the
                            // item from based solely on the filereference.
                            $this->_statusCode = self::STATUS_SUCCESS;
                            $msg = null;
                            $this->_encoder->startTag(self::ITEMOPERATIONS_FETCH);
                            if (isset($value['airsyncbasefilereference'])) {
                                // filereference is already in the backend serverid format
                                // since it is taken from the AIRSYNCBASE_FILEREFERENCE
                                try {
                                    $msg = $this->_driver->itemOperationsGetAttachmentData($value['airsyncbasefilereference']);
                                } catch (Horde_ActiveSync_Exception $e) {
                                    $this->_statusCode = self::STATUS_ATTINVALID;
                                }
                                if (!$this->_encoder->multipart) {
                                    $msg->total = $this->_getDataSize($msg->data);
                                    $msg->range = '0-' . ($msg->total - 1);
                                }
                                $this->_outputStatus();
                                $this->_encoder->startTag(Horde_ActiveSync::AIRSYNCBASE_FILEREFERENCE);
                                $this->_encoder->content($value['airsyncbasefilereference']);
                                $this->_encoder->endTag();
                            } elseif (isset($value['searchlongid'])) {
                                $msg = $this->_fetchMailboxMessage(
                                    $value,
                                    $collections,
                                    $mimesupport,
                                    'searchlongid'
                                );
                                $this->_outputStatus();
                                if ($this->_statusCode == self::STATUS_SUCCESS && $msg) {
                                    $this->_encoder->startTag(Horde_ActiveSync_Request_Search::SEARCH_LONGID);
                                    $this->_encoder->content($value['searchlongid']);
                                    $this->_encoder->endTag();
                                    $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERTYPE);
                                    $this->_encoder->content('Email');
                                    $this->_encoder->endTag();
                                }
                            } else {
                                if (isset($value['folderid']) && isset($value['serverentryid'])) {
                                    $folderType = $this->_getItemOperationsFolderType(
                                        $collections,
                                        $value['folderid']
                                    );
                                    $msg = $this->_fetchMailboxMessage(
                                        $value,
                                        $collections,
                                        $mimesupport,
                                        'folder'
                                    );
                                    $this->_outputStatus();
                                    if ($this->_statusCode == self::STATUS_SUCCESS && $msg) {
                                        $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERID);
                                        $this->_encoder->content($value['folderid']);
                                        $this->_encoder->endTag();

                                        $this->_encoder->startTag(Horde_ActiveSync::SYNC_SERVERENTRYID);
                                        $this->_encoder->content($value['serverentryid']);
                                        $this->_encoder->endTag();

                                        $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERTYPE);
                                        $this->_encoder->content($folderType);
                                        $this->_encoder->endTag();
                                    }
                                } else {
                                    $this->_statusCode = self::STATUS_PROTERR;
                                    $this->_outputStatus();
                                }
                            }
                            if ($this->_statusCode == self::STATUS_SUCCESS && $msg) {
                                $this->_encoder->startTag(self::ITEMOPERATIONS_PROPERTIES);
                                $msg->encodeStream($this->_encoder);
                                $this->_encoder->endTag();
                            }
                            $this->_encoder->endTag();
                            break;
                        case 'documentlibrary':
                            $this->_encoder->startTag(self::ITEMOPERATIONS_FETCH);
                            try {
                                $u = $this->_driver->itemOperationsGetDocumentLibraryLink($value['documentlibrarylinkid'], []);
                                $doc = Horde_ActiveSync::messageFactory('Document');
                                $doc->range = '0-' . ($u['content-length'] - 1);
                                $doc->total = $u['content-length'];
                                $doc->data = $u['data']->stream;
                                $doc->version = $u['modified'];
                            } catch (Horde_ActiveSync_Exception $e) {
                                $this->_status = self::STATUS_NOT_SUPPORTED;
                            }
                            $this->_outputStatus();

                            $this->_encoder->startTag(Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_LINKID);
                            $this->_encoder->content($u['linkid']);
                            $this->_encoder->endTag();

                            $this->_encoder->startTag(self::ITEMOPERATIONS_PROPERTIES);
                            $doc->encodeStream($this->_encoder);
                            $this->_encoder->endTag();

                            $this->_encoder->endTag();
                            break;

                        default:
                            $this->_logger->err(
                                sprintf(
                                    '%s not supported by HANDLEITEMOPERATIONS.',
                                    $value['type']
                                )
                            );
                            break;
                    }
                    break;
                case 'empty':
                    // @todo remove check for H6.
                    if (method_exists($this->_driver, 'itemOperationsEmptyFolder')) {
                        $map = array_flip($this->_state->getFolderUidToBackendIdMap());
                        $value['folderid'] = $map[$value['folderid']];
                        $this->_logger->meta(sprintf(
                            'Handling EMPTYFOLDERCONTENT for collection %s.',
                            $value['folderid']
                        ));
                        try {
                            $this->_driver->itemOperationsEmptyFolder($value);
                        } catch (Horde_ActiveSync_Exception $e) {
                            $this->_status = self::STATUS_NOT_SUPPORTED;
                        }
                    } else {
                        $this->_logger->err('EMPTYFOLDERCONTENT not supported by driver.');
                    }
                    break;
                default:
                    $this->_logger->err(
                        sprintf(
                            '%s not supported by HANDLEITEMOPERATIONS.',
                            $value['type']
                        )
                    );
                    break;
            }
        }
        $this->_encoder->endTag(); //end SYNC_ITEMOPERATIONS_RESPONSE
        $this->_encoder->endTag(); //end SYNC_ITEMOPERATIONS_ITEMOPERATIONS

        // @TODO This is for BC, remove in H6.
        return $this->_encoder->multipart
            ? 'application/vnd.ms-sync.multipart'
            : 'application/vnd.ms-sync.wbxml';
    }

    /**
     * Folder type for an ItemOperations fetch (MS-ASCAL 3.1.4.3).
     *
     * @author Torben Dannhauer <torben@dannhauer.de>
     *
     * @param Horde_ActiveSync_Collections $collections
     * @param string $folderid  Client folder/collection id.
     *
     * @return string  A Horde_ActiveSync::CLASS_* value.
     */
    protected function _getItemOperationsFolderType(
        Horde_ActiveSync_Collections $collections,
        $folderid
    ) {
        $class = $collections->getCollectionClass($folderid);
        if ($class && $class !== 'RI') {
            return $class;
        }

        return Horde_ActiveSync::CLASS_EMAIL;
    }

    /**
     * Fetch a mailbox message for ItemOperations.
     *
     * @author Torben Dannhauer <torben@dannhauer.de>
     *
     * @param array  $value        Parsed ItemOperations fetch request.
     * @param Horde_ActiveSync_Collections $collections  Folder cache.
     * @param integer $mimesupport MIME support flag.
     * @param string $mode         Either "searchlongid" or "folder".
     *
     * @return Horde_ActiveSync_Message_Base|null  Message or null on failure.
     */
    protected function _fetchMailboxMessage(
        array $value,
        Horde_ActiveSync_Collections $collections,
        $mimesupport,
        string $mode
    ) {
        $opts = [
            'bodyprefs' => $value['bodyprefs'] ?? [],
            'mimesupport' => $mimesupport,
        ];

        try {
            if ($mode === 'searchlongid') {
                return $this->_driver->itemOperationsFetchMailbox(
                    $value['searchlongid'],
                    $opts['bodyprefs'],
                    $mimesupport
                );
            }

            $longid = $this->_resolveFetchLongId($value);
            if ($longid) {
                return $this->_driver->itemOperationsFetchMailbox(
                    $longid,
                    $opts['bodyprefs'],
                    $mimesupport
                );
            }

            $folderid = $value['folderid'];
            if ($folderid !== '' && $folderid[0] === 'M') {
                $this->_logger->info(sprintf(
                    'ItemOperations fetch: message UID %s not found for virtual folder %s.',
                    $value['serverentryid'],
                    $folderid
                ));
                $this->_statusCode = self::STATUS_OBJECTNOTFOUND;
                return null;
            }

            $mailbox = $collections->getBackendIdForFolderUid($folderid);

            return $this->_driver->fetch(
                $mailbox,
                $value['serverentryid'],
                $opts
            );
        } catch (Horde_ActiveSync_Exception_FolderGone $e) {
            $this->_logger->err(sprintf(
                'ItemOperations fetch: folder %s not in cache for UID %s.',
                $value['folderid'] ?? '',
                $value['serverentryid'] ?? ''
            ));
            $this->_statusCode = self::STATUS_SERVERERR;
            return null;
        } catch (Horde_Exception_NotFound $e) {
            $this->_logger->info(sprintf(
                'ItemOperations fetch: message not found (folder=%s id=%s).',
                $value['folderid'] ?? '',
                $value['serverentryid'] ?? ($value['searchlongid'] ?? '')
            ));
            $this->_statusCode = self::STATUS_OBJECTNOTFOUND;
            return null;
        }
    }

    /**
     * Helper to send the status output.
     */
    protected function _outputStatus()
    {
        $this->_encoder->startTag(self::ITEMOPERATIONS_STATUS);
        $this->_encoder->content($this->_statusCode);
        $this->_encoder->endTag();
    }

    /**
     * Return the size of the specified data.
     *
     * @param string|stream  The data to obtain the size of.
     *
     * @return integer  The size of the data.
     */
    /**
     * Resolve mailbox:uid for ItemOperations when the client uses a virtual
     * folder id from unified Find search (e.g. iOS All Mailboxes "M&lt;uid&gt;").
     *
     * @author Torben Dannhauer <torben@dannhauer.de>
     *
     * @param array $value  Parsed ItemOperations fetch request.
     *
     * @return string|null  Long id suitable for itemOperationsFetchMailbox().
     */
    protected function _resolveFetchLongId(array $value): ?string
    {
        if (!isset($value['serverentryid'])
            || !method_exists($this->_driver, 'resolveLongIdForUid')) {
            return null;
        }

        $folderid = $value['folderid'] ?? '';
        $needsResolve = ($folderid !== '' && $folderid[0] === 'M');

        if (!$needsResolve) {
            $collections = $this->_activeSync->getCollectionsObject();
            try {
                $collections->getBackendIdForFolderUid($folderid);
                return null;
            } catch (Horde_ActiveSync_Exception_FolderGone $e) {
                $needsResolve = true;
            }
        }

        if (!$needsResolve) {
            return null;
        }

        $uid = (int) $value['serverentryid'];

        return $uid > 0 ? $this->_driver->resolveLongIdForUid($uid) : null;
    }

    protected function _getDataSize($data)
    {
        if (is_resource($data)) {
            rewind($data);
            fseek($data, 0, SEEK_END);
            return ftell($data);
        } else {
            return strlen($data);
        }
    }

    protected function _handleError(array $data, $error)
    {
        $this->_decoder->getElementEndTag(); // end SYNC_ITEMOPERATIONS_ITEMOPERATIONS
        $this->_encoder->startWBXML($this->_activeSync->multipart);
        $this->_encoder->startTag(self::ITEMOPERATIONS_ITEMOPERATIONS);
        $this->_outputStatus();
        $this->_encoder->endTag();
    }

}
