<?php

/**
 * Horde_ActiveSync_Request_Search::
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
 * Handle Search requests.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 * @internal
 */
class Horde_ActiveSync_Request_Search extends Horde_ActiveSync_Request_SyncBase
{
    /** Search code page **/
    public const SEARCH_SEARCH              = 'Search:Search';
    public const SEARCH_STORE               = 'Search:Store';
    public const SEARCH_NAME                = 'Search:Name';
    public const SEARCH_QUERY               = 'Search:Query';
    public const SEARCH_OPTIONS             = 'Search:Options';
    public const SEARCH_RANGE               = 'Search:Range';
    public const SEARCH_STATUS              = 'Search:Status';
    public const SEARCH_RESPONSE            = 'Search:Response';
    public const SEARCH_RESULT              = 'Search:Result';
    public const SEARCH_PROPERTIES          = 'Search:Properties';
    public const SEARCH_TOTAL               = 'Search:Total';
    public const SEARCH_EQUALTO             = 'Search:EqualTo';
    public const SEARCH_VALUE               = 'Search:Value';
    public const SEARCH_AND                 = 'Search:And';
    public const SEARCH_OR                  = 'Search:Or';
    public const SEARCH_FREETEXT            = 'Search:FreeText';
    public const SEARCH_DEEPTRAVERSAL       = 'Search:DeepTraversal';
    public const SEARCH_LONGID              = 'Search:LongId';
    public const SEARCH_REBUILDRESULTS      = 'Search:RebuildResults';
    public const SEARCH_LESSTHAN            = 'Search:LessThan';
    public const SEARCH_GREATERTHAN         = 'Search:GreaterThan';
    public const SEARCH_SCHEMA              = 'Search:Schema';
    public const SEARCH_SUPPORTED           = 'Search:Supported';
    public const SEARCH_USERNAME            = 'Search:UserName';
    public const SEARCH_PASSWORD            = 'Search:Password';

    // 14
    public const SEARCH_CONVERSATIONID      = 'Search:ConversationId';

    // 14.1
    public const SEARCH_PICTURE             = 'Search:Picture';
    public const SEARCH_MAXSIZE             = 'Search:MaxSize';
    public const SEARCH_MAXPICTURES         = 'Search:MaxPictures';

    /** Search Status **/
    public const SEARCH_STATUS_SUCCESS      = 1;
    public const SEARCH_STATUS_ERROR        = 3;

    /** Compat **/
    public const STATUS_PROTERROR           = 3;

    /** Store Status **/
    public const STORE_STATUS_SUCCESS       = 1;
    public const STORE_STATUS_PROTERR       = 2;
    public const STORE_STATUS_SERVERERR     = 3;
    public const STORE_STATUS_BADLINK       = 4;
    public const STORE_STATUS_NOTFOUND      = 6;
    public const STORE_STATUS_CONNECTIONERR = 7;
    public const STORE_STATUS_COMPLEX       = 8;
    public const STORE_STATUS_FOLDERSYNC    = 11;
    public const STORE_STATUS_RANGEERR      = 12;

    /**
     * @var Horde_ActiveSync_Collections
     */
    protected $_collections;

