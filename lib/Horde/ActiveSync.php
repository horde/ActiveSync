<?php

/**
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2009-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */

/**
 * The Horde ActiveSync server. Entry point for performing all ActiveSync
 * operations.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2009-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property-read Horde_ActiveSync_Wbxml_Encoder $encoder The Wbxml encoder.
 * @property-read Horde_ActiveSync_Wbxml_Decoder $decoder The Wbxml decoder.
 * @property-read Horde_ActiveSync_State_Base $state      The state object.
 * @property-read Horde_Controller_Request_Http $request  The HTTP request object.
 * @property-read Horde_ActiveSync_Driver_Base $driver    The backend driver object.
 * @property-read boolean|string $provisioning Provisioning support: True, False, or 'loose'
 * @property-read boolean $multipart Indicate this is a multipart request.
 * @property-read string $certPath Local path to the certificate bundle.
 * @property-read Horde_ActiveSync_Device $device  The current device object.
 * @property-read Horde_ActiveSync_Log_Logger $logger   The logger object.
 */
class Horde_ActiveSync
{
    /* Conflict resolution */
    public const CONFLICT_OVERWRITE_SERVER             = 0;
    public const CONFLICT_OVERWRITE_PIM                = 1;

    /* TRUNCATION Constants */
    public const TRUNCATION_ALL                        = 0;
    public const TRUNCATION_1                          = 1;
    public const TRUNCATION_2                          = 2;
    public const TRUNCATION_3                          = 3;
    public const TRUNCATION_4                          = 4;
    public const TRUNCATION_5                          = 5;
    public const TRUNCATION_6                          = 6;
    public const TRUNCATION_7                          = 7;
    public const TRUNCATION_8                          = 8;
    public const TRUNCATION_9                          = 9;
    public const TRUNCATION_NONE                       = 9; // @deprecated

    /* FOLDERHIERARCHY */
    public const FOLDERHIERARCHY_FOLDERS               = 'FolderHierarchy:Folders';
    public const FOLDERHIERARCHY_FOLDER                = 'FolderHierarchy:Folder';
    public const FOLDERHIERARCHY_DISPLAYNAME           = 'FolderHierarchy:DisplayName';
    public const FOLDERHIERARCHY_SERVERENTRYID         = 'FolderHierarchy:ServerEntryId';
    public const FOLDERHIERARCHY_PARENTID              = 'FolderHierarchy:ParentId';
    public const FOLDERHIERARCHY_TYPE                  = 'FolderHierarchy:Type';
    public const FOLDERHIERARCHY_RESPONSE              = 'FolderHierarchy:Response';
    public const FOLDERHIERARCHY_STATUS                = 'FolderHierarchy:Status';
    public const FOLDERHIERARCHY_CONTENTCLASS          = 'FolderHierarchy:ContentClass';
    public const FOLDERHIERARCHY_CHANGES               = 'FolderHierarchy:Changes';
    public const FOLDERHIERARCHY_SYNCKEY               = 'FolderHierarchy:SyncKey';
    public const FOLDERHIERARCHY_FOLDERSYNC            = 'FolderHierarchy:FolderSync';
    public const FOLDERHIERARCHY_COUNT                 = 'FolderHierarchy:Count';
    public const FOLDERHIERARCHY_VERSION               = 'FolderHierarchy:Version';

    /* SYNC */
    public const SYNC_SYNCHRONIZE                      = 'Synchronize';
    public const SYNC_REPLIES                          = 'Replies';
    public const SYNC_ADD                              = 'Add';
    public const SYNC_MODIFY                           = 'Modify';
    public const SYNC_REMOVE                           = 'Remove';
    public const SYNC_FETCH                            = 'Fetch';
    public const SYNC_SYNCKEY                          = 'SyncKey';
    public const SYNC_CLIENTENTRYID                    = 'ClientEntryId';
    public const SYNC_SERVERENTRYID                    = 'ServerEntryId';
    public const SYNC_STATUS                           = 'Status';
    public const SYNC_FOLDER                           = 'Folder';
    public const SYNC_FOLDERTYPE                       = 'FolderType';
    public const SYNC_VERSION                          = 'Version';
    public const SYNC_FOLDERID                         = 'FolderId';
    public const SYNC_GETCHANGES                       = 'GetChanges';
    public const SYNC_MOREAVAILABLE                    = 'MoreAvailable';
    public const SYNC_WINDOWSIZE                       = 'WindowSize';
    public const SYNC_COMMANDS                         = 'Commands';
    public const SYNC_OPTIONS                          = 'Options';
    public const SYNC_FILTERTYPE                       = 'FilterType';
    public const SYNC_TRUNCATION                       = 'Truncation';
    public const SYNC_RTFTRUNCATION                    = 'RtfTruncation';
    public const SYNC_CONFLICT                         = 'Conflict';
    public const SYNC_FOLDERS                          = 'Folders';
    public const SYNC_DATA                             = 'Data';
    public const SYNC_DELETESASMOVES                   = 'DeletesAsMoves';
    public const SYNC_NOTIFYGUID                       = 'NotifyGUID';
    public const SYNC_SUPPORTED                        = 'Supported';
    public const SYNC_SOFTDELETE                       = 'SoftDelete';
    public const SYNC_MIMESUPPORT                      = 'MIMESupport';
    public const SYNC_MIMETRUNCATION                   = 'MIMETruncation';
    public const SYNC_NEWMESSAGE                       = 'NewMessage';
    public const SYNC_PARTIAL                          = 'Partial';
    public const SYNC_WAIT                             = 'Wait';
    public const SYNC_LIMIT                            = 'Limit';
    // 14
    public const SYNC_HEARTBEATINTERVAL                = 'HeartbeatInterval';
    public const SYNC_CONVERSATIONMODE                 = 'ConversationMode';
    public const SYNC_MAXITEMS                         = 'MaxItems';

