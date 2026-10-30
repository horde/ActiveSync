<?php

use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Component\AbstractComponent;
use Horde\Icalendar\Enum\CalendarMethod;
use Horde\Icalendar\IcalendarException;
use Horde\Util\HordeString;

/**
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */

/**
 * Base class for building and populating the various body related properties
 * of a Horde_ActiveSync_Message_Mail object.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Imap_EasMessageBuilder
{
    /**
     * @var Horde_ActiveSync_Imap_MessageBodyData
     */
    protected $_mbd;

    /**
     * @var Horde_ActiveSync_Imap_Message
     */
    protected $_imapMessage;

    /**
     * @var  Horde_ActiveSync_Message_Base
     */
    protected $_easMessage;

    /**
     * @var  Horde_ActiveSync_Message_AirSyncBaseBody
     */
    protected $_airsyncBody;

    /**
     * @var array
     */
    protected $_options;

    /**
     * @var string
     */
    protected $_version;

    /**
     * Logger
     *
     * @var Horde_Log_Logger
     */
    protected $_logger;

    /**
     * Process Id
     *
     * @var  integer
     */
    protected $_procid;

    /**
     *
     * @param Horde_ActiveSync_Imap_Message $imap_message  The IMAP message object.
     * @param array                         $options       Options array.
     * @param Horde_Log_Logger $logger                     The logger.
     */
    public function __construct(
        Horde_ActiveSync_Imap_Message $imap_message,
        array $options,
        $logger
    ) {
        $this->_imapMessage = $imap_message;
        $this->_mbd = $this->_imapMessage->getMessageBodyDataObject($options);
        $this->_easMessage = Horde_ActiveSync::messageFactory('Mail');

        $this->_version = empty($options['protocolversion'])
            ? Horde_ActiveSync::VERSION_TWOFIVE
            : $options['protocolversion'];

        $this->_airsyncBody = Horde_ActiveSync::messageFactory('AirSyncBaseBody');
        $this->_logger = Horde_ActiveSync::_wrapLogger($logger);
        $this->_procid = getmypid();

        if ($this->_version > Horde_ActiveSync::VERSION_TWOFIVE) {
            $this->_easMessage->airsyncbasenativebodytype = $this->_mbd->nativeBodyType;
        }
    }

    /**
     * Return a Horde_ActiveSync_Message_Mail object with the appropriate body
     * related properties populated.
     *
     * @param  $params  Paramater array:
     *   -flags: An array representing the message's flags.
     *
     * @return Horde_ActiveSync_Message_Base
     */
    public function getMessageObject($params = [])
    {
        // Perform the bulk of the work.
        $this->_populateObject();

        // Set message flags if needed.
        if ($this->_version > Horde_ActiveSync::VERSION_TWELVEONE && !empty($params['flags'])) {
            $this->_setFlags($params['flags']);
        }

        // Build the message body contents.
        $this->_buildBody();

        // It's legal to have both a BODY and a BODYPART
        if ($this->_version > Horde_ActiveSync::VERSION_FOURTEEN
            && !empty($options['bodypartprefs'])) {
            $this->_easMessage->airsyncbasebodypart = $this->_buildBodyPart();
        }

        // Body Preview. Note that this is different than the BodyPart preview.
        if ($this->_version >= Horde_ActiveSync::VERSION_FOURTEEN
            && !empty($this->_options['bodyprefs']['preview'])) {
            $this->_mbd->plain['body']->rewind();
            $this->_easMessage->airsyncbasebody->preview
                = $this->_mbd->plain['body']->substring(0, $this->_options['bodyprefs']['preview']);
        }

        return $this->_easMessage;
    }

    /**
     * Populate the EAS message object.
     *
     */
    protected function _populateObject()
    {
        // Set basic header properties.
        $this->_setHeaderProperties();

        // Set the read flag
        $this->_easMessage->read = $this->_imapMessage->getFlag(Horde_Imap_Client::FLAG_SEEN);

        // Default to IPM.Note - may change below depending on message content.
        $this->_easMessage->messageclass = 'IPM.Note';

        // Codepage id. MS recommends to always set to UTF-8 when possible.
        // See http://msdn.microsoft.com/en-us/library/windows/desktop/dd317756%28v=vs.85%29.aspx
        $this->_easMessage->cpid = Horde_ActiveSync_Message_Mail::INTERNET_CPID_UTF8;

        // Check X-Priority or Importance
        $this->_messageImportance();

        // Check for any special message types.
        $this->_specialTypes();

        // Finally, some additional properties if using >= 14.0
        if ($this->_version >= Horde_ActiveSync::VERSION_FOURTEEN) {
            $this->_easMessage->messageid = $this->_imapMessage->getHeaders()->getValue('Message-ID');
            $this->_easMessage->forwarded = $this->_imapMessage->getFlag(Horde_Imap_Client::FLAG_FORWARDED);
            $this->_easMessage->answered  = $this->_imapMessage->getFlag(Horde_Imap_Client::FLAG_ANSWERED);
        }
    }

    /**
     * Sets general email header properties:
     * To:, From:, Cc:, Reply-To:, Subject:, Threadtopic:, Date:
     */
    protected function _setHeaderProperties()
    {
        // To: (POOMMAIL_TO has a max length of 32768).
        $to = $this->_imapMessage->getToAddresses();
        $this->_easMessage->to = array_pop($to['to']);
        foreach ($to['to'] as $to_atom) {
            if (strlen($this->_easMessage->to) + strlen($to_atom) > 32768) {
                break;
            }
            $this->_easMessage->to .= ',' . $to_atom;
        }
        $this->_easMessage->displayto = implode(';', $to['displayto']);
        if (empty($this->_easMessage->displayto)) {
            $this->_easMessage->displayto = $this->_easMessage->to;
        }

        // From:
        try {
            $this->_easMessage->from = Horde_ActiveSync_Utils::ensureUtf8(
                $this->_imapMessage->getFromAddress(),
                'UTF-8'
            );
        } catch (Horde_ActiveSync_Exception $e) {
            $this->_logger->err($e->getMessage());
        }

        // CC:
        try {
            $this->_easMessage->cc = Horde_ActiveSync_Utils::ensureUtf8(
                $this->_imapMessage->getCc(),
                'UTF-8'
            );
        } catch (Horde_ActiveSync_Exception $e) {
            $this->_logger->err($e->getMessage());
        }

        // Reply-To:
        try {
            $this->_easMessage->reply_to = Horde_ActiveSync_Utils::ensureUtf8(
                $this->_imapMessage->getReplyTo(),
                'UTF-8'
            );
        } catch (Horde_ActiveSync_Exception $e) {
            $this->_logger->err($e->getMessage());
        }

        // Subject:
        $this->_easMessage->subject = Horde_ActiveSync_Utils::ensureUtf8(
            $this->_imapMessage->getSubject(),
            'UTF-8'
        );

        $this->_easMessage->threadtopic = $this->_easMessage->subject;
        $this->_easMessage->datereceived = $this->_imapMessage->getDate();
    }

    /**
     * Set IMAP message flags (EAS categories).
     */
    protected function _setFlags($msgFlags)
    {
        // Flags
        $flags = [];
        foreach ($this->_imapMessage->getFlags() as $flag) {
            if (!empty($msgFlags[HordeString::lower($flag)])) {
                $flags[] = $msgFlags[HordeString::lower($flag)];
            }
        }
        $this->_easMessage->categories = $flags;
    }

    /**
     * Check for Disposition-Notification and deliver-status reports.
     */
    protected function _deliveryNotification()
    {
        $part = $this->_imapMessage->getStructure();
        if ($part->getType() != 'multipart/report') {
            return;
        }

        $ids = array_keys($this->_imapMessage->contentTypeMap());
        reset($ids);
        $part1_id = next($ids);
        $part2_id = Horde_Mime::mimeIdArithmetic($part1_id, 'next');
        try {
            $lines = explode(chr(13), $this->_imapMessage->getBodyPart($part2_id, ['decode' => true]));
        } catch (Horde_ActiveSync_Exception $e) {
            return;
        }

        switch ($part->getContentTypeParameter('report-type')) {
            case 'delivery-status':
                foreach ($lines as $line) {
                    if (strpos(trim($line), 'Action:') === 0) {
                        switch (trim(substr(trim($line), 7))) {
                            case 'failed':
                                $this->_easMessage->messageclass = 'REPORT.IPM.NOTE.NDR';
                                break 2;
                            case 'delayed':
                                $this->_easMessage->messageclass = 'REPORT.IPM.NOTE.DELAYED';
                                break 2;
                            case 'delivered':
                                $this->_easMessage->messageclass = 'REPORT.IPM.NOTE.DR';
                                break 2;
                        }
                    }
                }
                break;
            case 'disposition-notification':
                foreach ($lines as $line) {
                    if (strpos(trim($line), 'Disposition:') === 0) {
                        if (strpos($line, 'displayed') !== false) {
                            $this->_easMessage->messageclass = 'REPORT.IPM.NOTE.IPNRN';
                        } elseif (strpos($line, 'deleted') !== false) {
                            $this->_easMessage->messageclass = 'REPORT.IPM.NOTE.IPNNRN';
                        }
                        break;
                    }
                }
        }
    }

    /**
     * Check for meeting requests/responses.
     */
    protected function _meetingRequest()
    {
        // Exit if we don't support or don't have an iTip.
        if ($this->_version < Horde_ActiveSync::VERSION_TWELVE
            || !($mime_part = $this->_imapMessage->hasiCalendar())) {
            return;
        }

        // Get the iTip data.
        $data = Horde_ActiveSync_Utils::ensureUtf8(
            $mime_part->getContents(),
            $mime_part->getCharset()
        );

        // Parse the iTip.
        try {
            $calendar = AbstractComponent::fromString($data);
        } catch (IcalendarException $e) {
            $this->_logger->err($e->getMessage());
            return;
        }

        // Exit if we can't parse/find any data.
        if (!($calendar instanceof VCalendar) || $calendar->getEvents() === []) {
            return;
        }

        // A sender that repeats METHOD must not break the sync; the first
        // value counts. Many senders omit METHOD on text/calendar parts;
        // treat as REQUEST (Bug #12083).
        if ($calendar->getProperties()->count('METHOD') > 1) {
            $this->_logger->debug('The iTip has more than one METHOD property, using the first.');
        }
        $method = $calendar->getMethod() ?? CalendarMethod::from('REQUEST');

        $messageClass = null;
        $hasMeetingData = true;
        try {
            switch ($method->value) {
                case 'REQUEST':
                case 'PUBLISH':
                    $messageClass = 'IPM.Schedule.Meeting.Request';
                    break;
                case 'REPLY':
                    switch ($this->_getiTipStatus($calendar)) {
                        case 'ACCEPTED':
                            $messageClass = 'IPM.Schedule.Meeting.Resp.Pos';
                            break;
                        case 'DECLINED':
                            $messageClass = 'IPM.Schedule.Meeting.Resp.Neg';
                            break;
                        case 'TENTATIVE':
                            $messageClass = 'IPM.Schedule.Meeting.Resp.Tent';
                    }
                    break;
                case 'CANCEL':
                    $messageClass = Horde_ActiveSync_Message_Mail::CLASS_MEETING_CANCELED;
                    break;
                default:
                    $hasMeetingData = false;
            }

            if ($hasMeetingData) {
                $mtg = Horde_ActiveSync::messageFactory('MeetingRequest');
                $mtg->fromCalendar($calendar, $method);
                $this->_easMessage->meetingrequest = $mtg;
            }
        } catch (Horde_ActiveSync_Exception $e) {
            // The mail is still delivered, just without the meeting data.
            $this->_logger->err($e->getMessage());
            return;
        }

        $this->_easMessage->contentclass = 'urn:content-classes:calendarmessage';
        if ($messageClass !== null) {
            $this->_easMessage->messageclass = $messageClass;
        }
    }

    /**
     * Handle POOMMAIL_FLAGGED data.
     */
    protected function _poomMailFlagged()
    {
        if (!$this->_imapMessage->getFlag(Horde_Imap_Client::FLAG_FLAGGED)) {
            return;
        }
        $poommail_flag = Horde_ActiveSync::messageFactory('Flag');
        $poommail_flag->subject = $this->_imapMessage->getSubject();
        $poommail_flag->flagstatus = Horde_ActiveSync_Message_Flag::FLAG_STATUS_ACTIVE;
        $poommail_flag->flagtype = Horde_Imap_Client::FLAG_FLAGGED;
        $this->_easMessage->flag = $poommail_flag;
    }

    /**
     * Check for and handle special message types.
     * Signed, Encrypted, Disposition, MeetingRequest, Flagged.
     */
    protected function _specialTypes()
    {
        // Default to message.
        if ($this->_version >= Horde_ActiveSync::VERSION_TWELVE) {
            $this->_easMessage->contentclass = 'urn:content-classes:message';
        }

        // Signed/Encrypted?
        $this->_signedEncrypted();

        // Delivery Report?
        $this->_deliveryNotification();

        // Meeting request?
        $this->_meetingRequest();

        // Flagged?
        $this->_poomMailFlagged();
    }

    /**
     * Handle signed/encrypted messageclass.
     */
    protected function _signedEncrypted()
    {
        // Signed or encrypted?
        if ($this->_imapMessage->isEncrypted()) {
            $this->_easMessage->messageclass = 'IPM.Note.SMIME';
        } elseif ($this->_imapMessage->isSigned()) {
            $this->_easMessage->messageclass = 'IPM.Note.SMIME.MultipartSigned';
        }
    }

    /**
     * Set any importance data.
     */
    protected function _messageImportance()
    {
        // Message importance. First try X-Priority, then Importance since
        // Outlook sends the later.
        if ($priority = $this->_imapMessage->getHeaders()->getValue('X-priority')) {
            $priority = preg_replace('/\D+/', '', $priority);
        } else {
            $priority = $this->_imapMessage->getHeaders()->getValue('Importance');
        }
        $this->_easMessage->importance = $this->_getEASImportance($priority);
    }

    /**
     * Map Importance header values to EAS importance values.
     *
     * @param string $importance  The importance [high|normal|low].
     *
     * @return integer  The EAS importance value [0|1|2].
     */
    protected function _getEASImportance($importance)
    {
        switch (HordeString::lower($importance)) {
            case '1':
            case 'high':
                return 2;
            case '5':
            case 'low':
                return 0;
            case 'normal':
            case '3':
            default:
                return 1;
        }
    }

    /**
     * Return the attendee participation status.
     *
     * @param VCalendar $calendar  The iTip.
     *
     * @return string  The upper-cased PARTSTAT of the first attendee, or
     *                 NEEDS-ACTION if it has none (RFC 5545 default).
     *
     * @throws Horde_ActiveSync_Exception
     */
    protected function _getiTipStatus(VCalendar $calendar)
    {
        foreach ($calendar->getEvents() as $event) {
            $attendees = $event->getAttendees();
            if ($attendees === []) {
                throw new Horde_ActiveSync_Exception('The iTip reply has no ATTENDEE.');
            }

            return $attendees[0]->getParticipationStatus()->value;
        }

        throw new Horde_ActiveSync_Exception('The iTip reply has no event.');
    }

    /**
     * Build the BodyPart data.
     *
     * @return  Horde_ActiveSync_Message_AirSyncBaseBodypart
     */
    protected function _buildBodyPart()
    {
        $this->_logger->meta('Preparing BODYPART data.');

        $message = Horde_ActiveSync::messageFactory('AirSyncBaseBodypart');
        $message->status = Horde_ActiveSync_Message_AirSyncBaseBodypart::STATUS_SUCCESS;
        if (!empty($this->_options['bodypartprefs']['preview']) && $this->_mbd->plain) {
            $this->_mbd->plain['body']->rewind();
            $message->preview = $this->_mbd->plain['body']->substring(0, $this->_options['bodypartprefs']['preview']);
        }
        $message->data = $this->_mbd->bodyPart['body']->stream;
        $message->truncated = $this->_mbd->bodyPart['truncated'];

        return $message;
    }

    /**
     * Simple factory for creating the correct
     * Horde_ActiveSync_Imap_EasMessageType object.
     *
     * @param  Horde_ActiveSync_Imap_Message $imap_message
     * @param  array                         $options
     * @param  $logger  The logger.
     *
     * @return Horde_ActiveSync_Imap_EasMessageType
     */
    public static function create(
        Horde_ActiveSync_Imap_Message $imap_message,
        array $options,
        $logger
    ) {
        $mbd = $imap_message->getMessageBodyDataObject($options);

        // First, see if we are EAS 2.5
        if ($options['protocolversion'] == Horde_ActiveSync::VERSION_TWOFIVE) {
            return new Horde_ActiveSync_Imap_EasMessageBuilder_TwoFive($imap_message, $options, $logger);
        }

        switch ($mbd->getBodyTypePreference()) {
            case Horde_ActiveSync::BODYPREF_TYPE_MIME:
                $class = 'Mime';
                break;
            case Horde_ActiveSync::BODYPREF_TYPE_HTML:
                $class = 'Html';
                break;
            case Horde_ActiveSync::BODYPREF_TYPE_PLAIN:
                $class = 'Plain';
        }
        $class_name = 'Horde_ActiveSync_Imap_EasMessageBuilder_' . $class;

        return new $class_name($imap_message, $options, $logger);
    }
}
