<?php

/**
 * Horde_ActiveSync_Message_Mail::
 *
 * Portions of this class were ported from the Z-Push project:
 *   File      :   wbxml.php
 *   Project   :   Z-Push
 *   Descr     :   WBXML mapping file
 *
 *   Created   :   01.10.2007
 *
 *   � Zarafa Deutschland GmbH, www.zarafaserver.de
 *   This file is distributed under GPL-2.0.
 *   Consult LICENSE file for details
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2011-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Horde_ActiveSync_Message_Mail::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2011-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property string         $to
 * @property string         $cc
 * @property string         $from
 * @property string         $subject
 * @property string         $threadtopic
 * @property Horde_Date     $datereceived
 * @property string         $displayto
 * @property integer        $importance
 * @property integer        $mimetruncated
 * @property string         $mimedata
 * @property integer        $mimesize
 * @property integer        $messageclass
 * @property Horde_ActiveSync_Message_MeetingRequest
 *                          $meetingrequest
 * @property string         $reply_to
 * @property integer        $read
 * @property cpid           $integer  The codepage id.
 * @property Horde_ActiveSync_Message_Attachments
 *                          $attachments (EAS 2.5 only).
 * @property integer        $bodytruncated (EAS 2.5 only)
 * @property integer        $bodysize (EAS 2.5 only)
 * @property stream|string  $body (EAS 2.5 only)
 * @property integer        $airsyncbasenativebodytype (EAS > 2.5 only).
 * @property Horde_ActiveSync_Message_AirSyncBaseBody
 *                          $airsyncbasebody (EAS > 2.5 only).
 * @property Horde_ActiveSync_Message_AirSyncBaseAttachments
 *                          $airsyncbaseattachments (EAS > 2.5 only).
 * @property integer        $contentclass (EAS > 2.5 only).
 * @property Horde_ActiveSync_Message_Flag
 *                          $flag (EAS > 2.5 only).
 * @property boolean        $isdraft (EAS 16.0 only).
 * @property string         $bcc  The bcc recipients (EAS 16.0 only).
 * @property boolean        $send (EAS 16.0 only).
 *
 * // Internal properties. Not streamed to device.
 * @property string         $messageid @since 2.4.0
 * @property boolean        $answered @since 2.4.0
 * @property boolean        $forwarded @since 2.4.0
 */