    /* Document library */
    public const SYNC_DOCUMENTLIBRARY_LINKID           = 'DocumentLibrary:LinkId';
    public const SYNC_DOCUMENTLIBRARY_DISPLAYNAME      = 'DocumentLibrary:DisplayName';
    public const SYNC_DOCUMENTLIBRARY_ISFOLDER         = 'DocumentLibrary:IsFolder';
    public const SYNC_DOCUMENTLIBRARY_CREATIONDATE     = 'DocumentLibrary:CreationDate';
    public const SYNC_DOCUMENTLIBRARY_LASTMODIFIEDDATE = 'DocumentLibrary:LastModifiedDate';
    public const SYNC_DOCUMENTLIBRARY_ISHIDDEN         = 'DocumentLibrary:IsHidden';
    public const SYNC_DOCUMENTLIBRARY_CONTENTLENGTH    = 'DocumentLibrary:ContentLength';
    public const SYNC_DOCUMENTLIBRARY_CONTENTTYPE      = 'DocumentLibrary:ContentType';

    /* AIRSYNCBASE */
    public const AIRSYNCBASE_BODYPREFERENCE            = 'AirSyncBase:BodyPreference';
    public const AIRSYNCBASE_TYPE                      = 'AirSyncBase:Type';
    public const AIRSYNCBASE_TRUNCATIONSIZE            = 'AirSyncBase:TruncationSize';
    public const AIRSYNCBASE_ALLORNONE                 = 'AirSyncBase:AllOrNone';
    public const AIRSYNCBASE_BODY                      = 'AirSyncBase:Body';
    public const AIRSYNCBASE_DATA                      = 'AirSyncBase:Data';
    public const AIRSYNCBASE_ESTIMATEDDATASIZE         = 'AirSyncBase:EstimatedDataSize';
    public const AIRSYNCBASE_TRUNCATED                 = 'AirSyncBase:Truncated';
    public const AIRSYNCBASE_ATTACHMENTS               = 'AirSyncBase:Attachments';
    public const AIRSYNCBASE_ATTACHMENT                = 'AirSyncBase:Attachment';
    public const AIRSYNCBASE_DISPLAYNAME               = 'AirSyncBase:DisplayName';
    public const AIRSYNCBASE_FILEREFERENCE             = 'AirSyncBase:FileReference';
    public const AIRSYNCBASE_METHOD                    = 'AirSyncBase:Method';
    public const AIRSYNCBASE_CONTENTID                 = 'AirSyncBase:ContentId';
    public const AIRSYNCBASE_CONTENTLOCATION           = 'AirSyncBase:ContentLocation';
    public const AIRSYNCBASE_ISINLINE                  = 'AirSyncBase:IsInline';
    public const AIRSYNCBASE_NATIVEBODYTYPE            = 'AirSyncBase:NativeBodyType';
    public const AIRSYNCBASE_CONTENTTYPE               = 'AirSyncBase:ContentType';
    public const AIRSYNCBASE_LOCATION                  = 'AirSyncBase:Location';

    // 14.0
    public const AIRSYNCBASE_PREVIEW                   = 'AirSyncBase:Preview';

    // 14.1
    public const AIRSYNCBASE_BODYPARTPREFERENCE        = 'AirSyncBase:BodyPartPreference';
    public const AIRSYNCBASE_BODYPART                  = 'AirSyncBase:BodyPart';
    public const AIRSYNCBASE_STATUS                    = 'AirSyncBase:Status';

    // 16.0
    public const AIRSYNCBASE_ADD                       = 'AirSyncBase:Add';
    public const AIRSYNCBASE_DELETE                    = 'AirSyncBase:Delete';
    public const AIRSYNCBASE_CLIENTID                  = 'AirSyncBase:ClientId';
    public const AIRSYNCBASE_CONTENT                   = 'AirSyncBase:Content';
    public const AIRSYNCBASE_ANNOTATION                = 'AirSyncBase:Annotation';
    public const AIRSYNCBASE_STREET                    = 'AirSyncBase:Street';
    public const AIRSYNCBASE_CITY                      = 'AirSyncBase:City';
    public const AIRSYNCBASE_STATE                     = 'AirSyncBase:State';
    public const AIRSYNCBASE_COUNTRY                   = 'AirSyncBase:Country';
    public const AIRSYNCBASE_POSTALCODE                = 'AirSyncBase:PostalCode';
    public const AIRSYNCBASE_LATITUDE                  = 'AirSyncBase:Latitude';
    public const AIRSYNCBASE_LONGITUDE                 = 'AirSyncBase:Longitude';
    public const AIRSYNCBASE_ACCURACY                  = 'AirSyncBase:Accuracy';
    public const AIRSYNCBASE_ALTITUDE                  = 'AirSyncBase:Altitude';
    public const AIRSYNCBASE_ALTITUDEACCURACY          = 'AirSyncBase:AltitudeAccuracy';
    public const AIRSYNCBASE_LOCATIONURI               = 'AirSyncBase:LocationUri';
    public const AIRSYNCBASE_INSTANCEID                = 'AirSyncBase:InstanceId';


    /* Body type prefs */
    public const BODYPREF_TYPE_PLAIN                   = 1;
    public const BODYPREF_TYPE_HTML                    = 2;
    public const BODYPREF_TYPE_RTF                     = 3;
    public const BODYPREF_TYPE_MIME                    = 4;

    /* PROVISION */
    public const PROVISION_PROVISION                   =  'Provision:Provision';
    public const PROVISION_POLICIES                    =  'Provision:Policies';
    public const PROVISION_POLICY                      =  'Provision:Policy';
    public const PROVISION_POLICYTYPE                  =  'Provision:PolicyType';
    public const PROVISION_POLICYKEY                   =  'Provision:PolicyKey';
    public const PROVISION_DATA                        =  'Provision:Data';
    public const PROVISION_STATUS                      =  'Provision:Status';
    public const PROVISION_REMOTEWIPE                  =  'Provision:RemoteWipe';
    public const PROVISION_EASPROVISIONDOC             =  'Provision:EASProvisionDoc';

    /* Policy types */
    public const POLICYTYPE_XML                        = 'MS-WAP-Provisioning-XML';
    public const POLICYTYPE_WBXML                      = 'MS-EAS-Provisioning-WBXML';

