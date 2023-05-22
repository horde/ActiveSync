<?php
/**
 * Horde_ActiveSync_Imap_MessageBodyData::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property array html     An array describing the text/html part with:
 *     - charset:  (string)   The charset of the text.
 *     - body: (Horde_Stream) The body text in a stream.
 *     - truncated: (boolean) True if text was truncated.
 *     - size: (integer)      The original part size, in bytes.
 *
 * @property array plain    An array describing the text/plain part with:
 *     - charset:  (string)   The charset of the text.
 *     - body: (Horde_Stream) The body text in a stream.
 *     - truncated: (boolean) True if text was truncated.
 *     - size: (integer)      The original part size, in bytes.
 *
 * @property array bodyPart An array describing the BODYPART requested. BODYPART
 *                          is typically a truncated text/html representation of
 *                          part of the message. Very few clients request this.
 *     - charset:  (string)   The charset of the text.
 *     - body: (Horde_Stream) The body text in a stream.
 *     - truncated: (boolean) True if text was truncated.
 *     - size: (integer)      The original part size, in bytes.
 *
 * @property boolean|integer  The Horde_ActiveSync::BODYPREF_TYPE of the
 *                            original email on the server, or false if
 *                            not able to be determined.
 */
class Horde_ActiveSync_Imap_MessageBodyData
{
    /**
     *
     * @var Horde_ActiveSync_Imap_Adapter
     */
    protected $_imap;

    /**
     * @var Horde_ActiveSync_Mime
     */
    protected $_basePart;

    /**
     *
     * @var array
     */
    protected $_options;

    /**
     *
     * @var float
     */
    protected $_version;

    /**
     *
     * @var Horde_Imap_Client_Mailbox
     */
    protected $_mbox;

    /**
     *
     * @var integer
     */
    protected $_uid;

    /**
     *
     * @var array
     */
    protected $_plain;

    /**
     *
     * @var array
     */
    protected $_html;

    /**
     *
     * @var array
     */
    protected $_bodyPart;

    /**
     * Flag to indicate self::$_Plain is validated.
     *
     * @var boolean
     */
    protected $_validatedPlain;

    /**
     * Flag to indicate self::$_html is validated.
     *
     * @var boolean
     */
    protected $_validatedHtml;

    /**
     * The body part type of the original email if potentially different
     * than the BODYPREF_TYPE that is being requested.
     *
     * @var boolean | integer  False if not specified, otherwise a
     *      Horde_ActiveSync::BODYPREF_TYPE constant.
     */
    protected $_nativeType = false;

    /**
     * Const'r
     *
     * @param array $params  Parameters:
     *     - imap: (Horde_Imap_Client_Base)     The IMAP client.
     *     - mbox: (Horde_Imap_Client_Mailbox)  The mailbox.
     *     - uid: (integer) The message UID.
     *     - mime: (Horde_ActiveSync_Mime)      The MIME object.
     *
     * @param array $options  The options array.
     */
    public function __construct(array $params, array $options)
    {
        stream_filter_register('horde_eol', 'Horde_Stream_Filter_Eol');

        $this->_imap = $params['imap'];
        $this->_basePart = $params['mime'];
        $this->_mbox = $params['mbox'];
        $this->_uid = $params['uid'];
        $this->_options = $options;

        $this->_version = empty($options['protocolversion']) ?
            Horde_ActiveSync::VERSION_TWOFIVE :
            $options['protocolversion'];

        $this->_getParts();
    }

    public function __destruct()
    {
        $this->_basePart = null;
        $this->_imap = null;
        if (!empty($this->_plain) && ($this->_plain['body'] instanceof Horde_Stream)) {
            $this->_plain['body'] = null;
        }
        if (!empty($this->_html) && ($this->_html['body'] instanceof Horde_Stream)) {
            $this->_html['body'] = null;
        }
    }

    public function &__get($property)
    {
        switch ($property) {
        case 'plain':
            $body = $this->plainBody();
            return $body;
        case 'html':
            $body = $this->htmlBody();
            return $body;
        case 'bodyPart':
            $body = $this->bodyPartBody();
            return $body;
        case 'nativeBodyType':
            return $this->_nativeType;
        default:
            throw new InvalidArgumentException("Unknown property: $property");
        }
    }

