<?php

/**
 * Horde_ActiveSync_Wbxml_Encoder::
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
 * Horde_ActiveSync_Wbxml_Encoder::  Encapsulates all Wbxml encoding from
 * server to client.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2009-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @package   ActiveSync
 */
use Horde\Util\HordeString;

class Horde_ActiveSync_Wbxml_Encoder extends Horde_ActiveSync_Wbxml
{
    /**
     * Cache the tags to output. The stack is output when content() is called.
     * We only output tags when they actually contain something. i.e. calling
     * startTag() 10 times, then endTag() will cause 0 bytes of output apart
     * from the header.
     *
     * @var array
     */
    private $_stack = [];

    /**
     * Flag to indicate if we are outputing multipart binary data during e.g.,
     * ITEMOPERATION requests.
     *
     * @var boolean
     */
    public $multipart;

    /**
     * Collection of parts to send in MULTIPART responses.
     *
     * @var array
     */
    protected $_parts = [];

    /**
     * Private stream when handling multipart output
     *
     * @var resource
     */
    protected $_tempStream;

    /**
     * Whether the WBXML document header was already written.
     *
     * Streaming Sync responses may pre-send the header (and keep-alive
     * tokens) while the incoming request is still being processed; the
     * regular startWBXML() call later must not emit it a second time.
     *
     * @var boolean
     */
    protected $_headerSent = false;

    /**
     * Const'r
     *
     * @param stream $output      The output stream
     * @param integer $log_level  The logging level
     *
     * @return Horde_ActiveSync_Wbxml_Encoder
     */
    public function __construct($output, $log_level = self::LOG_PROTOCOL)
    {
        parent::__construct($output, $log_level);

        /* reverse-map the DTD */
        $dtd = [];
        foreach ($this->_dtd['namespaces'] as $nsid => $nsname) {
            $dtd['namespaces'][$nsname] = $nsid;
        }

        foreach ($this->_dtd['codes'] as $cp => $value) {
            $dtd['codes'][$cp] = [];
            foreach ($this->_dtd['codes'][$cp] as $tagid => $tagname) {
                $dtd['codes'][$cp][$tagname] = $tagid;
            }
        }
        $this->_dtd = $dtd;
    }

    /**
     * Starts the wbxml output.
     *
     * @param boolean $multipart  Indicates we need to output mulitpart binary
     *                            binary data. See MS-ASCMD 2.2.1.8.1
     *
     */
    public function startWBXML($multipart = false)
    {
        $this->multipart = $multipart;
        if ($multipart) {
            $this->_tempStream = $this->_stream;
            $this->_stream = new Horde_Stream_Temp();
        }
        $this->outputWbxmlHeader();
    }

    /**
     * Output the Wbxml header to the output stream.
     *
     * A no-op if the header was already written (streaming Sync responses
     * pre-send it via keep-alive handling before the regular startWBXML()).
     */
    public function outputWbxmlHeader()
    {
        if ($this->_headerSent) {
            return;
        }
        $this->_headerSent = true;
        $this->_outByte(0x03);   // WBXML 1.3
        $this->_outMBUInt(0x01); // Public ID 1
        $this->_outMBUInt(106);  // UTF-8
        $this->_outMBUInt(0x00); // string table length (0)
    }

    /**
     * Start output for the specified tag
     *
     * @param string $tag            The name of the tag to start
     * @param mixed $attributes      Any attributes for the start tag
     * @param boolean $output_empty  Force output of empty tags
     *
     */
    public function startTag($tag, $attributes = false, $output_empty = false)
    {
        $stackelem = [];
        if (!$output_empty) {
            $stackelem['tag'] = $tag;
            $stackelem['attributes'] = $attributes;
            $stackelem['sent'] = false;
            $this->_stack[] = $stackelem;
        } else {
            /* Flush the stack if we want to force empty tags */
            $this->_outputStack();
            $this->_startTag($tag, $attributes, $output_empty);
        }
    }

    /**
     * Output the end tag
     *
     */
    public function endTag()
    {
        $stackelem = array_pop($this->_stack);
        if ($stackelem['sent']) {
            $this->_endTag();
            if (count($this->_stack) == 0 && $this->multipart) {
                $this->_stream->rewind();
                $len = $this->_stream->length();

                $totalCount = count($this->_parts) + 1;
                $header = pack('i', $totalCount);
                $offset = (($totalCount * 2) * 4) + 4;
                $header .= pack('ii', $offset, $len);
                $offset += $len;

                // start/length of parts
                foreach ($this->_parts as $bp) {
                    if (is_resource($bp)) {
                        rewind($bp);
                        $stat = fstat($bp);
                        $len = $stat['size'];
                    } else {
                        $len = strlen(bin2hex($bp)) / 2;
                    }
                    $header .= pack('ii', $offset, $len);
                    $offset += $len;
                }

                // Output
                $this->_tempStream->add($header);
                $this->_stream->rewind();
                $this->_tempStream->add($this->_stream);
                foreach ($this->_parts as $bp) {
                    if (is_resource($bp)) {
                        rewind($bp);
                        $this->_tempStream->add($bp);
                        fclose($bp);
                    } else {
                        $this->_tempStream->add($bp);
                    }
                }
                $this->_stream->close();
                $this->_stream = $this->_tempStream;
            }
        }
    }