    /* Flags */
    // @TODO: H6 Change this to CHANGE_TYPE_NEW
    public const FLAG_NEWMESSAGE                       = 'NewMessage';

    /* Folder types */
    public const FOLDER_TYPE_OTHER                     =  1;
    public const FOLDER_TYPE_INBOX                     =  2;
    public const FOLDER_TYPE_DRAFTS                    =  3;
    public const FOLDER_TYPE_WASTEBASKET               =  4;
    public const FOLDER_TYPE_SENTMAIL                  =  5;
    public const FOLDER_TYPE_OUTBOX                    =  6;
    public const FOLDER_TYPE_TASK                      =  7;
    public const FOLDER_TYPE_APPOINTMENT               =  8;
    public const FOLDER_TYPE_CONTACT                   =  9;
    public const FOLDER_TYPE_NOTE                      =  10;
    public const FOLDER_TYPE_JOURNAL                   =  11;
    public const FOLDER_TYPE_USER_MAIL                 =  12;
    public const FOLDER_TYPE_USER_APPOINTMENT          =  13;
    public const FOLDER_TYPE_USER_CONTACT              =  14;
    public const FOLDER_TYPE_USER_TASK                 =  15;
    public const FOLDER_TYPE_USER_JOURNAL              =  16;
    public const FOLDER_TYPE_USER_NOTE                 =  17;
    public const FOLDER_TYPE_UNKNOWN                   =  18;
    public const FOLDER_TYPE_RECIPIENT_CACHE           =  19;
    // @TODO, remove const definition in H6, not used anymore.
    public const FOLDER_TYPE_DUMMY                     =  999999;

    /* Origin of changes **/
    public const CHANGE_ORIGIN_PIM                     = 0;
    public const CHANGE_ORIGIN_SERVER                  = 1;
    public const CHANGE_ORIGIN_NA                      = 3;

    /* Remote wipe **/
    public const RWSTATUS_NA                           = 0;
    public const RWSTATUS_OK                           = 1;
    public const RWSTATUS_PENDING                      = 2;
    public const RWSTATUS_WIPED                        = 3;

    /* GAL **/
    public const GAL_DISPLAYNAME                       = 'GAL:DisplayName';
    public const GAL_PHONE                             = 'GAL:Phone';
    public const GAL_OFFICE                            = 'GAL:Office';
    public const GAL_TITLE                             = 'GAL:Title';
    public const GAL_COMPANY                           = 'GAL:Company';
    public const GAL_ALIAS                             = 'GAL:Alias';
    public const GAL_FIRSTNAME                         = 'GAL:FirstName';
    public const GAL_LASTNAME                          = 'GAL:LastName';
    public const GAL_HOMEPHONE                         = 'GAL:HomePhone';
    public const GAL_MOBILEPHONE                       = 'GAL:MobilePhone';
    public const GAL_EMAILADDRESS                      = 'GAL:EmailAddress';
    // 14.1
    public const GAL_PICTURE                           = 'GAL:Picture';
    public const GAL_STATUS                            = 'GAL:Status';
    public const GAL_DATA                              = 'GAL:Data';

    /* Request Type */
    public const REQUEST_TYPE_SYNC                     = 'sync';
    public const REQUEST_TYPE_FOLDERSYNC               = 'foldersync';

    /* Change Type */
    public const CHANGE_TYPE_CHANGE                    = 'change';
    public const CHANGE_TYPE_DELETE                    = 'delete';
    public const CHANGE_TYPE_FLAGS                     = 'flags';
    public const CHANGE_TYPE_MOVE                      = 'move';
    public const CHANGE_TYPE_FOLDERSYNC                = 'foldersync';
    public const CHANGE_TYPE_SOFTDELETE                = 'softdelete';

    // @since 2.36.0
    public const CHANGE_TYPE_DRAFT                     = 'draft';

    /* Internal flags to indicate change is a change in reply/forward state */
    public const CHANGE_REPLY_STATE                    = '@--reply--@';
    public const CHANGE_REPLYALL_STATE                 = '@--replyall--@';
    public const CHANGE_FORWARD_STATE                  = '@--forward--@';

    /* RM */
    public const RM_SUPPORT                            = 'RightsManagement:RightsManagementSupport';
    public const RM_TEMPLATEID                         = 'RightsManagement:TemplateId';

    /* Collection Classes */
    public const CLASS_EMAIL                           = 'Email';
    public const CLASS_CONTACTS                        = 'Contacts';
    public const CLASS_CALENDAR                        = 'Calendar';
    public const CLASS_TASKS                           = 'Tasks';
    public const CLASS_NOTES                           = 'Notes';
    public const CLASS_SMS                             = 'SMS';

    /* Filtertype constants */
    public const FILTERTYPE_ALL                        = 0;
    public const FILTERTYPE_1DAY                       = 1;
    public const FILTERTYPE_3DAYS                      = 2;
    public const FILTERTYPE_1WEEK                      = 3;
    public const FILTERTYPE_2WEEKS                     = 4;
    public const FILTERTYPE_1MONTH                     = 5;
    public const FILTERTYPE_3MONTHS                    = 6;
    public const FILTERTYPE_6MONTHS                    = 7;
    public const FILTERTYPE_INCOMPLETETASKS            = 8;

    // @todo normalize to string values.
    public const PROVISIONING_FORCE                    = true;
    public const PROVISIONING_LOOSE                    = 'loose';
    public const PROVISIONING_NONE                     = false;

    public const FOLDER_ROOT                           = 0;

    public const VERSION_TWOFIVE                       = '2.5';
    public const VERSION_TWELVE                        = '12.0';
    public const VERSION_TWELVEONE                     = '12.1';
    public const VERSION_FOURTEEN                      = '14.0';
    public const VERSION_FOURTEENONE                   = '14.1';
    public const VERSION_SIXTEEN                       = '16.0';

    public const MIME_SUPPORT_NONE                     = 0;
    public const MIME_SUPPORT_SMIME                    = 1;
    public const MIME_SUPPORT_ALL                      = 2;