    public function __set($property, $value)
    {
        switch ($property) {
        case 'html':
            $this->_html = $value;
            break;
        default:
            throw new InvalidArgumentException("$property can not be set.");
        }
    }

    /**
     * Return the BODYTYPE to return to the client. Takes BODYPREF and available
     * parts into account.
     *
     * @param  boolean $save_bandwith  If true, saves bandwidth usage by
     *                                 favoring HTML over MIME BODYTYPE if able.
     *
     * @return integer  A Horde_ActiveSync::BODYPREF_TYPE_* constant.
     */
    public function getBodyTypePreference($save_bandwidth = false)
    {
        // Apparently some clients don't send the MIME_SUPPORT field (thus
        // defaulting it to MIME_SUPPORT_NONE), but still request
        // BODYPREF_TYPE_MIME. Failure to do this results in NO data being
        // sent to the client, so we ignore the MIME_SUPPORT requirement and
        // assume it is implied if it is requested in a BODYPREF element.
        $bodyprefs = $this->_options['bodyprefs'];
        if ($save_bandwidth) {
            return !empty($bodyprefs[Horde_ActiveSync::BODYPREF_TYPE_HTML]) && !empty($this->_html)
                ? Horde_ActiveSync::BODYPREF_TYPE_HTML
                : (!empty($bodyprefs[Horde_ActiveSync::BODYPREF_TYPE_MIME])
                    ? Horde_ActiveSync::BODYPREF_TYPE_MIME
                    : Horde_ActiveSync::BODYPREF_TYPE_PLAIN);
        }

        // Prefer high bandwidth, full MIME.
        return !empty($bodyprefs[Horde_ActiveSync::BODYPREF_TYPE_MIME])
            ? Horde_ActiveSync::BODYPREF_TYPE_MIME
            : (!empty($bodyprefs[Horde_ActiveSync::BODYPREF_TYPE_HTML]) && !empty($this->_html)
                ? Horde_ActiveSync::BODYPREF_TYPE_HTML
                : Horde_ActiveSync::BODYPREF_TYPE_PLAIN);
    }