    /**
     * Output the tag content
     *
     * @param mixed $content  The value to output for this tag. A string or
     *                        a stream resource.
     */
    public function content($content, $opaque = false)
    {
        if (!$opaque) {
            // Don't try to send a string containing \0 - it's the wbxml string
            // terminator.
            if (!is_resource($content)) {
                $content = str_replace("\0", '', $content);
                if ('x' . $content == 'x') {
                    return;
                }
            }
        }

        $this->_outputStack();


        $this->_content($content, $opaque);

        if (is_resource($content)) {
            fclose($content);
            $content = null;
        }
    }

    /**
     * Add a mulitpart part to be output.
     *
     * @param mixed $data  The part data. A string or stream resource.
     */
    public function addPart($data)
    {
        $this->_parts[] = $data;
    }

    /**
     * Return the parts array.
     *
     * @return array
     */
    public function getParts()
    {
        return $this->_parts;
    }

    /**
     * Replace the WBXML output stream.
     *
     * Used to buffer Sync Commands output so MOREAVAILABLE can be inserted
     * before Commands when a time budget stops the send loop early.
     *
     * @param Horde_Stream|resource $stream  New output stream.
     *
     * @return Horde_Stream|resource  Previous output stream.
     */
    public function swapOutputStream($stream)
    {
        if (is_resource($stream)) {
            $stream = new Horde_Stream_Existing(['stream' => $stream]);
        } elseif (!$stream instanceof Horde_Stream) {
            throw new InvalidArgumentException('swapOutputStream() expects a Horde_Stream or stream resource.');
        }

        $previous = $this->_stream;
        $this->_stream = $stream;
        return $previous;
    }

    /**
     * Flush pending output to the underlying stream and, when writing to the
     * SAPI output stream, on to the client.
     *
     * Used by streaming Sync responses so each exported message reaches
     * clients with hard read timeouts (e.g. Gmail Android at ~30 seconds)
     * while the remaining batch is still being assembled. A no-op in effect
     * when the output stream is a memory or temp stream (tests, buffers).
     */
    public function flushOutput()
    {
        if (isset($this->_stream->stream)
            && is_resource($this->_stream->stream)) {
            fflush($this->_stream->stream);
        }
        flush();
    }

    /**
     * Emit a WBXML keep-alive no-op and flush it to the client.
     *
     * Writes a SWITCH_PAGE token targeting the code page that is already
     * active. Token-stream WBXML parsers (including this package's own
     * decoder) process it without any semantic effect. Streaming Sync
     * responses use this to keep response body bytes flowing while
     * long-running server work is in progress and no protocol content is
     * available yet - e.g. while importing client-sent changes, which can
     * take far longer than the hard ~30 second read timeout of some clients
     * (Gmail Android).
     *
     * @see doc/sync-streaming.md
     */
    public function keepAlive()
    {
        $this->outputWbxmlHeader();
        $this->_stream->add(chr(self::SWITCH_PAGE));
        $this->_stream->add(chr($this->_tagcp));
        $this->flushOutput();
    }

    /**
     * Append a buffered stream to the current output.
     *
     * @param Horde_Stream|resource $stream  Stream to append.
     */
    public function appendOutputStream($stream)
    {
        if ($stream instanceof Horde_Stream) {
            $stream->rewind();
        } elseif (is_resource($stream)) {
            rewind($stream);
        } else {
            throw new InvalidArgumentException('appendOutputStream() expects a Horde_Stream or stream resource.');
        }

        $this->_stream->add($stream);
    }

    /**
     * Output any tags on the stack that haven't been output yet
     *
     */
    private function _outputStack()
    {
        for ($i = 0; $i < count($this->_stack); $i++) {
            if (!$this->_stack[$i]['sent']) {
                $this->_startTag(
                    $this->_stack[$i]['tag'],
                    $this->_stack[$i]['attributes']
                );
                $this->_stack[$i]['sent'] = true;
            }
        }
    }

    /**
     * Actually outputs the start tag
     *
     * @param string $tag @see Horde_ActiveSync_Wbxml_Encoder::startTag
     * @param mixed $attributes @see Horde_ActiveSync_Wbxml_Encoder::startTag
     * @param boolean $output_empty @see Horde_ActiveSync_Wbxml_Encoder::startTag
     */
    private function _startTag($tag, $attributes = false, $output_empty = false)
    {
        $this->_logStartTag($tag, $attributes, $output_empty);
        $mapping = $this->_getMapping($tag);
        if (!$mapping) {
            return false;
        }

        /* Make sure we don't need to switch code pages */
        if ($this->_tagcp != $mapping['cp']) {
            $this->_outSwitchPage($mapping['cp']);
            $this->_tagcp = $mapping['cp'];
        }

        /* Build and send the code */
        $code = $mapping['code'];
        if (isset($attributes) && is_array($attributes) && count($attributes) > 0) {
            $code |= 0x80;
        } elseif (!$output_empty) {
            $code |= 0x40;
        }
        $this->_outByte($code);
        if ($code & 0x80) {
            $this->_outAttributes($attributes);
        }
    }