class Horde_ActiveSync_Message_Mail extends Horde_ActiveSync_Message_Base
{
    public const POOMMAIL_ATTACHMENT              = 'POOMMAIL:Attachment';
    public const POOMMAIL_ATTACHMENTS             = 'POOMMAIL:Attachments';
    public const POOMMAIL_BODY                    = 'POOMMAIL:Body';
    public const POOMMAIL_BODYSIZE                = 'POOMMAIL:BodySize';
    public const POOMMAIL_BODYTRUNCATED           = 'POOMMAIL:BodyTruncated';
    public const POOMMAIL_DATERECEIVED            = 'POOMMAIL:DateReceived';
    public const POOMMAIL_DISPLAYTO               = 'POOMMAIL:DisplayTo';
    public const POOMMAIL_IMPORTANCE              = 'POOMMAIL:Importance';
    public const POOMMAIL_MESSAGECLASS            = 'POOMMAIL:MessageClass';
    public const POOMMAIL_SUBJECT                 = 'POOMMAIL:Subject';
    public const POOMMAIL_READ                    = 'POOMMAIL:Read';
    public const POOMMAIL_TO                      = 'POOMMAIL:To';
    public const POOMMAIL_CC                      = 'POOMMAIL:Cc';
    public const POOMMAIL_FROM                    = 'POOMMAIL:From';
    public const POOMMAIL_REPLY_TO                = 'POOMMAIL:Reply-To';
    public const POOMMAIL_ALLDAYEVENT             = 'POOMMAIL:AllDayEvent';
    public const POOMMAIL_CATEGORIES              = 'POOMMAIL:Categories';
    public const POOMMAIL_CATEGORY                = 'POOMMAIL:Category';
    public const POOMMAIL_DTSTAMP                 = 'POOMMAIL:DtStamp';
    public const POOMMAIL_ENDTIME                 = 'POOMMAIL:EndTime';
    public const POOMMAIL_INSTANCETYPE            = 'POOMMAIL:InstanceType';
    public const POOMMAIL_BUSYSTATUS              = 'POOMMAIL:BusyStatus';
    public const POOMMAIL_LOCATION                = 'POOMMAIL:Location';
    public const POOMMAIL_MEETINGREQUEST          = 'POOMMAIL:MeetingRequest';
    public const POOMMAIL_ORGANIZER               = 'POOMMAIL:Organizer';
    public const POOMMAIL_RECURRENCEID            = 'POOMMAIL:RecurrenceId';
    public const POOMMAIL_REMINDER                = 'POOMMAIL:Reminder';
    public const POOMMAIL_RESPONSEREQUESTED       = 'POOMMAIL:ResponseRequested';
    public const POOMMAIL_RECURRENCES             = 'POOMMAIL:Recurrences';
    public const POOMMAIL_RECURRENCE              = 'POOMMAIL:Recurrence';
    public const POOMMAIL_TYPE                    = 'POOMMAIL:Type';
    public const POOMMAIL_UNTIL                   = 'POOMMAIL:Until';
    public const POOMMAIL_OCCURRENCES             = 'POOMMAIL:Occurrences';
    public const POOMMAIL_INTERVAL                = 'POOMMAIL:Interval';
    public const POOMMAIL_DAYOFWEEK               = 'POOMMAIL:DayOfWeek';
    public const POOMMAIL_DAYOFMONTH              = 'POOMMAIL:DayOfMonth';
    public const POOMMAIL_WEEKOFMONTH             = 'POOMMAIL:WeekOfMonth';
    public const POOMMAIL_MONTHOFYEAR             = 'POOMMAIL:MonthOfYear';
    public const POOMMAIL_STARTTIME               = 'POOMMAIL:StartTime';
    public const POOMMAIL_SENSITIVITY             = 'POOMMAIL:Sensitivity';
    public const POOMMAIL_TIMEZONE                = 'POOMMAIL:TimeZone';
    public const POOMMAIL_GLOBALOBJID             = 'POOMMAIL:GlobalObjId';
    public const POOMMAIL_THREADTOPIC             = 'POOMMAIL:ThreadTopic';
    public const POOMMAIL_MIMEDATA                = 'POOMMAIL:MIMEData';
    public const POOMMAIL_MIMETRUNCATED           = 'POOMMAIL:MIMETruncated';
    public const POOMMAIL_MIMESIZE                = 'POOMMAIL:MIMESize';
    public const POOMMAIL_INTERNETCPID            = 'POOMMAIL:InternetCPID';

    // EAS 12.0
    public const POOMMAIL_CONTENTCLASS            = 'POOMMAIL:ContentClass';
    public const POOMMAIL_FLAG                    = 'POOMMAIL:Flag';

    // EAS 14.0
    public const POOMMAIL_COMPLETETIME            = 'POOMMAIL:CompleteTime';
    public const POOMMAIL_DISALLOWNEWTIMEPROPOSAL = 'POOMMAIL:DisallowNewTimeProposal';

    // EAS 14 POOMMAIL2
    public const POOMMAIL2_UMCALLERID             = 'POOMMAIL2:UmCallerId';
    public const POOMMAIL2_UMUSERNOTES            = 'POOMMAIL2:UmUserNotes';
    public const POOMMAIL2_UMATTDURATION          = 'POOMMAIL2:UmAttDuration';
    public const POOMMAIL2_UMATTORDER             = 'POOMMAIL2:UmAttOrder';
    public const POOMMAIL2_CONVERSATIONID         = 'POOMMAIL2:ConversationId';
    public const POOMMAIL2_CONVERSATIONINDEX      = 'POOMMAIL2:ConversationIndex';
    public const POOMMAIL2_LASTVERBEXECUTED       = 'POOMMAIL2:LastVerbExecuted';
    public const POOMMAIL2_LASTVERBEXECUTIONTIME  = 'POOMMAIL2:LastVerbExecutionTime';
    public const POOMMAIL2_RECEIVEDASBCC          = 'POOMMAIL2:ReceivedAsBcc';
    public const POOMMAIL2_SENDER                 = 'POOMMAIL2:Sender';
    public const POOMMAIL2_CALENDARTYPE           = 'POOMMAIL2:CalendarType';
    public const POOMMAIL2_ISLEAPMONTH            = 'POOMMAIL2:IsLeapMonth';
    public const POOMMAIL2_ACCOUNTID              = 'POOMMAIL2:AccountId';
    public const POOMMAIL2_FIRSTDAYOFWEEK         = 'POOMMAIL2:FirstDayOfWeek';

    // EAS 14.1
    public const POOMMAIL2_MEETINGMESSAGETYPE     = 'POOMMAIL2:MeetingMessageType';