    public const IMAP_FLAG_REPLY                       = 'reply';
    public const IMAP_FLAG_FORWARD                     = 'forward';

    /* Result Type */
    public const RESOLVE_RESULT_GAL                    = 1;
    public const RESOLVE_RESULT_ADDRESSBOOK            = 2;

    /* Auth failure reasons */
    public const AUTH_REASON_USER_DENIED               = 'user';
    public const AUTH_REASON_DEVICE_DENIED             = 'device';
    public const AUTH_REASON_UNAVAILABLE               = 'unavailable';

    /* Internal flag indicates all possible fields are ghosted */
    public const ALL_GHOSTED                           = 'allghosted';

    public const LIBRARY_VERSION                       = '2.x.y-git';

    /**
     * Logger
     *
     * @var Horde_ActiveSync_Interface_LoggerFactory
     */
    protected $_loggerFactory;

    /**
     * The logger for this class.
     *
     * @var Horde_Log_Logger
     */
    protected static $_logger;

    /**
     * Provisioning support
     *
     * @var string
     */
    protected $_provisioning;

    /**
     * Highest version to support.
     *
     * @var float
     */
    protected $_maxVersion = self::VERSION_SIXTEEN;

    /**
     * The actual version we are supporting.
     *
     * @var float
     */
    protected static $_version;

    /**
     * Multipart support?
     *
     * @var boolean
     */
    protected $_multipart = false;

    /**
     * Support gzip compression of certain data parts?
     *
     * @var boolean
     */
    protected $_compression = false;

    /**
     * Local cache of Get variables/decoded base64 uri
     *
     * @var array
     */
    protected $_get = [];

    /**
     * Path to root certificate bundle
     *
     * @var string
     */
    protected $_certPath;

    /**
     * The policy key.
     *
     * @var integer
     */
    protected $_policykey;

    /**
     *
     * @var Horde_ActiveSync_Device
     */
    protected static $_device;

    /**
     * The HTTP request object.
     *
     * @var Horde_Controller_Request_Http
     */
    protected $_request;

    /**
     * The backend driver.
     *
     * @var Horde_ActiveSync_Driver_Base
     */
    protected $_driver;

    /**
     * Device state manager.
     *
     * @var Horde_ActiveSync_State_Base
     */
    protected $_state;

    /**
     * Wbxml encoder
     *
     * @var Horde_ActiveSync_Wbxml_Encoder
     */
    protected $_encoder;

    /**
     * Wbxml decoder
     *
     * @var Horde_ActiveSync_Wbxml_Decoder
     */
    protected $_decoder;

    /**
     * The singleton collections handler.
     *
     * @var Horde_ActiveSync_Collections
     */
    protected $_collectionsObj;

    /**
     * Global error flag.
     *
     * @var int  ActiveSync global status code.
     */
    protected $_globalError = 0;

    /**
     * Process id (used in logging).
     *
     * @var integer
     */
    protected $_procid;

    /**
     * Flag to  indicate we need to update the device version.
     *
     * @var boolean
     */
    protected $_needMsRp = false;

    /**
     * Supported EAS versions.
     *
     * @var array
     */
    protected static $_supportedVersions = [
        self::VERSION_TWOFIVE,
        self::VERSION_TWELVE,
        self::VERSION_TWELVEONE,
        self::VERSION_FOURTEEN,
        self::VERSION_FOURTEENONE,
        self::VERSION_SIXTEEN,
    ];

    /**
     * Factory method for creating Horde_ActiveSync_Message objects.
     *
     * @param string $message  The message type.
     * @since 2.4.0
     *
     * @return Horde_ActiveSync_Message_Base   The concrete message object.
     * @todo For H6, move to Horde_ActiveSync_Message_Base::factory()
     */
    public static function messageFactory($message)
    {
        $class = 'Horde_ActiveSync_Message_' . $message;
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Class %s does not exist.', $class));
        }

