<?php

/**
 * Horde_ActiveSync_Status::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2013-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Horde_ActiveSync_Status:: Constants for common EAS status codes. Common codes
 * were introduced in EAS 14.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2013-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Status
{
    // EAS 12.1
    public const INVALID_CONTENT                        = 101;
    public const INVALID_WBXML                          = 102;
    public const INVALID_XML                            = 103;
    public const INVALID_DATETIME                       = 104;
    public const INVALID_COMBINATIONOFIDS               = 105;
    public const INVALID_IDS                            = 106; // was previously 400 or 500 (for SENDMAIL) in 12.0.
    public const INVALID_MIME                           = 107;
    public const INVALID_DEVICEID                       = 108;
    public const INVALID_DEVICETYPE                     = 109;
    public const SERVER_ERROR                           = 110; // was a general 500 error (server should not try again) in 12.0.
    public const SERVER_ERROR_RETRY                     = 111; // was a 503 in 12.0
    public const MAILBOX_QUOTA_EXCEEDED                 = 113;
    public const MAILBOX_OFFLINE                        = 114;
    public const SEND_QUOTA_EXCEEDED                    = 115;
    public const RECIPIENT_UNRESOLVED                   = 116;
    public const DUPLICATE_MESSAGE                      = 118; // @TODO
    public const NO_RECIPIENT                           = 119;
    public const MAIL_SUBMISSION_FAILED                 = 120;
    public const MAIL_REPLY_FAILED                      = 121;
    public const ATT_TOO_LARGE                          = 122;
    public const NO_MAILBOX                             = 123;
    public const SYNC_NOT_ALLOWED                       = 126;
    public const DEVICE_BLOCKED_FOR_USER                = 129;
    public const DENIED                                 = 130;
    public const DISABLED                               = 131;
    public const STATEFILE_NOT_FOUND                    = 132;  // was 500 in 12.0
    public const STATEVERSION_INVALID                   = 136;
    public const DEVICE_NOT_FULLY_PROVISIONABLE         = 139;  // Device uses version that doesn't support policies defined on server.
    public const REMOTEWIPE_REQUESTED                   = 140;
    public const LEGACY_DEVICE_STRICT_POLICY            = 141;
    public const DEVICE_NOT_PROVISIONED                 = 142;
    public const POLICY_REFRESH                         = 143;
    public const INVALID_POLICY_KEY                     = 144;
    public const EXTERNALLY_MANAGED_DEVICES_NOT_ALLOWED = 145;
    public const UNEXPECTED_ITEM_CLASS                  = 147;
    public const INVALID_STORED_REQUEST                 = 149;
    public const ITEM_NOT_FOUND                         = 150;
    public const TOO_MANY_FOLDERS                       = 151;
    public const NO_FOLDERS_FOUND                       = 152;
    public const ITEMS_LOST_AFTER_MOVE                  = 153;
    public const FAILURE_IN_MOVE_OPERATION              = 154;
    public const MOVE_INVALID_DESTINATION               = 156;
    // EAS 14.0
    public const AVAILABILITY_TOO_MANY_RECIPIENTS       = 160;
    public const AVAILABILITY_TRANSIENT_FAILURE         = 162;
    public const AVAILABILITY_FAILURE                   = 163;
    public const AVAILABILITY_SUCCESS                   = 1;
    // EAS 14.1
    public const DEVICE_INFORMATION_REQUIRED            = 165;
    public const INVALID_ACCOUNT_ID                     = 166;
    public const IRM_DISABLED                           = 168;
    public const PICTURE_SUCCESS                        = 1;
    public const NO_PICTURE                             = 173;
    public const PICTURE_TOO_LARGE                      = 174;
    public const PICTURE_LIMIT_REACHED                  = 175;
    public const BODYPART_CONVERSATION_TOO_LARGE        = 176;
    public const MAXIMUM_DEVICES_REACHED                = 177;

}
