<?php

/**
 * Horde_ActiveSync_Request_LegacyCollection::
 *
 * Handle deprecated CreateCollection, DeleteCollection, and MoveCollection
 * commands. These predate FolderCreate/FolderDelete/FolderUpdate but perform
 * the same folder hierarchy operations.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 * @internal
 */
class Horde_ActiveSync_Request_LegacyCollection extends Horde_ActiveSync_Request_FolderCreate
{
    public const LEGACY_CREATE = 'CreateCollection';
    public const LEGACY_DELETE = 'DeleteCollection';
    public const LEGACY_MOVE   = 'MoveCollection';

    /**
     * Handle request.
     *
     * Accepts modern FolderCreate/FolderDelete/FolderUpdate bodies (some
     * clients send those with a legacy Cmd value) or the older
     * FolderHierarchy:Folder envelope.
     *
     * @return boolean
     */
    protected function _handle()
    {
        $next = $this->_decoder->peek();
        if ($next[Horde_ActiveSync_Wbxml::EN_TYPE] != Horde_ActiveSync_Wbxml::EN_TYPE_STARTTAG) {
            throw new Horde_ActiveSync_Exception('Protocol Error');
        }

        $tag = $next[Horde_ActiveSync_Wbxml::EN_TAG];
        if ($tag == self::FOLDERCREATE || $tag == self::FOLDERUPDATE || $tag == self::FOLDERDELETE) {
            return parent::_handle();
        }

        if ($tag == Horde_ActiveSync::FOLDERHIERARCHY_FOLDER || $tag == Horde_ActiveSync::SYNC_FOLDER) {
            return $this->_handleLegacyFolder($tag);
        }

        throw new Horde_ActiveSync_Exception('Protocol Error');
    }

    /**
     * Parse and handle a legacy FolderHierarchy:Folder (or AirSync:Folder)
     * request body.
     *
     * @param string $rootTag  Root WBXML tag name.
     *
     * @return boolean
     */
    protected function _handleLegacyFolder($rootTag)
    {
        $cmd = $this->_legacyCommand();
        $this->_logger->meta(
            sprintf('Handling legacy collection command %s.', $cmd)
        );

        if (!$this->_decoder->getElementStartTag($rootTag)) {
            throw new Horde_ActiveSync_Exception('Protocol Error');
        }

        $create = $cmd == self::LEGACY_CREATE;
        $delete = $cmd == self::LEGACY_DELETE;
        $update = $cmd == self::LEGACY_MOVE;

        if (!$this->_decoder->getElementStartTag(Horde_ActiveSync::FOLDERHIERARCHY_SYNCKEY)) {
            throw new Horde_ActiveSync_Exception('Protocol Error');
        }
        $synckey = $this->_decoder->getElementContent();
        if (!$this->_decoder->getElementEndTag()) {
            throw new Horde_ActiveSync_Exception('Protocol Error');
        }

        $server_uid = false;
        if ($this->_decoder->getElementStartTag(Horde_ActiveSync::FOLDERHIERARCHY_SERVERENTRYID)) {
            $server_uid = $this->_decoder->getElementContent();
            if ($server_uid !== false && !$this->_decoder->getElementEndTag()) {
                throw new Horde_ActiveSync_Exception('Protocol Error');
            }
        }

        $parentid = false;
        $displayname = false;
        $type = false;
        if (!$delete) {
            if ($this->_decoder->getElementStartTag(Horde_ActiveSync::FOLDERHIERARCHY_PARENTID)) {
                $parentid = $this->_decoder->getElementContent();
                if (!$this->_decoder->getElementEndTag()) {
                    throw new Horde_ActiveSync_Exception('Protocol Error');
                }
            }
            if ($this->_decoder->getElementStartTag(Horde_ActiveSync::FOLDERHIERARCHY_DISPLAYNAME)) {
                $displayname = $this->_decoder->getElementContent();
                if (!$this->_decoder->getElementEndTag()) {
                    throw new Horde_ActiveSync_Exception('Protocol Error');
                }
            }
            if ($this->_decoder->getElementStartTag(Horde_ActiveSync::FOLDERHIERARCHY_TYPE)) {
                $type = $this->_decoder->getElementContent();
                if (!$this->_decoder->getElementEndTag()) {
                    throw new Horde_ActiveSync_Exception('Protocol Error');
                }
            }
        }

        if (!$this->_decoder->getElementEndTag()) {
            throw new Horde_ActiveSync_Exception('Protocol Error');
        }

        $result = $this->_processFolderChange(
            $create,
            $update,
            $delete,
            $synckey,
            $server_uid,
            $parentid,
            $displayname,
            $type
        );

        $this->_encoder->startWBXML();
        $this->_encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_FOLDER);

        $this->_encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_STATUS);
        $this->_encoder->content($result['status']);
        $this->_encoder->endTag();

        if ($result['status'] == self::STATUS_SUCCESS) {
            $this->_encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_SYNCKEY);
            $this->_encoder->content($result['newsynckey']);
            $this->_encoder->endTag();

            if ($create && !empty($result['folder'])) {
                $this->_encoder->startTag(Horde_ActiveSync::FOLDERHIERARCHY_SERVERENTRYID);
                $this->_encoder->content($result['folder']->serverid);
                $this->_encoder->endTag();
            }
        }

        $this->_encoder->endTag();

        return true;
    }

    /**
     * Return the legacy HTTP command name from the request.
     *
     * @return string
     */
    protected function _legacyCommand()
    {
        $get = $this->_activeSync->getGetVars();
        if (!empty($get['Cmd'])) {
            return $get['Cmd'];
        }

        throw new Horde_ActiveSync_Exception('Protocol Error');
    }
}