        return new $class([
            'logger' => self::$_logger,
            'protocolversion' => self::$_version,
            'device' => self::$_device]);
    }

    /**
     * Const'r
     *
     * @param Horde_ActiveSync_Driver_Base $driver      The backend driver.
     * @param Horde_ActiveSync_Wbxml_Decoder $decoder   The Wbxml decoder.
     * @param Horde_ActiveSync_Wbxml_Endcoder $encoder  The Wbxml encoder.
     * @param Horde_ActiveSync_State_Base $state        The state driver.
     * @param Horde_Controller_Request_Http $request    The HTTP request object.
     *
     * @return Horde_ActiveSync  The ActiveSync server object.
     */
    public function __construct(
        Horde_ActiveSync_Driver_Base $driver,
        Horde_ActiveSync_Wbxml_Decoder $decoder,
        Horde_ActiveSync_Wbxml_Encoder $encoder,
        Horde_ActiveSync_State_Base $state,
        Horde_Controller_Request_Http $request
    ) {
        // The http request
        $this->_request = $request;

        // Backend driver
        $this->_driver = $driver;
        $this->_driver->setProtocolVersion($this->getProtocolVersion());

        // Device state manager
        $this->_state = $state;

        // Wbxml handlers
        $this->_encoder = $encoder;
        $this->_decoder = $decoder;

        $this->_procid = getmypid();
    }

    /**
     * Return a collections singleton.
     *
     * @return Horde_ActiveSync_Collections
     * @since 2.4.0
     */
    public function getCollectionsObject()
    {
        if (empty($this->_collectionsObj)) {
            $this->_collectionsObj = new Horde_ActiveSync_Collections($this->getSyncCache(), $this);
        }

        return $this->_collectionsObj;
    }

    /**
     * Return a new, fully configured SyncCache.
     *
     * @return Horde_ActiveSync_SyncCache
     * @since 2.4.0
     */
    public function getSyncCache()
    {
        return new Horde_ActiveSync_SyncCache(
            $this->_state,
            self::$_device->id,
            self::$_device->user,
            self::$_logger
        );
    }

    /**
     * Return an Importer object.
     *
     * @return Horde_ActiveSync_Connector_Importer
     * @since 2.4.0
     */
    public function getImporter()
    {
        $importer = new Horde_ActiveSync_Connector_Importer($this);
        $importer->setLogger(self::$_logger);

        return $importer;
    }

    /**
     * Authenticate to the backend.
     *
     * @param Horde_ActiveSync_Credentials $credentials  The credentials object.
     *
     * @return boolean  True on successful authentication to the backend.
     * @throws Horde_ActiveSync_Exception
     */
    public function authenticate(Horde_ActiveSync_Credentials $credentials)
    {
        if (!$credentials->username) {
            // No provided username or Authorization header.
            self::$_logger->notice('Client did not provide authentication data.');
            return false;
        }

        $user = $this->_driver->getUsernameFromEmail($credentials->username);
        $pos = strrpos($user, '\\');
        if ($pos !== false) {
            $domain = substr($user, 0, $pos);
            $user = substr($user, $pos + 1);
        } else {
            $domain = null;
        }

        // Authenticate
        if ($result = $this->_driver->authenticate($user, $credentials->password, $domain)) {
            if ($result === self::AUTH_REASON_USER_DENIED) {
                $this->_globalError = Horde_ActiveSync_Status::SYNC_NOT_ALLOWED;
            } elseif ($result === self::AUTH_REASON_DEVICE_DENIED) {
                $this->_globalError = Horde_ActiveSync_Status::DEVICE_BLOCKED_FOR_USER;
            } elseif ($result === self::AUTH_REASON_UNAVAILABLE) {
                $this->_globalError = Horde_ActiveSync_Status::SERVER_ERROR_RETRY;
                if ($this->getProtocolVersion() < Horde_ActiveSync::VERSION_FOURTEEN) {
                    // Horde_ActiveSync_STATUS_SERVER_ERROR_RETRY only supported >= 14.0
                    return false;
                }
            } elseif ($result !== true) {
                $this->_globalError = Horde_ActiveSync_Status::DENIED;
            }
        } else {
            return false;
        }

        if (!$this->_driver->setup($user)) {
            return false;
        }

        return true;
    }

    /**
     * Allow to force the highest version to support.
     *
     * @param float $version  The highest version
     */
    public function setSupportedVersion($version)
    {
        $this->_maxVersion = $version;
    }

    /**
     * Set the local path to the root certificate bundle.
     *
     * @param string $path  The local path to the bundle.
     */
    public function setRootCertificatePath($path)
    {
        $this->_certPath = $path;
    }

    /**
     * Getter
     *
     * @param string $property  The property to return.
     *
     * @return mixed  The value of the requested property.
     */
    public function __get($property)
    {
        switch ($property) {
            case 'encoder':
            case 'decoder':
            case 'state':
            case 'request':
            case 'driver':
            case 'provisioning':
            case 'multipart':
            case 'certPath':
                $property = '_' . $property;
                return $this->$property;
            case 'logger':
                return self::$_logger;
            case 'device':
                return self::$_device;
            default:
                throw new InvalidArgumentException(
                    sprintf(
                        'The property %s does not exist',
                        $property
                    )
                );
        }
    }

    /**
     * Setter for the logger factory.
     *
     * @param Horde_ActiveSync_Interface_LoggerFactory $logger  The logger factory.
     */
    public function setLogger(Horde_ActiveSync_Interface_LoggerFactory $logger)
    {
        $this->_loggerFactory = $logger;
    }

    /**
     * Instantiate the logger from the factory and inject into all needed
     * objects.
     *
     * @param array $options [description]
     */
    protected function _setLogger(array $options)
    {
        if (!empty($this->_loggerFactory)) {
            // @TODO. Remove wrapper.
            self::$_logger = self::_wrapLogger($this->_loggerFactory->create($options));
            $this->_encoder->setLogger(self::$_logger);
            $this->_decoder->setLogger(self::$_logger);
            $this->_driver->setLogger(self::$_logger);
            $this->_state->setLogger(self::$_logger);
        }
    }

    public static function _wrapLogger(Horde_Log_Logger $logger)
    {
        if (!($logger instanceof Horde_ActiveSync_Log_Logger)) {
            return new Horde_ActiveSync_Log_Logger_Deprecated(null, $logger);
        }

        return $logger;
    }

    /**
     * Setter for provisioning support
     *
     */
    public function setProvisioning($provision)
    {
        $this->_provisioning = $provision;
    }

    /**
     * Send the headers indicating that provisioning is required.
     */
    public function provisioningRequired()
    {
        $this->provisionHeader();
        $this->activeSyncHeader();
        $this->versionHeader();
        $this->commandsHeader();
        header('Cache-Control: private');
    }

    /**
     * The heart of the server. Dispatch a request to the appropriate request
     * handler.
     *
     * @param string $cmd    The command we are requesting.
     * @param string $devId  The device id making the request. @deprecated
     *
     * @return string|boolean  false if failed, true if succeeded and response
     *                         content is wbxml, otherwise the
     *                         content-type string to send in the response.
     * @throws Horde_ActiveSync_Exception
     * @throws Horde_ActiveSync_Exception_InvalidRequest
     * @throws Horde_ActiveSync_PermissionDenied
     */
    public function handleRequest($cmd, $devId)
    {
        $get = $this->getGetVars();
        if (empty($cmd)) {
            $cmd = $get['Cmd'];
        }
        if (empty($devId)) {
            $devId = !empty($get['DeviceId']) ? Horde_String::upper($get['DeviceId']) : null;
        } else {
            $devId = Horde_String::upper($devId);
        }
        $this->_setLogger($get);

        // @TODO: Remove is_callable check for H6.
        // Callback to give the backend the option to limit EAS version based
        // on user/device/etc...
        if (is_callable([$this->_driver, 'versionCallback'])) {
            $this->_driver->versionCallback($this);
        }

        // Autodiscovery handles authentication on it's own.
        if ($cmd == 'Autodiscover') {
            $request = new Horde_ActiveSync_Request_Autodiscover($this, new Horde_ActiveSync_Device($this->_state));

            if (!empty(self::$_logger)) {
                $request->setLogger(self::$_logger);
            }

            $result = $request->handle($this->_request);
            $this->_driver->clearAuthentication();
            return $result;
        }
        if (!$this->authenticate(new Horde_ActiveSync_Credentials($this))) {
            $this->activeSyncHeader();
            $this->versionHeader();
            $this->commandsHeader();
            throw new Horde_Exception_AuthenticationFailure('', $this->_globalError);
        }

        self::$_logger->info(
            sprintf(
                '%s%s request received for user %s',
                str_repeat('-', 10),
                Horde_String::upper($cmd),
                $this->_driver->getUser()
            )
        );

        // These are all handled in the same class.
        if ($cmd == 'FolderDelete' || $cmd == 'FolderUpdate') {
            $cmd = 'FolderCreate';
        }

        // Device id is REQUIRED
        if (empty($devId)) {
            if ($cmd == 'Options') {
                $this->_handleOptionsRequest();
                $this->_driver->clearAuthentication();
                return true;
            }
            $this->_driver->clearAuthentication();
            throw new Horde_ActiveSync_Exception_InvalidRequest('Device failed to send device id.');
        }

        // EAS Version
        $version = $this->getProtocolVersion();

        // Device. Even though versions of EAS > 12.1 are supposed to send
        // EAS status codes back to indicate various errors in allowing a client
        // to connect, we just throw an exception (thus causing a HTTP error
        // code to be sent as in versions 12.1 and below). Until we refactor for
        // Horde 6, we don't know the response type to wrap the status code in
        // until we load the request handler, which requires we start to parse
        // the WBXML stream and device information etc... This saves resources
        // as well as keeps things cleaner until we refactor.
        $device_result = $this->_handleDevice($devId);

        // Don't bother with everything else if all we want are Options
        if ($cmd == 'Options') {
            $this->_handleOptionsRequest();
            $this->_driver->clearAuthentication();
            return true;
        }

        // Set provisioning support now that we are authenticated.
        $this->setProvisioning($this->_driver->getProvisioning(self::$_device));

        // Read the initial Wbxml header
        $this->_decoder->readWbxmlHeader();

        // Support Multipart response for ITEMOPERATIONS requests?
        $headers = $this->_request->getHeaders();
        if ((!empty($headers['ms-asacceptmultipart']) && $headers['ms-asacceptmultipart'] == 'T')
            || !empty($get['AcceptMultiPart'])) {
            $this->_multipart = true;
            self::$_logger->info('Requesting multipart data.');
        }

        // Load the request handler to handle the request
        // We must send the EAS header here, since some requests may start
        // output and be large enough to flush the buffer (e.g., GetAttachment)
        // See Bug: 12486
        $this->activeSyncHeader();
        if ($cmd != 'GetAttachment') {
            $this->contentTypeHeader();
        }

        // Should we announce a new version is available to the client?
        if (!empty($this->_needMsRp)) {
            self::$_logger->info('Announcing X-MS-RP to client.');
            header("X-MS-RP: " . $this->getSupportedVersions());
        }

        // @TODO: Look at getting rid of having to set the version in the driver
        //        and get it from the device object for H6.
        $this->_driver->setDevice(self::$_device);
        $class = 'Horde_ActiveSync_Request_' . basename($cmd);
        if (class_exists($class)) {
            $request = new $class($this);
            $request->setLogger(self::$_logger);
            $result = $request->handle();
            self::$_logger->info(
                sprintf(
                    'Maximum memory usage for ActiveSync request: %d bytes.',
                    memory_get_peak_usage(true)
                )
            );

            return $result;
        }

        $this->_driver->clearAuthentication();
        throw new Horde_ActiveSync_Exception_InvalidRequest(basename($cmd) . ' not supported.');
    }

    /**
     * Handle device checks. Takes into account permissions and restrictions
     * via various callback methods.
     *
     * @param  string $devId  The client provided device id.
     *
     * @return boolean  If EAS version is > 12.1 returns false on any type of
     *                  failure in allowing the device to connect. Sets
     *                  appropriate internal variables to indicate the type of
     *                  error to return to the client. Failure on EAS version
     *                  < 12.1 results in throwing exceptions. Otherwise, return
     *                  true.
     * @throws Horde_ActiveSync_Exception, Horde_Exception_AuthenticationFailure
     */
    protected function _handleDevice($devId)
    {
        $get = $this->getGetVars();
        $version = $this->getProtocolVersion();

        // Does device exist AND does the user have an account on the device?
        if (!$this->_state->deviceExists($devId, $this->_driver->getUser())) {
            // Device might exist, but with a new (additional) user account
            if ($this->_state->deviceExists($devId)) {
                self::$_device = $this->_state->loadDeviceInfo($devId);
            } else {
                self::$_device = new Horde_ActiveSync_Device($this->_state);
            }
            self::$_device->policykey = 0;
            self::$_device->userAgent = $this->_request->getHeader('User-Agent');
            self::$_device->deviceType = !empty($get['DeviceType']) ? $get['DeviceType'] : '';
            self::$_device->rwstatus = self::RWSTATUS_NA;
            self::$_device->user = $this->_driver->getUser();
            self::$_device->id = $devId;
            self::$_device->needsVersionUpdate($this->getSupportedVersions());
            self::$_device->version = $version;
            // @TODO: Remove is_callable check for H6.
            //        Combine this with the modifyDevice callback? Allow $device
            //        to be modified here?
            if (is_callable([$this->_driver, 'createDeviceCallback'])) {
                $callback_ret = $this->_driver->createDeviceCallback(self::$_device);
                if ($callback_ret !== true) {
                    $msg = sprintf(
                        'The device %s was disallowed for user %s per policy settings.',
                        self::$_device->id,
                        self::$_device->user
                    );
                    self::$_logger->err($msg);
                    // Always throw exception in place of status code since we
                    // won't have a version number before the device is created.
                    throw new Horde_Exception_AuthenticationFailure($msg, $callback_ret);
                } else {
                    // Give the driver a chance to modify device properties.
                    if (is_callable([$this->_driver, 'modifyDeviceCallback'])) {
                        self::$_device = $this->_driver->modifyDeviceCallback(self::$_device);
                    }
                }
            }
        } else {
            self::$_device = $this->_state->loadDeviceInfo($devId, $this->_driver->getUser());

            // If the device state was removed from storage, we may lose the
            // device properties, so try to repopulate what we can. userAgent
            // is ALWAYS available, so if it's missing, the state is gone.
            if (empty(self::$_device->userAgent)) {
                self::$_device->userAgent = $this->_request->getHeader('User-Agent');
                self::$_device->deviceType = !empty($get['DeviceType']) ? $get['DeviceType'] : '';
                self::$_device->user = $this->_driver->getUser();
            }

            if (empty(self::$_device->version)) {
                self::$_device->version = $version;
            }
            if (self::$_device->version < $this->_maxVersion
                && self::$_device->needsVersionUpdate($this->getSupportedVersions())) {
                $this->_needMsRp = true;
            }

            // Give the driver a chance to modify device properties.
            if (is_callable([$this->_driver, 'modifyDeviceCallback'])) {
                self::$_device = $this->_driver->modifyDeviceCallback(self::$_device);
            }
        }

        // Save the device now that we know it is at least allowed to connect,
        // or it has connected successfully at least once in the past.
        self::$_device->save();
        if (is_callable([$this->_driver, 'deviceCallback'])) {
            $callback_ret = $this->_driver->deviceCallback(self::$_device);
            if ($callback_ret !== true) {
                $msg = sprintf(
                    'The device %s was disallowed for user %s per policy settings.',
                    self::$_device->id,
                    self::$_device->user
                );
                self::$_logger->err($msg);
                if ($version > self::VERSION_TWELVEONE) {
                    // Use a status code here, since the device has already
                    // connected.
                    $this->_globalError = $callback_ret;
                    return false;
                } else {
                    throw new Horde_Exception_AuthenticationFailure($msg, $callback_ret);
                }
            }
        }

        // Lastly, check if the device has been set to blocked.
        if (self::$_device->blocked) {
            $msg = sprintf(
                'The device %s was blocked.',
                self::$_device->id
            );
            self::$_logger->err($msg);
            if ($version > self::VERSION_TWELVEONE) {
                $this->_globalError = Horde_ActiveSync_Status::DEVICE_BLOCKED_FOR_USER;
                return false;
            } else {
                throw new Horde_ActiveSync_Exception($msg);
            }
        }

        return true;
    }

    /**
     * Send the MS_Server-ActiveSync header.
     *
     * @return array Returns an array of the headers that were sent.
     *     @since  2.39.0
     */
    public function activeSyncHeader()
    {
        $headers = [
            'Allow: OPTIONS,POST',
            sprintf('Server: Horde_ActiveSync Library v%s', self::LIBRARY_VERSION),
            'Public: OPTIONS,POST',
        ];

        switch ($this->_maxVersion) {
            case self::VERSION_TWOFIVE:
                $headers[] = 'MS-Server-ActiveSync: 6.5.7638.1';
                break;
            case self::VERSION_TWELVE:
                $headers[] = 'MS-Server-ActiveSync: 12.0';
                break;
            case self::VERSION_TWELVEONE:
                $headers[] = 'MS-Server-ActiveSync: 12.1';
                break;
            case self::VERSION_FOURTEEN:
                $headers[] = 'MS-Server-ActiveSync: 14.0';
                break;
            case self::VERSION_FOURTEENONE:
                $headers[] = 'MS-Server-ActiveSync: 14.1';
                break;
            case self::VERSION_SIXTEEN:
                $headers[] = 'MS-Server-ActiveSync: 16.0';
        }

        foreach ($headers as $hdr) {
            header($hdr);
        }

        return $headers;
    }

    /**
     * Send the protocol versions header.
     *
     * @return  string  The header that was sent. @since 2.39.0
     */
    public function versionHeader()
    {
        $hdr = sprintf('MS-ASProtocolVersions: %s', $this->getSupportedVersions());
        header($hdr);

        return $hdr;
    }

    /**
     * Return supported versions in a comma delimited string suitable for
     * sending as the MS-ASProtocolVersions header.
     *
     * @return string
     */
    public function getSupportedVersions()
    {
        return implode(',', array_slice(self::$_supportedVersions, 0, (array_search($this->_maxVersion, self::$_supportedVersions) + 1)));
    }

    /**
     * Send protocol commands header.
     *
     * @return string  The header that was sent. @since 2.39.0
     */
    public function commandsHeader()
    {
        $hdr = sprintf('MS-ASProtocolCommands: %s', $this->getSupportedCommands());
        header($hdr);

        return $hdr;
    }

    /**
     * Return the supported commands in a comma delimited string suitable for
     * sending as the MS-ASProtocolCommands header.
     *
     * @return string
     */
    public function getSupportedCommands()
    {
        switch ($this->_maxVersion) {
            case self::VERSION_TWOFIVE:
                return 'Sync,SendMail,SmartForward,SmartReply,GetAttachment,GetHierarchy,CreateCollection,DeleteCollection,MoveCollection,FolderSync,FolderCreate,FolderDelete,FolderUpdate,MoveItems,GetItemEstimate,MeetingResponse,ResolveRecipients,ValidateCert,Provision,Search,Ping';

            case self::VERSION_TWELVE:
            case self::VERSION_TWELVEONE:
            case self::VERSION_FOURTEEN:
            case self::VERSION_FOURTEENONE:
            case self::VERSION_SIXTEEN:
                return 'Sync,SendMail,SmartForward,SmartReply,GetAttachment,GetHierarchy,CreateCollection,DeleteCollection,MoveCollection,FolderSync,FolderCreate,FolderDelete,FolderUpdate,MoveItems,GetItemEstimate,MeetingResponse,Search,Settings,Ping,ItemOperations,Provision,ResolveRecipients,ValidateCert';
        }
    }

    /**
     * Send provision header
     */
    public function provisionHeader()
    {
        header('HTTP/1.1 449 Retry after sending a PROVISION command');
    }

    /**
     * Obtain the policy key header from the request.
     *
     * @return integer  The policy key or '0' if not set.
     */
    public function getPolicyKey()
    {
        // Policy key can come from header or encoded request parameters.
        $this->_policykey = $this->_request->getHeader('X-MS-PolicyKey');
        if (empty($this->_policykey)) {
            $get = $this->getGetVars();
            if (!empty($get['PolicyKey'])) {
                $this->_policykey = $get['PolicyKey'];
            } else {
                $this->_policykey = 0;
            }
        }

        return $this->_policykey;
    }

    /**
     * Obtain the ActiveSync protocol version requested by the client headers.
     *
     * @return string  The EAS version requested by the client.
     */
    public function getProtocolVersion()
    {
        if (!isset(self::$_version)) {
            self::$_version = $this->_request->getHeader('MS-ASProtocolVersion');
            if (empty(self::$_version)) {
                $get = $this->getGetVars();
                self::$_version = empty($get['ProtVer']) ? '1.0' : $get['ProtVer'];
            }
        }
        return self::$_version;
    }

    /**
     * Return the GET variables passed from the device, decoding from
     * base64 if needed.
     *
     * @return array  A hash of get variables => values.
     */
    public function getGetVars()
    {
        if (!empty($this->_get)) {
            return $this->_get;
        }

        $results = [];
        $get = $this->_request->getGetVars();

        // Do we need to decode the request parameters?
        if (!isset($get['Cmd']) && !isset($get['DeviceId']) && !isset($get['DeviceType'])) {
            $serverVars = $this->_request->getServerVars();
            if (isset($serverVars['QUERY_STRING']) && strlen($serverVars['QUERY_STRING']) >= 10) {
                $results = Horde_ActiveSync_Utils::decodeBase64($serverVars['QUERY_STRING']);
                // Normalize values.
                switch ($results['DeviceType']) {
                    case 'PPC':
                        $results['DeviceType'] = 'PocketPC';
                        break;
                    case 'SP':
                        $results['DeviceType'] = 'SmartPhone';
                        break;
                    case 'WP':
                    case 'WP8':
                        $results['DeviceType'] = 'WindowsPhone';
                        break;
                    case 'android':
                    case 'android40':
                        $results['DeviceType'] = 'android';
                }
                $this->_get = $results;
            }
        } else {
            $this->_get = $get;
        }

        return $this->_get;
    }

    /**
     * Return any global errors that occured during initial connection.
     *
     * @since 2.4.0
     * @return mixed  A Horde_ActiveSync_Status:: constant of boolean false if
     *                no errors.
     */
    public function checkGlobalError()
    {
        return $this->_globalError;
    }

    /**
     * Send the content type header.
     *
     */
    public function contentTypeHeader($content_type = null)
    {
        if (!empty($content_type)) {
            header('Content-Type: ' . $content_type);
            return;
        }
        if ($this->_multipart) {
            header('Content-Type: application/vnd.ms-sync.multipart');
        } else {
            header('Content-Type: application/vnd.ms-sync.wbxml');
        }
    }

    /**
     * Send the OPTIONS request response headers.
     */
    protected function _handleOptionsRequest()
    {
        $as_headers = implode("\r\n", $this->activeSyncHeader());
        $version_header = $this->versionHeader();
        $cmd_header = $this->commandsHeader();
        self::$_logger->meta(
            sprintf(
                "Returning OPTIONS response:\r\n%s\r\n%s\r\n%s",
                $as_headers,
                $version_header,
                $cmd_header
            )
        );
    }

    /**
     * Return the number of bytes corresponding to the requested trunction
     * constant. This applies to MIMETRUNCATION only.
     *
     * @param integer $truncation  The constant.
     *
     * @return integer|boolean  Either the size, in bytes, to truncate or
     *                          falso if no truncation.
     * @since 2.20.0
     */
    public static function getMIMETruncSize($truncation)
    {
        switch ($truncation) {
            case Horde_ActiveSync::TRUNCATION_ALL:
                return 0;
            case Horde_ActiveSync::TRUNCATION_1:
                return 4096;
            case Horde_ActiveSync::TRUNCATION_2:
                return 5120;
            case Horde_ActiveSync::TRUNCATION_3:
                return 7168;
            case Horde_ActiveSync::TRUNCATION_4:
                return 10240;
            case Horde_ActiveSync::TRUNCATION_5:
                return 20480;
            case Horde_ActiveSync::TRUNCATION_6:
                return 51200;
            case Horde_ActiveSync::TRUNCATION_7:
                return 102400;
            case Horde_ActiveSync::TRUNCATION_8:
                return false;
            default:
                return 1024; // Default to 1Kb
        }
    }

    /**
     * Return the number of bytes corresponding to the requested trunction
     * constant.
     *
     * @param integer $truncation  The constant.
     *
     * @return integer|boolean  Either the size, in bytes, to truncate or
     *                          falso if no truncation.
     *
     */
    public static function getTruncSize($truncation)
    {
        switch ($truncation) {
            case Horde_ActiveSync::TRUNCATION_ALL:
                return 0;
            case Horde_ActiveSync::TRUNCATION_1:
                return 512;
            case Horde_ActiveSync::TRUNCATION_2:
                return 1024;
            case Horde_ActiveSync::TRUNCATION_3:
                return 2048;
            case Horde_ActiveSync::TRUNCATION_4:
                return 5120;
            case Horde_ActiveSync::TRUNCATION_5:
                return 10240;
            case Horde_ActiveSync::TRUNCATION_6:
                return 20480;
            case Horde_ActiveSync::TRUNCATION_7:
                return 51200;
            case Horde_ActiveSync::TRUNCATION_8:
                return 102400;
            case Horde_ActiveSync::TRUNCATION_9:
            case Horde_ActiveSync::TRUNCATION_NONE: // @deprecated
                return false;
            default:
                return 1024; // Default to 1Kb
        }
    }

}
