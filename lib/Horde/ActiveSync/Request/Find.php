<?php

/**
 * Horde_ActiveSync_Request_Find
 *
 * Handle EAS 16.0 Find command requests.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 * @internal
 */
use Horde\Util\HordeString;

class Horde_ActiveSync_Request_Find extends Horde_ActiveSync_Request_SyncBase
{
    public const FIND_FIND                    = 'Find:Find';
    public const FIND_SEARCHID                = 'Find:SearchId';
    public const FIND_EXECUTESEARCH           = 'Find:ExecuteSearch';
    public const FIND_MAILBOXSEARCHCRITERION  = 'Find:MailBoxSearchCriterion';
    public const FIND_GALSEARCHCRITERION      = 'Find:GALSearchCriterion';
    public const FIND_QUERY                   = 'Find:Query';
    public const FIND_FREETEXT                = 'Find:FreeText';
    public const FIND_OPTIONS                 = 'Find:Options';
    public const FIND_RANGE                   = 'Find:Range';
    public const FIND_DEEPTRAVERSAL           = 'Find:DeepTraversal';
    public const FIND_PICTURE                 = 'Find:Picture';
    public const FIND_MAXSIZE                 = 'Find:MaxSize';
    public const FIND_MAXPICTURES             = 'Find:MaxPictures';
    public const FIND_STATUS                  = 'Find:Status';
    public const FIND_RESPONSE                = 'Find:Response';
    public const FIND_RESULT                  = 'Find:Result';
    public const FIND_PROPERTIES              = 'Find:Properties';
    public const FIND_TOTAL                   = 'Find:Total';
    public const FIND_DISPLAYCC               = 'Find:DisplayCc';
    public const FIND_DISPLAYBCC              = 'Find:DisplayBcc';
    public const FIND_PREVIEW                 = 'Find:Preview';
    public const FIND_HASATTACHMENTS          = 'Find:HasAttachments';

    public const STATUS_SUCCESS               = 1;
    public const STATUS_ERROR                 = 2;

    public const STORE_STATUS_SUCCESS         = 1;
    public const STORE_STATUS_SERVERERR       = 3;
    public const STORE_STATUS_RANGEERR        = 12;

    protected const MAX_RESULTS               = 100;

    /**
     * @var Horde_ActiveSync_Collections
     */
    protected $_collections;

    /**
     * Client FolderId from the Find request (for result encoding).
     *
     * @var string|null
     */
    protected $_findFolderUid;

