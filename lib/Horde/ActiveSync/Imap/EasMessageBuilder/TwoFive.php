<?php
/**
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */

/**
 * Handle building the body properties when using EAS version 2.5.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Imap_EasMessageBuilder_TwoFive extends Horde_ActiveSync_Imap_EasMessageBuilder
{
    /**
     * Perform all tasks.
     */
    protected function _buildBody()
    {
        $this->_logger->meta('Building EAS 2.5 style Message.');
        $this->_easMessage->body = $this->_mbd->plain['body']->stream;
        $this->_easMessage->bodysize = $this->_mbd->plain['body']->length(true);
        $this->_easMessage->bodytruncated = $this->_mbd->plain['truncated'];
        $this->_easMessage->attachments = $this->_imapMessage->getAttachments($this->_version);
    }

}