    // EAS 16.0
    public const POOMMAIL2_ISDRAFT                = 'POOMMAIL2:IsDraft';
    public const POOMMAIL2_BCC                    = 'POOMMAIL2:Bcc';
    public const POOMMAIL2_SEND                   = 'POOMMAIL2:Send';

    /* Mail message types */
    public const CLASS_NOTE                       = 'IPM.Note';
    public const CLASS_MEETING_REQUEST            = 'IPM.Schedule.Meeting.Request';
    public const CLASS_MEETING_NOTICE             = 'IPM.Notification.Meeting';

    /* Flags */
    public const FLAG_READ_UNSEEN   = 0;
    public const FLAG_READ_SEEN     = 1;

    /* UTF-8 codepage id. */
    public const INTERNET_CPID_UTF8 = 65001;

    /* Importance */
    public const IMPORTANCE_LOW     = 0;
    public const IMPORTANCE_NORM    = 1;
    public const IMPORTANCE_HIGH    = 2;

    /* Verbs */
    public const VERB_NONE          = 0;
    public const VERB_REPLY_SENDER  = 1;
    public const VERB_REPLY_ALL     = 2;
    public const VERB_FORWARD       = 3;

    /**
     * Property mappings
     *
     * @var array
     */
    protected $_mapping = [
        self::POOMMAIL_TO             => [self::KEY_ATTRIBUTE => 'to'],
        self::POOMMAIL_CC             => [self::KEY_ATTRIBUTE => 'cc'],
        self::POOMMAIL_FROM           => [self::KEY_ATTRIBUTE => 'from'],
        self::POOMMAIL_SUBJECT        => [self::KEY_ATTRIBUTE => 'subject'],
        self::POOMMAIL_REPLY_TO       => [self::KEY_ATTRIBUTE => 'reply_to'],
        self::POOMMAIL_DATERECEIVED   => [self::KEY_ATTRIBUTE => 'datereceived', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        self::POOMMAIL_DISPLAYTO      => [self::KEY_ATTRIBUTE => 'displayto'],
        self::POOMMAIL_THREADTOPIC    => [self::KEY_ATTRIBUTE => 'threadtopic'],
        self::POOMMAIL_IMPORTANCE     => [self::KEY_ATTRIBUTE => 'importance'],
        self::POOMMAIL_READ           => [self::KEY_ATTRIBUTE => 'read'],
        self::POOMMAIL_MIMETRUNCATED  => [self::KEY_ATTRIBUTE => 'mimetruncated' ],
        // Not used.
        self::POOMMAIL_MIMEDATA       => [self::KEY_ATTRIBUTE => 'mimedata', self::KEY_TYPE => 'KEY_TYPE_MAPI_STREAM'],
        self::POOMMAIL_MIMESIZE       => [self::KEY_ATTRIBUTE => 'mimesize' ],

        self::POOMMAIL_MESSAGECLASS   => [self::KEY_ATTRIBUTE => 'messageclass'],
        self::POOMMAIL_MEETINGREQUEST => [self::KEY_ATTRIBUTE => 'meetingrequest', self::KEY_TYPE => 'Horde_ActiveSync_Message_MeetingRequest'],
        self::POOMMAIL_INTERNETCPID   => [self::KEY_ATTRIBUTE => 'cpid'],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'to'             => false,
        'cc'             => false,
        'from'           => false,
        'subject'        => false,
        'threadtopic'    => false,
        'datereceived'   => false,
        'displayto'      => false,
        'importance'     => false,
        'mimetruncated'  => false,
        'mimedata'       => false,
        'mimesize'       => false,
        'messageclass'   => false,
        'meetingrequest' => false,
        'reply_to'       => false,
        'read'           => false,
        'cpid'           => false,
    ];

    /**
     * Const'r
     *
     * @see Horde_ActiveSync_Message_Base::__construct()
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        if ($this->_version == Horde_ActiveSync::VERSION_TWOFIVE) {
            $this->_mapping += [
                self::POOMMAIL_ATTACHMENTS    => [self::KEY_ATTRIBUTE => 'attachments', self::KEY_TYPE => 'Horde_ActiveSync_Message_Attachment', self::KEY_VALUES => self::POOMMAIL_ATTACHMENT],
                self::POOMMAIL_BODYTRUNCATED  => [self::KEY_ATTRIBUTE => 'bodytruncated'],
                self::POOMMAIL_BODYSIZE       => [self::KEY_ATTRIBUTE => 'bodysize'],
                self::POOMMAIL_BODY           => [self::KEY_ATTRIBUTE => 'body'],
            ];

            $this->_properties += [
                'attachments'    => false,
                'bodytruncated'  => false,
                'bodysize'       => false,
                'body'           => false,
            ];
        }
        if ($this->_version >= Horde_ActiveSync::VERSION_TWELVE) {
            $this->_mapping += [
                Horde_ActiveSync::AIRSYNCBASE_NATIVEBODYTYPE => [self::KEY_ATTRIBUTE => 'airsyncbasenativebodytype'],
                Horde_ActiveSync::AIRSYNCBASE_BODY           => [self::KEY_ATTRIBUTE => 'airsyncbasebody', self::KEY_TYPE => 'Horde_ActiveSync_Message_AirSyncBaseBody'],
                Horde_ActiveSync::AIRSYNCBASE_ATTACHMENTS    => [
                    self::KEY_ATTRIBUTE => 'airsyncbaseattachments',
                    self::KEY_TYPE => ['Horde_ActiveSync_Message_AirSyncBaseAttachment', 'Horde_ActiveSync_Message_AirSyncBaseAdd', 'Horde_ActiveSync_Message_AirSyncBaseDelete'],
                    self::KEY_VALUES => [Horde_ActiveSync::AIRSYNCBASE_ATTACHMENT, Horde_ActiveSync::AIRSYNCBASE_ADD, Horde_ActiveSync::AIRSYNCBASE_DELETE],
                ],
                self::POOMMAIL_FLAG                          => [self::KEY_ATTRIBUTE => 'flag', self::KEY_TYPE => 'Horde_ActiveSync_Message_Flag'],
                self::POOMMAIL_CONTENTCLASS                  => [self::KEY_ATTRIBUTE => 'contentclass'],
            ];

            $this->_properties += [
                'airsyncbasenativebodytype' => false,
                'airsyncbasebody'           => false,
                'airsyncbaseattachments'    => [],
                'contentclass'              => false,
                'flag'                      => false,
            ];

            // Removed in 16.0
            if ($this->_version <= Horde_ActiveSync::VERSION_FOURTEENONE) {
                $this->_mapping += [
                    self::POOMMAIL_LOCATION => [self::KEY_ATTRIBUTE => 'location'],
                    self::POOMMAIL_GLOBALOBJID => [self::KEY_ATTRIBUTE => 'globalobjid'],
                ];
                $this->_properties += [
                    'location' => false,
                    'globalobjid' => false,
                ];
            }

            if ($this->_version >= Horde_ActiveSync::VERSION_FOURTEEN) {
                $this->_mapping += [
                    self::POOMMAIL_CATEGORIES             => [self::KEY_ATTRIBUTE => 'categories', self::KEY_VALUES => self::POOMMAIL_CATEGORY],
                    self::POOMMAIL_CATEGORY               => [self::KEY_ATTRIBUTE => 'category'],
                    self::POOMMAIL2_UMCALLERID            => [self::KEY_ATTRIBUTE => 'umcallerid'],
                    self::POOMMAIL2_UMUSERNOTES           => [self::KEY_ATTRIBUTE => 'umusernotes'],
                    self::POOMMAIL2_UMATTDURATION         => [self::KEY_ATTRIBUTE => 'umattduration'],
                    self::POOMMAIL2_UMATTORDER            => [self::KEY_ATTRIBUTE => 'umattorder'],
                    self::POOMMAIL2_CONVERSATIONID        => [self::KEY_ATTRIBUTE => 'conversationid'],
                    self::POOMMAIL2_CONVERSATIONINDEX     => [self::KEY_ATTRIBUTE => 'conversationindex'],
                    self::POOMMAIL2_LASTVERBEXECUTED      => [self::KEY_ATTRIBUTE => 'lastverbexecuted'],
                    self::POOMMAIL2_LASTVERBEXECUTIONTIME => [self::KEY_ATTRIBUTE => 'lastverbexecutiontime', self::KEY_TYPE => self::TYPE_DATE_DASHES],
                    self::POOMMAIL2_RECEIVEDASBCC         => [self::KEY_ATTRIBUTE => 'receivedasbcc'],
                    self::POOMMAIL2_SENDER                => [self::KEY_ATTRIBUTE => 'sender'],
                    self::POOMMAIL2_CALENDARTYPE          => [self::KEY_ATTRIBUTE => 'calendartype'],
                    self::POOMMAIL2_ISLEAPMONTH           => [self::KEY_ATTRIBUTE => 'isleapmonth'],
                    self::POOMMAIL2_ACCOUNTID             => [self::KEY_ATTRIBUTE => 'accountid'],
                    self::POOMMAIL2_FIRSTDAYOFWEEK        => [self::KEY_ATTRIBUTE => 'firstdayofweek'],
                ];

                $this->_properties += [
                    'umcallerid'            => false,
                    'umusernotes'           => false,
                    'umattduration'         => false,
                    'umattorder'            => false,
                    'conversationid'        => false,
                    'conversationindex'     => false,
                    'lastverbexecuted'      => false,
                    'lastverbexecutiontime' => false,
                    'receivedasbcc'         => false,
                    'sender'                => false,
                    'calendartype'          => false,
                    'isleapmonth'           => false,
                    'accountid'             => false,
                    'firstdayofweek'        => false,
                    'categories'            => [],

                    // Internal use
                    'messageid'             => false,
                    'answered'              => false,
                    'forwarded'             => false,
                ];
            }

            if ($this->_version > Horde_ActiveSync::VERSION_FOURTEEN) {
                $this->_mapping += [
                    Horde_ActiveSync::AIRSYNCBASE_BODYPART => [self::KEY_ATTRIBUTE => 'airsyncbasebodypart', self::KEY_TYPE => 'Horde_ActiveSync_Message_AirSyncBaseBodypart'],
                ];
                $this->_properties += [
                    'airsyncbasebodypart' => false,
                ];
            }

            if ($this->_version >= Horde_ActiveSync::VERSION_SIXTEEN) {
                $this->_mapping += [
                    self::POOMMAIL2_ISDRAFT                => [self::KEY_ATTRIBUTE => 'isdraft'],
                    self::POOMMAIL2_BCC                    => [self::KEY_ATTRIBUTE => 'bcc'],
                    self::POOMMAIL2_SEND                   => [self::KEY_ATTRIBUTE => 'send'],
                    Horde_ActiveSync::AIRSYNCBASE_LOCATION => [self::KEY_ATTRIBUTE => 'location',
                        Horde_ActiveSync_Message_Appointment::POOMCAL_UID => [self::KEY_ATTRIBUTE => 'uid']],
                ];

                $this->_properties += [
                    'isdraft'  => false,
                    'bcc'      => false,
                    'send'     => false,
                    'location' => false,
                    'uid'      => false,
                ];
            }
        }
    }

    /**
     * Get a Horde_Mime object representint the data contained in this object.
     *
     * [MS_ASEMAIL 3.1.53]
     *
     * @return array An array containing:
     *         - part: Horde_Mime_Part containing the body data NO ATTACHMENTS.
     *         - headers: Horde_Mime_Headers containing the envelope headers.
     */
    public function draftToMime()
    {
        // Main text body.
        $text = new Horde_Mime_Part();
        $body = $this->airsyncbasebody;

        $text->setContents($body->data);
        if ($body->type == Horde_ActiveSync::BODYPREF_TYPE_HTML) {
            $text->setType('text/html');
        } else {
            $text->setType('text/plain');
        }

        // Add headers that are sent with ADD;
        $headers = new Horde_Mime_Headers();
        if ($this->to) {
            $headers->addHeader('To', $this->to);
        }
        if ($this->cc) {
            $headers->addHeader('Cc', $this->cc);
        }
        if ($this->subject) {
            $headers->addHeader('Subject', $this->subject);
        }
        if ($this->bcc) {
            $headers->addHeader('Bcc', $this->bcc);
        }
        if ($this->reply_to) {
            $headers->addHeader('reply-to', $this->reply_to);
        }
        if ($this->importance) {
            $headers->addHeader('importance', $this->importance);
        }

        return [
            'part' => $text,
            'headers' => $headers,
        ];
    }

    /**
     * Add an AirSyncBaseAttachment object to this message.
     *
     * @param Horde_ActiveSync_Message_AirSyncBaseAttachment $atc
     * @throws  Horde_ActiveSync_Exception
     */
    public function addAttachment(Horde_ActiveSync_Message_AirSyncBaseAttachment $atc)
    {
        if (!is_null($this->_properties['airsyncbaseattachments'])) {
            $this->_properties['airsyncbaseattachments'][] = $atc;
        } else {
            throw new Horde_ActiveSync_Exception('Property unavailable');
        }
    }

    /**
     * Return the class type for this object.
     *
     * @return string
     */
    public function getClass()
    {
        return 'Email';
    }

    /**
     * Checks to see if we should send an empty value.
     *
     * @param string $tag  The tag name
     *
     * @return boolean
     */
    protected function _checkSendEmpty($tag)
    {
        switch ($tag) {
            case self::POOMMAIL_FLAG:
            case self::POOMMAIL_CATEGORIES:
                return true;
        }

        return false;
    }

}