    /**
     * Handle request.
     *
     * @return boolean
     */
    protected function _handle()
    {
        $this->_logger->meta('Handling FIND command.');
        $this->_collections = $this->_activeSync->getCollectionsObject();

        if (!$this->_decoder->getElementStartTag(self::FIND_FIND)) {
            throw new Horde_ActiveSync_Exception_InvalidRequest('Missing required Find element.');
        }

        $searchId = null;
        $type = null;
        $query = [];
        $options = [];
        $range = null;
        $deepTraversal = false;
        $bodyprefs = [];
        $mime = Horde_ActiveSync::MIME_SUPPORT_NONE;

        if ($this->_decoder->getElementStartTag(self::FIND_SEARCHID)) {
            $searchId = $this->_decoder->getElementContent();
            if (!$this->_decoder->getElementEndTag()) {
                return false;
            }
        }

        if (!$this->_decoder->getElementStartTag(self::FIND_EXECUTESEARCH)) {
            throw new Horde_ActiveSync_Exception_InvalidRequest('Missing required ExecuteSearch element.');
        }

        if ($this->_decoder->getElementStartTag(self::FIND_MAILBOXSEARCHCRITERION)) {
            $type = 'mailbox';
            $parsed = $this->_parseMailboxCriterion();
            if ($parsed === false) {
                return false;
            }
            $query = $parsed['query'];
            if (!empty($parsed['options'])) {
                $options = array_merge($options, $parsed['options']);
            }
            if (!empty($options['range'])) {
                $range = $options['range'];
            }
            if (!empty($options['deeptraversal'])) {
                $deepTraversal = true;
            }
            if (!$this->_decoder->getElementEndTag()) {
                return false;
            }
        }

        if ($this->_decoder->getElementStartTag(self::FIND_GALSEARCHCRITERION)) {
            $type = 'gal';
            $query = ['text' => $this->_decoder->getElementContent()];
            if (!$this->_decoder->getElementEndTag()) {
                return false;
            }
        }

        if (!$type) {
            throw new Horde_ActiveSync_Exception_InvalidRequest('Missing search criterion.');
        }

        if (!$this->_decoder->getElementEndTag()) { // ExecuteSearch
            return false;
        }

        if ($this->_decoder->getElementStartTag(self::FIND_OPTIONS)) {
            while (1) {
                if ($this->_decoder->getElementStartTag(self::FIND_RANGE)) {
                    $range = $this->_decoder->getElementContent();
                    if (!$this->_decoder->getElementEndTag()) {
                        return false;
                    }
                }
                if ($this->_decoder->getElementStartTag(self::FIND_DEEPTRAVERSAL)) {
                    if (!($deepTraversal = $this->_decoder->getElementContent())) {
                        $deepTraversal = true;
                    } elseif (!$this->_decoder->getElementEndTag()) {
                        return false;
                    }
                }
                if ($this->_decoder->getElementStartTag(Horde_ActiveSync::AIRSYNCBASE_BODYPREFERENCE)) {
                    $this->_bodyPrefs($bodyprefs);
                }
                if ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_MIMESUPPORT)) {
                    $this->_mimeSupport($bodyprefs);
                }
                if ($this->_decoder->getElementStartTag(self::FIND_PICTURE)) {
                    $options[self::FIND_PICTURE] = true;
                    if ($this->_decoder->getElementStartTag(self::FIND_MAXSIZE)) {
                        $options[self::FIND_MAXSIZE] = $this->_decoder->getElementContent();
                        if (!$this->_decoder->getElementEndTag()) {
                            return false;
                        }
                    }
                    if ($this->_decoder->getElementStartTag(self::FIND_MAXPICTURES)) {
                        $options[self::FIND_MAXPICTURES] = $this->_decoder->getElementContent();
                        if (!$this->_decoder->getElementEndTag()) {
                            return false;
                        }
                    }
                    if (!$this->_decoder->getElementEndTag()) {
                        return false;
                    }
                }

                $e = $this->_decoder->peek();
                if ($e[Horde_ActiveSync_Wbxml::EN_TYPE] == Horde_ActiveSync_Wbxml::EN_TYPE_ENDTAG) {
                    $this->_decoder->getElementEndTag();
                    break;
                }
            }
        }

        if (!$this->_decoder->getElementEndTag()) { // Find
            return false;
        }

        $this->_findFolderUid = $query['collectionid'] ?? null;
        $this->_logger->info(sprintf(
            'Find criteria: type=%s searchId=%s folderUid=%s serverid=%s range=%s deepTraversal=%s freetext_len=%d',
            $type,
            $searchId ?? '',
            $this->_findFolderUid ?? '',
            $query['serverid'] ?? '',
            $range ?? '',
            $deepTraversal ? 'yes' : 'no',
            isset($query['freetext']) ? strlen($query['freetext']) : 0
        ));
        if (!empty($query['freetext'])) {
            $this->_logger->info('Find FreeText: ' . $query['freetext']);
        } elseif (!empty($query['text'])) {
            $this->_logger->info('Find GAL query: ' . $query['text']);
        }

        $status = self::STATUS_SUCCESS;
        $storeStatus = self::STORE_STATUS_SUCCESS;
        $start = 0;
        $limit = self::MAX_RESULTS;

        if ($range !== null) {
            if (preg_match('/^(\d+)-(\d+)$/', $range, $matches)) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];
                if ($end < $start) {
                    $storeStatus = self::STORE_STATUS_RANGEERR;
                } else {
                    $limit = $end - $start + 1;
                    // iOS sends 0-100 (101 slots); cap to server maximum.
                    if ($limit > self::MAX_RESULTS) {
                        $limit = self::MAX_RESULTS;
                    }
                }
            } else {
                $storeStatus = self::STORE_STATUS_RANGEERR;
            }
        }

        $results = null;
        if ($storeStatus === self::STORE_STATUS_SUCCESS && $type) {
            $params = new Horde_ActiveSync_Find_Params(
                type: $type,
                searchId: $searchId,
                query: $query,
                options: $options,
                start: $start,
                limit: $limit,
                deepTraversal: $deepTraversal,
            );

            $results = $this->_driver->getFindResults(
                $params,
                empty($bodyprefs['bodyprefs']) ? [] : $bodyprefs['bodyprefs'],
                $mime
            );

            if ($results->rows === null) {
                $storeStatus = self::STORE_STATUS_SERVERERR;
            }
        }

        if ($this->_clientDisconnected()) {
            $this->_logger->meta(
                'FIND: Client disconnected, skipping response encoding.'
            );
            return true;
        }

        $this->_encoder->startWBXML();
        $this->_encoder->startTag(self::FIND_FIND);
        $this->_encoder->startTag(self::FIND_STATUS);
        $this->_encoder->content($status);
        $this->_encoder->endTag();

        if ($status === self::STATUS_SUCCESS) {
            $this->_encoder->startTag(self::FIND_RESPONSE);
            $this->_encoder->startTag(Horde_ActiveSync_Request_ItemOperations::ITEMOPERATIONS_STORE);
            $this->_encoder->content('Mailbox');
            $this->_encoder->endTag();

            $this->_encoder->startTag(self::FIND_STATUS);
            $this->_encoder->content($storeStatus);
            $this->_encoder->endTag();

            if ($storeStatus === self::STORE_STATUS_SUCCESS && $results) {
                $returned = 0;
                if ($results->rows) {
                    $bodyPrefs = empty($bodyprefs['bodyprefs']) ? [] : $bodyprefs['bodyprefs'];
                    if (empty($bodyPrefs['preview'])) {
                        $bodyPrefs['preview'] = 255;
                    }
                    foreach ($results->rows as $row) {
                        if ($this->_clientDisconnected()) {
                            $this->_logger->meta(
                                'FIND: Client disconnected during result encoding.'
                            );
                            break;
                        }
                        if ($this->_encodeResult($row, $type, $bodyPrefs, $mime)) {
                            $returned++;
                        }
                    }
                }
                $searchRange = $returned
                    ? $start . '-' . ($start + $returned - 1)
                    : $start . '-' . $start;
                $this->_encoder->startTag(self::FIND_RANGE);
                $this->_encoder->content($searchRange);
                $this->_encoder->endTag();

                $this->_encoder->startTag(self::FIND_TOTAL);
                $this->_encoder->content($results->total);
                $this->_encoder->endTag();
            }

            $this->_encoder->endTag(); // Response
        }

        $this->_encoder->endTag(); // Find

        return true;
    }

    /**
     * Parse MailBoxSearchCriterion.
     *
     * @return array
     */
    protected function _parseMailboxCriterion()
    {
        $query = [];
        $options = [];

        if (!$this->_decoder->getElementStartTag(self::FIND_QUERY)) {
            throw new Horde_ActiveSync_Exception_InvalidRequest('Missing required Query element.');
        }

        if ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERTYPE)) {
            $query['class'] = $this->_decoder->getElementContent();
            if (!$this->_decoder->getElementEndTag()) {
                return false;
            }
        }

        if ($this->_decoder->getElementStartTag(Horde_ActiveSync::SYNC_FOLDERID)) {
            $folderUid = $this->_decoder->getElementContent();
            try {
                $query['collectionid'] = $folderUid;
                $query['serverid'] = $this->_collections->getBackendIdForFolderUid($folderUid);
            } catch (Horde_ActiveSync_Exception_FolderGone $e) {
                $this->_logger->err($e->getMessage());
            }
            if (!$this->_decoder->getElementEndTag()) {
                return false;
            }
        }

        if ($this->_decoder->getElementStartTag(self::FIND_FREETEXT)) {
            $query['freetext'] = $this->_decoder->getElementContent();
            if (!$this->_decoder->getElementEndTag()) {
                return false;
            }
        }

        if (!$this->_decoder->getElementEndTag()) { // Query
            return false;
        }

        if ($this->_decoder->getElementStartTag(self::FIND_OPTIONS)) {
            $options = $this->_parseOptions();
            if ($options === false) {
                return false;
            }
        }

        return ['query' => $query, 'options' => $options];
    }

    /**
     * Parse an Options block.
     *
     * @return array
     */
    protected function _parseOptions()
    {
        $options = [];
        while (1) {
            if ($this->_decoder->getElementStartTag(self::FIND_RANGE)) {
                $options['range'] = $this->_decoder->getElementContent();
                if (!$this->_decoder->getElementEndTag()) {
                    return false;
                }
            }
            if ($this->_decoder->getElementStartTag(self::FIND_DEEPTRAVERSAL)) {
                $options['deeptraversal'] = true;
                if ($this->_decoder->getElementContent() !== false
                    && !$this->_decoder->getElementEndTag()) {
                    return false;
                }
            }
            if ($this->_decoder->getElementStartTag(self::FIND_PICTURE)) {
                $options[self::FIND_PICTURE] = true;
                if ($this->_decoder->getElementStartTag(self::FIND_MAXSIZE)) {
                    $options[self::FIND_MAXSIZE] = $this->_decoder->getElementContent();
                    $this->_decoder->getElementEndTag();
                }
                if ($this->_decoder->getElementStartTag(self::FIND_MAXPICTURES)) {
                    $options[self::FIND_MAXPICTURES] = $this->_decoder->getElementContent();
                    $this->_decoder->getElementEndTag();
                }
                $this->_decoder->getElementEndTag();
            }

            $e = $this->_decoder->peek();
            if ($e[Horde_ActiveSync_Wbxml::EN_TYPE] == Horde_ActiveSync_Wbxml::EN_TYPE_ENDTAG) {
                $this->_decoder->getElementEndTag();
                break;
            }
        }

        return $options;
    }

    /**
     * Encode a single Find result.
     *
     * @param array       $row        Search hit from getSearchResults().
     * @param string|null $type       Search type.
     * @param array       $bodyprefs  Body preference options.
     * @param integer     $mime       MIME support flag.
     *
     * @return boolean  True if the result was encoded.
     */
    protected function _encodeResult(array $row, $type, array $bodyprefs, $mime)
    {
        if ($type === 'mailbox') {
            try {
                $msg = $this->_driver->itemOperationsFetchMailbox(
                    $row['uniqueid'],
                    $bodyprefs,
                    $mime
                );
            } catch (Horde_Exception_NotFound $e) {
                $this->_logger->info(sprintf(
                    'Find: message %s no longer available (%s), skipping result.',
                    $row['uniqueid'],
                    $e->getMessage()
                ));
                return false;
            }
        }

        $this->_encoder->startTag(self::FIND_RESULT);

        if ($type === 'mailbox') {
            $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERTYPE);
            $this->_encoder->content(Horde_ActiveSync::CLASS_EMAIL);
            $this->_encoder->endTag();

            [, $uid] = explode(':', $row['uniqueid'], 2);
            $this->_encoder->startTag(Horde_ActiveSync::SYNC_SERVERENTRYID);
            $this->_encoder->content($uid);
            $this->_encoder->endTag();

            $folderUid = $this->_findFolderUid
                ?? $this->_collections->getFolderUidForBackendId($row['searchfolderid']);
            if (empty($folderUid)) {
                $this->_logger->err(sprintf(
                    'Find: no client FolderId for mailbox %s (UID in %s).',
                    $row['searchfolderid'],
                    $row['uniqueid']
                ));
            }
            $this->_encoder->startTag(Horde_ActiveSync::SYNC_FOLDERID);
            $this->_encoder->content($folderUid ?: '');
            $this->_encoder->endTag();

            $this->_encoder->startTag(self::FIND_PROPERTIES);
            $this->_encodeFindMailProperties($msg);
            $this->_encoder->endTag(); // Properties
        } elseif ($type === 'gal') {
            $this->_encoder->startTag(self::FIND_PROPERTIES);
            foreach ($row as $tag => $value) {
                if ($value === '' || $tag === 'class') {
                    continue;
                }
                $this->_encoder->startTag($tag);
                $this->_encoder->content($value);
                $this->_encoder->endTag();
            }
            $this->_encoder->endTag();
        }

        $this->_encoder->endTag(); // Result

        return true;
    }

    /**
     * Encode the mail properties required for a Find response.
     *
     * @param Horde_ActiveSync_Message_Mail $msg  The message object.
     */
    protected function _encodeFindMailProperties(Horde_ActiveSync_Message_Mail $msg)
    {
        $this->_encodeFindElement(
            Horde_ActiveSync_Message_Mail::POOMMAIL_SUBJECT,
            $msg->subject
        );
        if ($msg->datereceived instanceof Horde_Date) {
            $this->_encodeFindElement(
                Horde_ActiveSync_Message_Mail::POOMMAIL_DATERECEIVED,
                $msg->datereceived->setTimezone('UTC')->format('Y-m-d\TH:i:s.000\Z')
            );
        }
        $this->_encodeFindElement(
            Horde_ActiveSync_Message_Mail::POOMMAIL_DISPLAYTO,
            $msg->displayto
        );
        $this->_encodeFindElement(
            Horde_ActiveSync_Message_Mail::POOMMAIL_FROM,
            $msg->from
        );
        $this->_encodeFindElement(
            Horde_ActiveSync_Message_Mail::POOMMAIL_IMPORTANCE,
            (string) ($msg->importance ?? 1)
        );
        $this->_encodeFindElement(
            Horde_ActiveSync_Message_Mail::POOMMAIL_READ,
            $msg->read ? '1' : '0'
        );
        if (isset($msg->isdraft)) {
            $this->_encodeFindElement(
                Horde_ActiveSync_Message_Mail::POOMMAIL2_ISDRAFT,
                $msg->isdraft ? '1' : '0'
            );
        }

        $this->_encodeFindElement(self::FIND_PREVIEW, $this->_extractPreview($msg));

        $hasAttachments = !empty($msg->airsyncbaseattachments);
        $this->_encodeFindElement(
            self::FIND_HASATTACHMENTS,
            $hasAttachments ? '1' : '0'
        );
        $this->_encodeFindElement(self::FIND_DISPLAYCC, $msg->cc ?? '');
        $this->_encodeFindElement(self::FIND_DISPLAYBCC, $msg->bcc ?? '');
    }

    /**
     * Build a Find preview string from message body data.
     *
     * @param Horde_ActiveSync_Message_Mail $msg  The message object.
     *
     * @return string
     */
    protected function _extractPreview(Horde_ActiveSync_Message_Mail $msg): string
    {
        if (empty($msg->airsyncbasebody)) {
            return '';
        }

        if (!empty($msg->airsyncbasebody->preview)) {
            return (string) $msg->airsyncbasebody->preview;
        }

        if (empty($msg->airsyncbasebody->data)) {
            return '';
        }

        $data = $msg->airsyncbasebody->data;
        if (is_resource($data)) {
            $data = stream_get_contents($data, 255);
            if ($data === false) {
                return '';
            }
        }

        return HordeString::substr((string) $data, 0, 255);
    }

    /**
     * @param string $tag    WBXML tag name.
     * @param string $value  Element content.
     */
    protected function _encodeFindElement($tag, $value)
    {
        if ($value === '' || $value === null) {
            return;
        }
        $this->_encoder->startTag($tag);
        $this->_encoder->content($value);
        $this->_encoder->endTag();
    }
}
