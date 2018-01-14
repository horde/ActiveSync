<?php
/**
 *
 */
class Horde_ActiveSync_Mime_Headers_Addresses extends Horde_Mime_Headers_Addresses
{
   /**
     * Do send encoding for addresses.
     *
     * Needed as a static function because it is used by both single and
     * multiple address headers.
     *
     * @todo  Implement with traits.
     *
     * @param array $alist  An array of Horde_Mail_Rfc822_List objects.
     * @param array $opts   Additional options:
     *   - charset: (string) Encodes the headers using this charset.
     *              DEFAULT: UTF-8
     *   - defserver: (string) The default domain to append to mailboxes.
     *                DEFAULT: No default name.
     */
    public static function doSendEncode($alist, array $opts = array())
    {
        $opts['idn'] = false;
        return parent::doSendEncode($alist, $opts);
    }


    /**
     * @param array $opts  See doSendEncode().
     */
    protected function _sendEncode($opts)
    {
        return self::doSendEncode($this->getAddressList(), $opts);
    }

}