    /**
     * Determine which parts we need, and fetches them from the IMAP client.
     * Takes into account the available parts and the BODYPREF/BODYPARTPREF
     * options.
     */
    protected function _getParts()
    {
        // Look for the parts we need. We try to detect and fetch only the parts
        // we need, while ensuring we have something to return. So, e.g., if we
        // don't have BODYPREF_TYPE_HTML, we only request plain text, but if we
        // can't find plain text but we have a html body, fetch that anyway.
        //
        // If this is any type of Report (like a NDR) we can't use findBody
        // since some MTAs generate MDRs with no explicit mime type in the
        // human readable portion (the first part). We assume the MDR contains
        // three parts as specified in the RFC: (1) A human readable part, (2)
        // A machine parsable body Machine parsable body part
        // [message/disposition-notification] and (3) The (optional) original
        // message [message/rfc822]
        switch ($this->_basePart->getType()) {
        case 'message/disposition-notification':
            // OL may send this without an appropriate multipart/report wrapper.
            // Not sure what to do about this yet. Probably parse the machine
            // part and write out some basic text?
            break;
        case 'multipart/report':
            $iterator = $this->_basePart->partIterator(false);
            $iterator->rewind();
            if (!$curr = $iterator->current()) {
                break;
            }
            $text_id = $curr->getMimeId();
            $html_id = null;
            break;
        default:
            $text_id = $this->_basePart->findBody('plain');
            $html_id = $this->_basePart->findBody('html');
        }

        // Deduce which part(s) we need to request.
        $want_html_text = $this->_wantHtml();
        $want_plain_text = $this->_wantPlainText($html_id, $want_html_text);

        $want_html_as_plain = false;
        $want_plain_as_html = false;

        if (!empty($text_id) && $want_plain_text) {
            $text_body_part = $this->_basePart->getPart($text_id);
        } elseif ($want_plain_text && !empty($html_id) &&
                  empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_MIME])) {
            $want_html_text = true;
            $want_html_as_plain = true;
        }

        if (!empty($html_id) && $want_html_text) {
            $html_body_part = $this->_basePart->getPart($html_id);
        } elseif ($want_html_text &&
                  empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_MIME])) {
            // Want HTML text, but do not have a text/html part.
            $want_plain_as_html = true;
        }

        // Make sure we have truncation if needed.
        if (empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_PLAIN]) &&
            !empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_HTML]) &&
            $want_plain_text && $want_html_text) {

            // We only have HTML truncation data, requested HTML body but only
            // have plaintext.
            $this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_PLAIN] =
                $this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_HTML];
        }

        // Fetch the data from the IMAP client.
        $data = $this->_fetchData(array('html_id' => $html_id, 'text_id' => $text_id));

        // Get the text/plain part if needed, possibly also converting it to
        // text/html if required.
        if (!empty($text_id) && $want_plain_text) {
            $this->_plain = $this->_getPlainPart($data, $text_body_part);
            if ($want_plain_as_html) {
                // Note: We have to use $data here again since $this->_plain
                // could be truncated at this point.
                $this->_html = $this->_getPlainPart2Html($data, $text_body_part);
            }
            $this->_nativeType = Horde_ActiveSync::BODYPREF_TYPE_PLAIN;
        }

        // Get the text/html part if needed, possibly converting it to
        // text/plain if required.
        if (!empty($html_id) && $want_html_text) {
            $this->_html = $this->_getHtmlPart($data, $html_body_part);
            if ($want_html_as_plain) {
                $this->_plain = $this->_getHtmlPart2Plain($data, $html_body_part);
            }
            $this->_nativeType = Horde_ActiveSync::BODYPREF_TYPE_HTML;
        }

        if (!empty($this->_options['bodypartprefs'])) {
            $this->_bodyPart = $this->_getBodyPart(
                $data,
                !empty($html_id) ? $html_body_part : $text_body_part,
                empty($html_id)
            );
        }
        $text_body_part = null;
        $html_body_part = null;
    }

    /**
     * Return if we want HTML data.
     *
     * @return boolean  True if HTML data is needed.
     */
    protected function _wantHtml()
    {
        return $this->_version >= Horde_ActiveSync::VERSION_TWELVE &&
            (!empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_HTML]) ||
            !empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_MIME]) ||
            !empty($this->_options['bodypartprefs']));
    }

    /**
     * Return if we want plain text data.
     *
     * @param  string $html_id     The MIME id of any HTML part, if available.
     *                             Used to detect if we need to fetch the plain
     *                             part if we are requesting HTML, but only have
     *                             plain.
     * @param  boolean $want_html  True if the client wants HTML.
     *
     * @return boolean  True if plain data is needed.
     */
    protected function _wantPlainText($html_id, $want_html)
    {
        return $this->_version == Horde_ActiveSync::VERSION_TWOFIVE ||
            empty($this->_options['bodyprefs']) ||
            !empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_PLAIN]) ||
            !empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_RTF]) ||
            !empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_MIME]) ||
            ($want_html && empty($html_id));
    }

    /**
     * Fetch data from the IMAP client.
     *
     * @param  array $params  Parameter array.
     *     - html_id (string)  The MIME id of the HTML part, if any.
     *     - text_id (string)  The MIME id of the plain part, if any.
     *
     * @return Horde_Imap_Client_Data_Fetch  The results.
     * @throws  Horde_ActiveSync_Exception,
     *          Horde_ActiveSync_Exception_EmailFatalFailure
     */
    protected function _fetchData(array $params)
    {
        $query = new Horde_Imap_Client_Fetch_Query();
        $query_opts = array(
            'decode' => true,
            'peek' => true
        );

        // Get body information
        if ($this->_version >= Horde_ActiveSync::VERSION_TWELVE) {
            if (!empty($params['html_id'])) {
                $query->bodyPartSize($params['html_id']);
                $query->bodyPart($params['html_id'], $query_opts);
            }
            if (!empty($params['text_id'])) {
                $query->bodyPart($params['text_id'], $query_opts);
                $query->bodyPartSize($params['text_id']);
            }
        } else {
            // EAS 2.5 Plaintext body
            $query->bodyPart($params['text_id'], $query_opts);
            $query->bodyPartSize($params['text_id']);
        }
        try {
            $fetch_ret = $this->_imap->fetch(
                $this->_mbox,
                $query,
                array('ids' => new Horde_Imap_Client_Ids(array($this->_uid)))
            );
        } catch (Horde_Imap_Client_Exception $e) {
            // If we lost the connection, don't continue to try.
            if ($e->getCode() == Horde_Imap_Client_Exception::DISCONNECT) {
                throw new Horde_ActiveSync_Exception_TemporaryFailure($e->getMessage());
            }
            throw new Horde_ActiveSync_Exception($e);
        }
        if (!$data = $fetch_ret->first()) {
            throw new Horde_Exception_NotFound(
                sprintf('Could not load message %s from server.', $this->_uid));
        }

        return $data;
    }

    /**
     * Build the data needed for the plain part.
     *
     * @param  Horde_Imap_Client_Data_Fetch $data  The FETCH results.
     * @param  Horde_Mime_Part $text_mime          The plaintext MIME part.
     *
     * @return array  The plain part data.
     *     - charset:  (string)   The charset of the text.
     *     - body: (string)       The body text.
     *     - truncated: (boolean) True if text was truncated.
     *     - size: (integer)      The original part size, in bytes.
     */
    protected function _getPlainPart(
        Horde_Imap_Client_Data_Fetch $data,
        Horde_Mime_Part $text_mime)
    {
        $results = array();
        $text_id = $text_mime->getMimeId();
        $text = $data->getBodyPart($text_id);

        if (!$data->getBodyPartDecode($text_id)) {
            $text_mime->setContents($text);
            $text = $text_mime->getContents();
        }

        // Size of original part.
        $text_size = !is_null($data->getBodyPartSize($text_id))
            ? $data->getBodyPartSize($text_id)
            : strlen($text);

        if (!empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_PLAIN]['truncationsize'])) {
            // EAS >= 12.0 truncation
            $text = Horde_String::substr(
                $text,
                0,
                $this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_PLAIN]['truncationsize'],
                $text_mime->getCharset()
            );
        }

        $truncated = $text_size > strlen($text);
        if ($this->_version >= Horde_ActiveSync::VERSION_TWELVE &&
            $truncated && !empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_PLAIN]['allornone'])) {
            $text = '';
        }

        return array(
            'charset' => $text_mime->getCharset(),
            'body' => $text,
            'truncated' => $truncated,
            'size' => $text_size
        );
    }

    /**
     * Get the text/plain part and convert it into text/html.
     *
     * @param  Horde_Imap_Client_Data_Fetch $data  The FETCH results.
     * @param  Horde_Mime_Part $text_mime          The plaintext MIME part.
     *
     * @return array  The plain part data, converted to text/html.
     *     - charset:  (string)   The charset of the text.
     *     - body: (string)       The body text.
     *     - truncated: (boolean) True if text was truncated.
     *     - size: (integer)      The original part size, in bytes.
     */
    protected function _getPlainPart2Html(
        Horde_Imap_Client_Data_Fetch $data,
        Horde_Mime_Part $text_mime)
    {
        $text_id = $text_mime->getMimeId();
        $text = $data->getBodyPart($text_id);

        if (!$data->getBodyPartDecode($text_id)) {
            $text_mime->setContents($text);
            $text = $text_mime->getContents();
        }
        $charset = $text_mime->getCharset();

        return $this->_plain2Html($text, $charset);
    }

    /**
     * Helper method to convert, and possibly truncate text/plain body data
     * into text/html data taking into account BODYPREF_TYPE_HTML truncation.
     *
     * @param string $plain_text  The plain text body.
     * @param string $charset     The charset.
     *
     * @return  array  The text/html part data structure.
     */
    protected function _plain2Html($plain_text, $charset)
    {
       // Perform barebones conversion.
        $html_text = Horde_Text_Filter::filter(
            $plain_text,
            'Text2html',
             array(
                'charset' => $charset,
                'parselevel' => Horde_Text_Filter_Text2html::MICRO
            )
        );

        // Truncation
        $html_text_size = strlen($html_text);
        if (!empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_HTML]['truncationsize'])) {
            $html_text = Horde_String::substr(
                $html_text,
                0,
                $this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_HTML]['truncationsize'],
                $charset
            );
        }
        $html_truncated = $html_text_size > strlen($html_text);

        // Honor ALLORNONE
        if ($this->_version >= Horde_ActiveSync::VERSION_TWELVE &&
            $html_truncated && !empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_HTML]['allornone'])) {
            $html_text = '';
        }

        return array(
            'charset' => $charset,
            'body' => $html_text,
            'estimated_size' => $html_text_size,
            'truncated' => $html_truncated
        );
    }

    /**
     * Build the data needed for the html part.
     *
     * @param  Horde_Imap_Client_Data_Fetch $data  FETCH results.
     * @param  Horde_Mime_Part  $html_mime         The text/html MIME part.
     *
     * @return array  An array containing the html data.
     *                @see self::_getPlainPart for structure.
     */
    protected function _getHtmlPart(
        Horde_Imap_Client_Data_Fetch $data, Horde_Mime_Part $html_mime)
    {
        // @todo The length stuff in this method should really be done after
        // we validate the text since it might change if there was an incorrect
        // charset etc... For BC reasons, however, we need to keep the
        // unvalidated data available. Keep this as-is for now and refactor
        // for Horde 6. The worse-case here is that an incorrectly announced
        // charset MAY cause an email to be reported as truncated when it's not,
        // causing an additional reload on the client when viewing.
        $html_id = $html_mime->getMimeId();
        $html = $data->getBodyPart($html_id);
        if (!$data->getBodyPartDecode($html_id)) {
            $html_mime->setContents($html);
            $html = $html_mime->getContents();
        }
        $charset = $html_mime->getCharset();

        // Size of the original HTML part.
        $html_size = !is_null($data->getBodyPartSize($html_id))
            ? $data->getBodyPartSize($html_id)
            : strlen($html);

        if (!empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_HTML]['truncationsize'])) {
            $html = Horde_String::substr(
                $html,
                0,
                $this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_HTML]['truncationsize'],
                $charset);
        }

        $truncated = $html_size > strlen($html);
        if ($this->_version >= Horde_ActiveSync::VERSION_TWELVE &&
            $truncated &&
            !empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_HTML]['allornone'])) {

            $html = '';
        }
        return array(
            'charset' => $charset,
            'body' => $html,
            'estimated_size' => $html_size,
            'truncated' => $truncated
        );
    }

    /**
     *
     * @param  Horde_Imap_Client_Data_Fetch $data  FETCH results.
     * @param  Horde_Mime_Part  $html_mime         The text/html MIME part.
     *
     */
    protected function _getHtmlPart2Plain(
        Horde_Imap_Client_Data_Fetch $data, Horde_Mime_Part $html_mime)
    {
        $html_id = $html_mime->getMimeId();
        $html = $data->getBodyPart($html_id);
        if (!$data->getBodyPartDecode($html_id)) {
            $html_mime->setContents($html);
            $html = $html_mime->getContents();
        }
        $charset = $html_mime->getCharset();
        $html_plain = Horde_Text_Filter::filter(
            $html, 'Html2text', array('charset' => $charset, 'nestingLimit' => 1000));

        $html_plain_size = strlen($html_plain);
        if (!empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_PLAIN]['truncationsize'])) {
            // EAS >= 12.0 truncation
            $html_plain = Horde_String::substr(
                $html_plain,
                0,
                $this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_PLAIN]['truncationsize'],
                $charset
            );
        }
        $truncated = $html_plain_size > strlen($html_plain);
        if ($this->_version >= Horde_ActiveSync::VERSION_TWELVE &&
            $truncated &&
            !empty($this->_options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_PLAIN]['allornone'])) {

            $html_plain = '';
        }

        return array(
            'charset' => $charset,
            'body' => $html_plain,
            'truncated' => $truncated,
            'size' => $html_plain_size
        );
    }

    /**
     * Build the data needed for the BodyPart part.
     *
     * @param  Horde_Imap_Client_Data_Fetch $data  The FETCH results.
     * @param  Horde_Mime_Part $mime  The plaintext MIME part.
     * @param boolean $to_html        If true, $id is assumed to be a text/plain
     *                                part and is converted to html.
     *
     * @return array  The BodyPart data.
     *     - charset:  (string)   The charset of the text.
     *     - body: (string)       The body text.
     *     - truncated: (boolean) True if text was truncated.
     *     - size: (integer)      The original part size, in bytes.
     */
    protected function _getBodyPart(
        Horde_Imap_Client_Data_Fetch $data, Horde_Mime_Part $mime, $to_html)
    {
        $id = $mime->getMimeId();
        $text = $data->getBodyPart($id);
        if (!$data->getBodyPartDecode($id)) {
            $mime->setContents($text);
            $text = $mime->getContents();
        }

        if ($to_html) {
            $text = Horde_Text_Filter::filter(
                $text, 'Text2html', array('parselevel' => Horde_Text_Filter_Text2html::MICRO, 'charset' => $mime->getCharset()));
            $size = strlen($text);
        } else {
            $size = !is_null($data->getBodyPartSize($id))
                ? $data->getBodyPartSize($id)
                : strlen($text);
        }

        if (!empty($this->_options['bodypartprefs']['truncationsize'])) {
            $text = Horde_String::substr(
                $text,
                0,
                $this->_options['bodypartprefs']['truncationsize'],
                $mime->getCharset());
        }

        return array(
            'charset' => $mime->getCharset(),
            'body' => $text,
            'truncated' => $size > strlen($text),
            'size' => $size
        );
    }

    /**
     * Return the validated text/plain body data.
     *
     * @return array The validated body data array:
     *     - charset:  (string)   The charset of the text.
     *     - body: (Horde_Stream) The body text in a stream.
     *     - truncated: (boolean) True if text was truncated.
     *     - size: (integer)      The original part size, in bytes.
     */
    public function plainBody()
    {
        if (!empty($this->_plain) && empty($this->_validatedPlain)) {
            $this->_validateBodyData($this->_plain);
            $this->_validatedPlain = true;
        }
        if (!empty($this->_plain) && $this->_plain['body'] instanceof Horde_Stream) {
            return $this->_plain;
        }

        return false;
    }

    /**
     * Return the validated text/html body data.
     *
     * @return array The validated body data array:
     *     - charset:  (string)   The charset of the text.
     *     - body: (Horde_Stream) The body text in a stream.
     *     - truncated: (boolean) True if text was truncated.
     *     - size: (integer)      The original part size, in bytes.
     */
    public function htmlBody()
    {
        if (!empty($this->_html) && empty($this->_validatedHtml)) {
            $this->_validateBodyData($this->_html);
            $this->_validatedHtml = true;
        }
        if (!empty($this->_html['body']) && $this->_html['body'] instanceof Horde_Stream) {
            return $this->_html;
        }

        return false;
    }

    /**
     * Return the validated BODYPART data.
     *
     * @return array The validated body data array:
     *     - charset:  (string)   The charset of the text.
     *     - body: (Horde_Stream) The body text in a stream.
     *     - truncated: (boolean) True if text was truncated.
     *     - size: (integer)      The original part size, in bytes.
     */
    public function bodyPartBody()
    {
        if (!empty($this->_bodyPart)) {
            $this->_validateBodyData($this->_bodyPart);
            return $this->_bodyPart;
        }

        return false;
    }

    /**
     * Validate the body data to ensure consistent EOL and UTF8 data. Returns
     * body data in a stream object.
     *
     * @param array $data  The body data. @see self::_bodyPartText() for
     *                     structure.
     */
    protected function _validateBodyData(&$data)
    {
        $stream = new Horde_Stream_Temp(array('max_memory' => 1048576));
        $filter_h = stream_filter_append($stream->stream, 'horde_eol', STREAM_FILTER_WRITE);
        $stream->add(Horde_ActiveSync_Utils::ensureUtf8($data['body'], $data['charset']), true);
        stream_filter_remove($filter_h);

        $data['body'] = $stream;
    }

    /**
     * Return the body data in array format. Needed for BC.
     *
     * @return array
     * @todo remove in H6.
     */
    public function toArray()
    {
        $result = array();
        if ($this->plain) {
            $result['plain'] = $this->_plain;
        }
        if ($this->html) {
            $result['html'] = $this->_html;
        }

        return $result;
    }

}