    /**
     * Handle request
     *
     * @return boolean
     */
    protected function _handle()
    {
        // See https://learn.microsoft.com/en-us/openspecs/exchange_server_protocols/ms-ascmd/8211179b-14f3-44ab-9de6-b69ca2a48c4e

        $this->_logger->meta('Handling SEARCH command.');
        $search_status = self::SEARCH_STATUS_SUCCESS;
        $store_status = self::STORE_STATUS_SUCCESS;

        $this->_collections = $this->_activeSync->getCollectionsObject();

        if (!$this->_decoder->getElementStartTag(self::SEARCH_SEARCH)
            || !$this->_decoder->getElementStartTag(self::SEARCH_STORE)
            || !$this->_decoder->getElementStartTag(self::SEARCH_NAME)) {

            throw new Horde_ActiveSync_Exception_InvalidRequest('Missing required SEARCH|STORE|NAME');
        }

        $search_name = $this->_decoder->getElementContent();
        if (!$this->_decoder->getElementEndTag()) {
            return false;
        }

        if (!$this->_decoder->getElementStartTag(self::SEARCH_QUERY)) {
            throw new Horde_ActiveSync_Exception_InvalidRequest('Missing required SEARCH_QUERY.');
        }

        $options = [];
        $maxResults = 100;

        switch (Horde_String::lower($search_name)) {
            case 'documentlibrary':
                $maxResults = 1000;
                // fall through
                // no break
            case 'mailbox':
                $query = $this->_parseQuery();
                if (!$query) {
                    $search_status = self::SEARCH_STATUS_ERROR;
                    $store_status = self::STORE_STATUS_PROTERR;
                }
                break;
            case 'gal':
                $query = (array) $this->_decoder->getElementContent();
                break;
            default:
                $query = null;
                $search_status = self::SEARCH_STATUS_ERROR;
                $store_status = self::STORE_STATUS_PROTERR;
        }

        if (!$this->_decoder->getElementEndTag()) {
            return false;
        }

        $range = null;
        $rebuildResults = false;
        $deepTraversal = false;

        $mime = Horde_ActiveSync::MIME_SUPPORT_NONE;
        $searchbodypreference = [];
        if ($this->_decoder->getElementStartTag(self::SEARCH_OPTIONS)) {
            while (1) {
                if ($this->_decoder->getElementStartTag(self::SEARCH_RANGE)) {
                    //FIXME: The result of including more than one Range element in a Search command request
                    //       is undefined. The server MAY return a protocol status error in response to such a command request.
                    $range = $this->_decoder->getElementContent();
                    if (!$this->_decoder->getElementEndTag()) {
                        return false;
                    }
                }
                if ($this->_decoder->getElementStartTag(self::SEARCH_DEEPTRAVERSAL)) {
                    if (!($deepTraversal = $this->_decoder->getElementContent())) {
                        $deepTraversal = true;
                    } elseif (!$this->_decoder->getElementEndTag()) {
                        return false;
                    }
                }
                if ($this->_decoder->getElementStartTag(self::SEARCH_REBUILDRESULTS)) {
                    if (!($rebuildResults = $this->_decoder->getElementContent())) {
                        $rebuildResults = true;
                    } elseif (!$this->_decoder->getElementEndTag()) {
                        return false;
                    }
                }
                if ($this->_decoder->getElementStartTag(self::SEARCH_USERNAME)) {
                    if (!($options['username'] = $this->_decoder->getElementContent())) {
                        return false;
                    } elseif (!$this->_decoder->getElementEndTag()) {
                        return false;
                    }
                }
                if ($this->_decoder->getElementStartTag(self::SEARCH_PASSWORD)) {
                    if (!($options['password'] = $this->_decoder->getElementContent())) {
                        return false;
                    } elseif (!$this->_decoder->getElementEndTag()) {
                        return false;
                    }
                }
                if ($this->_decoder->getElementStartTag(self::SEARCH_SCHEMA)) {
                    if (!($options['schema'] = $this->_decoder->getElementContent())) {
                        $options['schema'] = true;
                    } elseif (!$this->_decoder->getElementEndTag()) {
                        return false;
                    }
                }
                // 14.1 Only
                if ($this->_decoder->getElementStartTag(self::SEARCH_PICTURE)) {
                    $options[self::SEARCH_PICTURE] = true;
                    if ($this->_decoder->getElementStartTag(self::SEARCH_MAXSIZE)) {
                        $options[self::SEARCH_MAXSIZE] = $this->_decoder->getElementContent();
                        if (!$this->_decoder->getElementEndTag()) {
                            return false;
                        }
                    }
                    if ($this->_decoder->getElementStartTag(self::SEARCH_MAXPICTURES)) {
                        $options[self::SEARCH_MAXPICTURES] = $this->_decoder->getElementContent();
                        if (!$this->_decoder->getElementEndTag()) {
                            return false;
                        }
                    }
                }

                if ($this->_decoder->getElementStartTag(Horde_ActiveSync::AIRSYNCBASE_BODYPREFERENCE)) {
                    $this->_bodyPrefs($searchbodypreference);
                    $searchbodypreference = empty($searchbodypreference['bodyprefs']) ? [] : $searchbodypreference['bodyprefs'];
                }

                if ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_MIMESUPPORT)) {
                    $this->_mimeSupport($searchbodypreference);
                }

                // EAS 14.1
                if ($this->_device->version >= Horde_ActiveSync::VERSION_FOURTEENONE) {
                    $rm = [];
                    if ($this->_decoder->getElementStartTag(Horde_ActiveSync::RM_SUPPORT)) {
                        $this->_rightsManagement($rm);
                    }
                    if ($this->_decoder->getElementStartTag(Horde_ActiveSync::AIRSYNCBASE_BODYPARTPREFERENCE)) {
                        $this->_bodyPartPrefs($options);
                    }
                }

                $e = $this->_decoder->peek();
                if ($e[Horde_ActiveSync_Wbxml::EN_TYPE] == Horde_ActiveSync_Wbxml::EN_TYPE_ENDTAG) {
                    $this->_decoder->getElementEndTag();
                    break;
                }
            }
        }

        if (!$this->_decoder->getElementEndTag()) { //store
            return false;
        }

        if (!$this->_decoder->getElementEndTag()) { //search
            return false;
        }

        if ($store_status === self::STORE_STATUS_SUCCESS) {
            $rebuildResults = !empty($rebuildResults);
            $deepTraversal =  !empty($deepTraversal);

            $start = 0;
            $limit = $maxResults;
            if ($range !== null) {
                if (preg_match('/^(\d+)-(\d+)$/', $range, $matches)) {
                    $start = (int) $matches[1];
                    $end = (int) $matches[2];
                    if ($end < $start) {
                        $store_status = self::STORE_STATUS_PROTERR;
                    } else {
                        $limit = $end - $start + 1;
                        if ($limit  > $maxResults) {
                            // If the Range element value specified in the request exceeds the default range value,
                            // a Status element (section 2.2.3.177.13) value of 12 is returned to indicate that the
                            // maximum range has been exceeded
                            $store_status = self::STORE_STATUS_RANGEERR;
                        }
                    }
                } else {
                    $store_status = self::STORE_STATUS_PROTERR;
                }
            }
        }

        // In the Search command response, the Total element (section 2.2.3.184.3) indicates an estimate
        // of the total number of entries that matched the Query element (section 2.2.3.142.2) value.
        if ($store_status === self::STORE_STATUS_SUCCESS && $query) {
            // Prepare search parameters
            $params = new Horde_ActiveSync_Search_Params(
                type: $search_name,
                query: $query,
                options: $options,
                start: $start,
                limit: $limit,
                rebuildResults: $rebuildResults,
                deepTraversal: $deepTraversal
            );

            // Get search results from backend
            $results = $this->_driver->getSearchResults($params);

            /* not yet */
            // $store_status = $results->status;
            if ($results->rows === null) {
                $store_status = self::STORE_STATUS_SERVERERR;
            }
        } else {
            $results = null;
        }

        /* Send output */
        $this->_encoder->startWBXML();
        $this->_encoder->startTag(self::SEARCH_SEARCH);

        $this->_encoder->startTag(self::SEARCH_STATUS);
        $this->_encoder->content($search_status);
        $this->_encoder->endTag();

        $this->_encoder->startTag(self::SEARCH_RESPONSE);
        $this->_encoder->startTag(self::SEARCH_STORE);

        $this->_encoder->startTag(self::SEARCH_STATUS);
        $this->_encoder->content($store_status);
        $this->_encoder->endTag();

        if ($results && $results->rows) {
            foreach ($results->rows as $u) {
                switch (Horde_String::lower($search_name)) {
                    case 'documentlibrary':
                        $this->_encoder->startTag(self::SEARCH_RESULT);

                        $doc = Horde_ActiveSync::messageFactory('DocumentLibrary');
                        $doc->linkid = $u['linkid'];
                        $doc->displayname = $u['name'];
                        $doc->isfolder = $u['is_folder'] ? '1' : '0';
                        $doc->creationdate = $u['created'];
                        $doc->lastmodifieddate = $u['modified'];
                        $doc->ishidden = '0';
                        $doc->contentlength = $u['content-length'];
                        if (!empty($u['content-type'])) {
                            $doc->contenttype = $u['content-type'];
                        }

                        $this->_encoder->startTag(self::SEARCH_PROPERTIES);
                        $doc->encodeStream($this->_encoder);
                        $this->_encoder->endTag();

                        $this->_encoder->endTag();
                        break;

                    case 'gal':
                        $this->_encoder->startTag(self::SEARCH_RESULT);
                        $this->_encoder->startTag(self::SEARCH_PROPERTIES);

                        $this->_encoder->startTag(Horde_ActiveSync::GAL_DISPLAYNAME);
                        $this->_encoder->content($u[Horde_ActiveSync::GAL_DISPLAYNAME]);
                        $this->_encoder->endTag();

                        $this->_encoder->startTag(Horde_ActiveSync::GAL_PHONE);
                        $this->_encoder->content($u[Horde_ActiveSync::GAL_PHONE]);
                        $this->_encoder->endTag();

                        $this->_encoder->startTag(Horde_ActiveSync::GAL_OFFICE);
                        $this->_encoder->content($u[Horde_ActiveSync::GAL_OFFICE]);
                        $this->_encoder->endTag();

                        $this->_encoder->startTag(Horde_ActiveSync::GAL_TITLE);
                        $this->_encoder->content($u[Horde_ActiveSync::GAL_TITLE]);
                        $this->_encoder->endTag();

                        $this->_encoder->startTag(Horde_ActiveSync::GAL_COMPANY);
                        $this->_encoder->content($u[Horde_ActiveSync::GAL_COMPANY]);
                        $this->_encoder->endTag();

                        $this->_encoder->startTag(Horde_ActiveSync::GAL_ALIAS);
                        $this->_encoder->content($u[Horde_ActiveSync::GAL_ALIAS]);
                        $this->_encoder->endTag();

                        $this->_encoder->startTag(Horde_ActiveSync::GAL_FIRSTNAME);
                        $this->_encoder->content($u[Horde_ActiveSync::GAL_FIRSTNAME]);
                        $this->_encoder->endTag();

                        $this->_encoder->startTag(Horde_ActiveSync::GAL_LASTNAME);
                        $this->_encoder->content($u[Horde_ActiveSync::GAL_LASTNAME]);
                        $this->_encoder->endTag();

                        $this->_encoder->startTag(Horde_ActiveSync::GAL_HOMEPHONE);
                        $this->_encoder->content($u[Horde_ActiveSync::GAL_HOMEPHONE]);
                        $this->_encoder->endTag();

                        $this->_encoder->startTag(Horde_ActiveSync::GAL_MOBILEPHONE);
                        $this->_encoder->content($u[Horde_ActiveSync::GAL_MOBILEPHONE]);
                        $this->_encoder->endTag();

                        $this->_encoder->startTag(Horde_ActiveSync::GAL_EMAILADDRESS);
                        $this->_encoder->content($u[Horde_ActiveSync::GAL_EMAILADDRESS]);
                        $this->_encoder->endTag();

                        if ($this->_device->version >= Horde_ActiveSync::VERSION_FOURTEENONE
                            && !empty($u[Horde_ActiveSync::GAL_PICTURE])) {
                            $this->_encoder->startTag(Horde_ActiveSync::GAL_PICTURE);
                            $u[Horde_ActiveSync::GAL_PICTURE]->encodeStream($this->_encoder);
                            $this->_encoder->endTag();
                        }

                        $this->_encoder->endTag();//properties
                        $this->_encoder->endTag();//result
                        break;
                    case 'mailbox':
                        $this->_encoder->startTag(self::SEARCH_RESULT);
                        $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERTYPE);
                        $this->_encoder->content(Horde_ActiveSync::CLASS_EMAIL);
                        $this->_encoder->endTag();
                        $this->_encoder->startTag(self::SEARCH_LONGID);
                        $this->_encoder->content($u['uniqueid']);
                        $this->_encoder->endTag();
                        $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERID);
                        $this->_encoder->content($this->_collections->getFolderUidForBackendId($u['searchfolderid']));
                        $this->_encoder->endTag();
                        $this->_encoder->startTag(self::SEARCH_PROPERTIES);
                        $msg = $this->_driver->ItemOperationsFetchMailbox($u['uniqueid'], $searchbodypreference, $mime);
                        $msg->encodeStream($this->_encoder);
                        $this->_encoder->endTag();//properties
                        $this->_encoder->endTag();//result
                }
            }

            $search_range = $start . '-' . ($start + count($results->rows) - 1);
            $this->_encoder->startTag(self::SEARCH_RANGE);
            $this->_encoder->content($search_range);
            $this->_encoder->endTag();

            $this->_encoder->startTag(self::SEARCH_TOTAL);
            $this->_encoder->content($results->total);
            $this->_encoder->endTag();
        }

        $this->_encoder->endTag();//store
        $this->_encoder->endTag();//response
        $this->_encoder->endTag();//search

        return true;
    }

    /**
     * Receive, and parse, the incoming wbxml query.
     *
     * According to MS docs, OR is supported in the protocol, but will ALWAYS
     * return a searchToComplex status in Exchange 2007. Additionally, AND is
     * ONLY supported as the topmost element. No nested AND is allowed. All
     * such queries will return a searchToComplex status.
     *
     * @param boolean $subquery  Parsing a subquery.
     *
     * @return array | false on error
     */
    protected function _parseQuery($subquery = null)
    {
        $query = [];
        while (($type = ($this->_decoder->getElementStartTag(self::SEARCH_AND) ? self::SEARCH_AND
                : ($this->_decoder->getElementStartTag(self::SEARCH_OR) ? self::SEARCH_OR
                : ($this->_decoder->getElementStartTag(self::SEARCH_EQUALTO) ? self::SEARCH_EQUALTO
                : ($this->_decoder->getElementStartTag(self::SEARCH_LESSTHAN) ? self::SEARCH_LESSTHAN
                : ($this->_decoder->getElementStartTag(self::SEARCH_GREATERTHAN) ? self::SEARCH_GREATERTHAN
                : ($this->_decoder->getElementStartTag(self::SEARCH_FREETEXT) ? self::SEARCH_FREETEXT
                : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERID) ? Horde_ActiveSync::SYNC_FOLDERID
                : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERTYPE) ? Horde_ActiveSync::SYNC_FOLDERTYPE
                : ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_LINKID) ? Horde_ActiveSync::SYNC_DOCUMENTLIBRARY_LINKID
                : ($this->_decoder->getElementStartTag(Horde_ActiveSync_Message_Mail::POOMMAIL_DATERECEIVED) ? Horde_ActiveSync_Message_Mail::POOMMAIL_DATERECEIVED
                : -1))))))))))) != -1) {


            switch ($type) {
                case self::SEARCH_AND:
                case self::SEARCH_OR:
                case self::SEARCH_EQUALTO:
                case self::SEARCH_LESSTHAN:
                case self::SEARCH_GREATERTHAN:
                    $q = [
                        'op' => $type,
                        'value' => $this->_parseQuery(true),
                    ];
                    if ($subquery) {
                        $query['subquery'][] = $q;
                    } else {
                        $query[] = $q;
                    }
                    $this->_decoder->getElementEndTag();
                    break;
                default:
                    if (($query[$type] = $this->_decoder->getElementContent())) {
                        if ($type == Horde_ActiveSync::SYNC_FOLDERID) {
                            try {
                                $query['serverid'] = $this->_collections->getBackendIdForFolderUid($query[$type]);
                            } catch (Horde_ActiveSync_Exception_FolderGone $e) {
                                $this->_logger->err($e->getMessage());
                            }
                        }
                        $this->_decoder->getElementEndTag();
                    } else {
                        $this->_decoder->getElementStartTag(self::SEARCH_VALUE);
                        $query[$type] = $this->_decoder->getElementContent();
                        switch ($type) {
                            case Horde_ActiveSync_Message_Mail::POOMMAIL_DATERECEIVED:
                                $query[$type] = new Horde_Date($query[$type]);
                                break;
                        }
                        $this->_decoder->getElementEndTag();
                    };
                    break;
            }
        }

        return $query;
    }

    protected function _handleError(array $data)
    {
        $this->_decoder->getElementEndTag(); // end SYNC_ITEMOPERATIONS_ITEMOPERATIONS
        $this->_encoder->startWBXML($this->_activeSync->multipart);
        $this->_encoder->startTag(self::SEARCH_SEARCH);
        $this->_encoder->startTag(self::SEARCH_STATUS);
        $this->_encoder->content($this->_statusCode);
        $this->_encoder->endTag();
        $this->_encoder->endTag();
    }

}