    /**
     * Outputs data
     *
     * @param mixed $content  A string or stream resource to write to the output
     */
    private function _content($content, $opaque = false)
    {
        if (!is_resource($content)) {
            if ($this->_logLevel == self::LOG_PROTOCOL
                && ($l = HordeString::length($content)) > self::LOG_MAXCONTENT) {
                $this->_logContent(sprintf('[%d bytes of content]', $l));
            } else {
                $this->_logContent($content);
            }
        } else {
            if ($this->_logLevel == self::LOG_DETAILED) {
                rewind($content);
                $this->_logContent(stream_get_contents($content));
                rewind($content);
            } else {
                $this->_logContent('[STREAM]');
            }
        }
        if ($opaque) {
            if (!is_resource($content)) {
                $len = strlen($content);
            } else {
                $len = 0;
            }
            $this->_outByte(self::OPAQUE);
            $this->_outMBUInt($len);
            //$content = base64_encode($content);
            $this->_stream->add($content);
            return;
        }
        $this->_outByte(self::STR_I);
        $this->_outTermStr($content);
    }

    /**
     * Output the endtag
     *
     */
    private function _endTag()
    {
        $this->_logEndTag();
        $this->_outByte(self::END);
    }

    /**
     * Output a single byte to the stream
     *
     * @param byte $byte  The byte to output.
     */
    private function _outByte($byte)
    {
        $this->_stream->add(chr($byte));
    }

    /**
     * Outputs an MBUInt to the stream
     *
     * @param $uint  The data to write.
     */
    private function _outMBUInt($uint)
    {
        while (1) {
            $byte = $uint & 0x7f;
            $uint = $uint >> 7;
            if ($uint == 0) {
                $this->_outByte($byte);
                break;
            } else {
                $this->_outByte($byte | 0x80);
            }
        }
    }

    /**
     * Output a string along with the terminator.
     *
     * @param mixed $content  A string or a stream resource.
     */
    private function _outTermStr($content)
    {
        if (is_resource($content)) {
            rewind($content);
        }
        $this->_stream->add($content);
        $this->_stream->add(chr(0));
    }

    /**
     * Output attributes
     */
    private function _outAttributes()
    {
        // We don't actually support this, because to do so, we would have
        // to build a string table before sending the data (but we can't
        // because we're streaming), so we'll just send an END, which just
        // terminates the attribute list with 0 attributes.
        $this->_outByte(self::END);
    }

    /**
     * Switch code page.
     *
     * @param integer $page  The code page to switch to.
     */
    private function _outSwitchPage($page)
    {
        $this->_outByte(self::SWITCH_PAGE);
        $this->_outByte($page);
    }

    /**
     * Obtain the wbxml mapping for the given tag
     *
     * @param string $tag
     *
     * @return array
     */
    private function _getMapping($tag)
    {
        $mapping = [];
        $split = $this->_splitTag($tag);
        if (isset($split['ns'])) {
            $cp = $this->_dtd['namespaces'][$split['ns']];
        } else {
            $cp = 0;
        }

        $code = $this->_dtd['codes'][$cp][$split['tag']];
        $mapping['cp'] = $cp;
        $mapping['code'] = $code;

        return $mapping;
    }

    /**
     * Split a tag into it's atomic parts
     *
     * @param string $fulltag  The full tag name
     *                         (e.g. POOMCONTACTS:Email1Address)
     *
     * @return array  An array containing the namespace and tagname
     */
    private function _splitTag($fulltag)
    {
        $ns = false;
        $pos = strpos($fulltag, chr(58)); // chr(58) == ':'
        if ($pos) {
            $ns = substr($fulltag, 0, $pos);
            $tag = substr($fulltag, $pos + 1);
        } else {
            $tag = $fulltag;
        }

        $ret = [];
        if ($ns) {
            $ret['ns'] = $ns;
        }
        $ret['tag'] = $tag;

        return $ret;
    }

    /**
     * Log the start tag output
     *
     * @param string $tag
     * @param mixed $attr
     * @param boolean $output_empty
     *
     * @return void
     */
    private function _logStartTag($tag, $attr, $output_empty)
    {
        $indent = count($this->_logStack);
        if ($output_empty) {
            $this->_logger->server(sprintf('<%s />', $tag), $indent);
        } else {
            $this->_logStack[] = $tag;
            $this->_logger->server(sprintf('<%s>', $tag), $indent);
        }
    }

    /**
     * Log the endtag output
     *
     * @return void
     */
    private function _logEndTag()
    {
        $indent = count($this->_logStack) - 1;
        $tag = array_pop($this->_logStack);
        $this->_logger->server(sprintf('</%s>', $tag), $indent);
    }

    /**
     * Log the content output
     *
     * @param string $content  The output
     *
     * @return void
     */
    private function _logContent($content)
    {
        $indent = count($this->_logStack);
        $this->_logger->server($content, $indent);
    }

}
