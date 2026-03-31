<?php

/**
 * Horde_ActiveSync_Request_Settings::
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
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Handle Settings requests.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2009-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 * @internal
 */
class Horde_ActiveSync_Request_Settings extends Horde_ActiveSync_Request_Base
{
    /** Wbxml constants **/
    public const SETTINGS_SETTINGS                 = 'Settings:Settings';
    public const SETTINGS_STATUS                   = 'Settings:Status';
    public const SETTINGS_GET                      = 'Settings:Get';
    public const SETTINGS_SET                      = 'Settings:Set';
    public const SETTINGS_OOF                      = 'Settings:Oof';
    public const SETTINGS_OOFSTATE                 = 'Settings:OofState';
    public const SETTINGS_STARTTIME                = 'Settings:StartTime';
    public const SETTINGS_ENDTIME                  = 'Settings:EndTime';
    public const SETTINGS_OOFMESSAGE               = 'Settings:OofMessage';
    public const SETTINGS_APPLIESTOINTERNAL        = 'Settings:AppliesToInternal';
    public const SETTINGS_APPLIESTOEXTERNALKNOWN   = 'Settings:AppliesToExternalKnown';
    public const SETTINGS_APPLIESTOEXTERNALUNKNOWN = 'Settings:AppliesToExternalUnknown';
    public const SETTINGS_ENABLED                  = 'Settings:Enabled';
    public const SETTINGS_REPLYMESSAGE             = 'Settings:ReplyMessage';
    public const SETTINGS_BODYTYPE                 = 'Settings:BodyType';
    public const SETTINGS_DEVICEPASSWORD           = 'Settings:DevicePassword';
    public const SETTINGS_PASSWORD                 = 'Settings:Password';
    public const SETTINGS_DEVICEINFORMATION        = 'Settings:DeviceInformation';
    public const SETTINGS_MODEL                    = 'Settings:Model';
    public const SETTINGS_IMEI                     = 'Settings:IMEI';
    public const SETTINGS_FRIENDLYNAME             = 'Settings:FriendlyName';
    public const SETTINGS_OS                       = 'Settings:OS';
    public const SETTINGS_OSLANGUAGE               = 'Settings:OSLanguage';
    public const SETTINGS_PHONENUMBER              = 'Settings:PhoneNumber';
    public const SETTINGS_USERINFORMATION          = 'Settings:UserInformation';
    public const SETTINGS_EMAILADDRESSES           = 'Settings:EmailAddresses';
    public const SETTINGS_SMTPADDRESS              = 'Settings:SmtpAddress';
    public const SETTINGS_USERAGENT                = 'Settings:UserAgent';

    /** EAS 14.0 **/
    public const SETTINGS_ENABLEOUTBOUNDSMS        = 'Settings:EnableOutboundSMS';
    public const SETTINGS_MOBILEOPERATOR           = 'Settings:MobileOperator';

    /** EAS 14.1 **/
    public const SETTINGS_PRIMARYSMTPADDRESS       = 'Settings:PrimarySmtpAddress';
    public const SETTINGS_ACCOUNTS                 = 'Settings:Accounts';
    public const SETTINGS_ACCOUNT                  = 'Settings:Account';
    public const SETTINGS_ACCOUNTID                = 'Settings:AccountId';
    public const SETTINGS_USERDISPLAYNAME          = 'Settings:UserDisplayName';
    public const SETTINGS_RIGHTSMANAGEMENTINFO     = 'Settings:RightsManagementInformation';
    public const SETTINGS_ACCOUNTNAME              = 'Settings:AccountName';


    /** Status codes **/
    public const STATUS_SUCCESS                    = 1;
    public const STATUS_ERROR                      = 2;
    public const STATUS_UNAVAILABLE                = 4;

    /** Out of office constants **/
    public const OOF_STATE_TIMEBASED               = 2;
    // @todo - this is called OOF_STATE_GLOBAL in the docs
    public const OOF_STATE_ENABLED                 = 1;
    public const OOF_STATE_DISABLED                = 0;


    /**
     * Handle the request.
     *
     * @see Horde_ActiveSync_Request_Base::_handle()
     */
    protected function _handle()
    {
        if (!$this->_decoder->getElementStartTag(self::SETTINGS_SETTINGS)) {
            throw new Horde_ActiveSync_Exception('Protocol Error');
        }

        $version = $this->_device->version;

        $request = [];
        while (($reqtype = ($this->_decoder->getElementStartTag(self::SETTINGS_OOF) ? self::SETTINGS_OOF
               : ($this->_decoder->getElementStartTag(self::SETTINGS_DEVICEINFORMATION) ? self::SETTINGS_DEVICEINFORMATION
               : ($this->_decoder->getElementStartTag(self::SETTINGS_USERINFORMATION) ? self::SETTINGS_USERINFORMATION
               : ($this->_decoder->getElementStartTag(self::SETTINGS_DEVICEPASSWORD) ? self::SETTINGS_DEVICEPASSWORD
               : ($this->_decoder->getElementStartTag(self::SETTINGS_RIGHTSMANAGEMENTINFO) ? self::SETTINGS_RIGHTSMANAGEMENTINFO
               : -1)))))) != -1) {

            while (($querytype = ($this->_decoder->getElementStartTag(self::SETTINGS_GET) ? self::SETTINGS_GET
                   : ($this->_decoder->getElementStartTag(self::SETTINGS_SET) ? self::SETTINGS_SET
                   : -1))) != -1) {

                switch ($querytype) {
                    case self::SETTINGS_GET:
                        switch ($reqtype) {
                            case self::SETTINGS_OOF:
                                $oof = Horde_ActiveSync::messageFactory('Oof');
                                $oof->decodeStream($this->_decoder);
                                $request['get']['oof']['bodytype'] = $oof->bodytype;
                                $this->_decoder->getElementEndTag(); // SETTINGS_GET
                                break;
                            case self::SETTINGS_USERINFORMATION:
                                // These are empty <GET /> tags.
                                $request['get']['userinformation'] = [];
                                $this->_decoder->getElementContent();
                                break;
                            case self::SETTINGS_RIGHTSMANAGEMENTINFO:
                                // These are empty <GET /> tags.
                                $request['get']['rightsmanagementinfo'] = true;
                                $this->_decoder->getElementContent();
                                break;
                        }
                        break;

                    case self::SETTINGS_SET:
                        switch ($reqtype) {
                            case self::SETTINGS_OOF:
                                $oof = Horde_ActiveSync::messageFactory('Oof');
                                $oof->decodeStream($this->_decoder);

                                $request['set']['oof']['oofstate'] = $oof->state;
                                $request['set']['oof']['starttime'] = $oof->starttime;
                                $request['set']['oof']['endtime'] = $oof->endtime;
                                $request['set']['oof']['oofmsgs'] = [];
                                foreach ($oof->messages as $msg) {
                                    $message = [];
                                    $message['appliesto'] = !empty($msg->internal)
                                        ? Horde_ActiveSync_Request_Settings::SETTINGS_APPLIESTOINTERNAL
                                        : (!empty($msg->externalknown)
                                            ? Horde_ActiveSync_Request_Settings::SETTINGS_APPLIESTOEXTERNALKNOWN
                                            : Horde_ActiveSync_Request_Settings::SETTINGS_APPLIESTOEXTERNALUNKNOWN);
                                    $message['enabled'] = $msg->enabled;
                                    $message['replymessage'] = $msg->reply;
                                    $message['bodytype'] = $msg->bodytype;
                                    $request['set']['oof']['oofmsgs'][] = $message;
                                }
                                break;
                            case self::SETTINGS_DEVICEINFORMATION:
                                // @TODO Clean the return values up when we can break bc.
                                $device_properties = $this->_device->properties;
                                $settings = Horde_ActiveSync::messageFactory('DeviceInformation');
                                $settings->decodeStream($this->_decoder);
                                $device_properties[self::SETTINGS_MODEL] = $settings->model;
                                $device_properties[self::SETTINGS_IMEI] = $settings->imei;
                                $device_properties[self::SETTINGS_FRIENDLYNAME] = $settings->friendlyname;
                                $device_properties[self::SETTINGS_OS] = $settings->os;
                                $device_properties[self::SETTINGS_OSLANGUAGE] = $settings->oslanguage;
                                $device_properties[self::SETTINGS_PHONENUMBER] = $settings->phonenumber;
                                $device_properties[self::SETTINGS_USERAGENT] = $settings->useragent;
                                $device_properties[self::SETTINGS_MOBILEOPERATOR] = $settings->mobileoperator;
                                $device_properties[self::SETTINGS_ENABLEOUTBOUNDSMS] = $settings->enableoutboundsms;

                                try {
                                    $device_properties['version'] = $version;
                                    $this->_device->setDeviceProperties($device_properties);
                                    $this->_device->save();
                                } catch (Horde_ActiveSync_Exception $e) {
                                    $this->_logger->err($e->getMessage());
                                    unset($device_properties);
                                }
                                break;
                            case self::SETTINGS_DEVICEPASSWORD:
                                $this->_decoder->getElementStartTag(self::SETTINGS_PASSWORD);
                                if (($password = $this->_decoder->getElementContent()) !== false) {
                                    $this->_decoder->getElementEndTag(); // end $field
                                }
                                $request['set']['devicepassword'] = $password;
                                break;
                        }

                        $this->_decoder->getElementEndTag(); // SETTINGS_SET
                        break;
                }
            }
            // SETTINGS_OOF || SETTINGS_DEVICEPW || SETTINGS_DEVICEINFORMATION || SETTINGS_USERINFORMATION
            $this->_decoder->getElementEndTag();
        }

        $this->_decoder->getElementEndTag(); // SETTINGS

        // Tell the backend
        $result = [];
        if (isset($request['set'])) {
            $result['set'] = $this->_driver->setSettings($request['set'], $this->_device->id);
        }
        if (isset($request['get'])) {
            $result['get'] = $this->_driver->getSettings($request['get'], $this->_device->id);
        }

        // Output response
        $encoder = $this->_encoder;
        $encoder->startWBXML();
        $encoder->startTag(self::SETTINGS_SETTINGS);
        $encoder->startTag(self::SETTINGS_STATUS);
        $encoder->content(self::STATUS_SUCCESS);
        $encoder->endTag(); // end self::SETTINGS_STATUS
        if (isset($request['set']['oof'])) {
            $encoder->startTag(self::SETTINGS_OOF);
            $encoder->startTag(self::SETTINGS_STATUS);
            if (!isset($result['set']['oof'])) {
                $encoder->content(self::OOF_STATE_DISABLED);
            } else {
                $encoder->content($result['set']['oof']);
            }
            $encoder->endTag(); // end self::SETTINGS_STATUS
            $encoder->endTag(); // end self::SETTINGS_OOF
        }
        if (isset($device_properties)) {
            $encoder->startTag(self::SETTINGS_DEVICEINFORMATION);
            $encoder->startTag(self::SETTINGS_STATUS);
            $encoder->content(Horde_ActiveSync_Request_Settings::STATUS_SUCCESS);
            $encoder->endTag(); // end self::SETTINGS_STATUS
            $encoder->endTag(); // end self::SETTINGS_DEVICEINFORMATION
        }
        if (isset($request['set']['devicepassword'])) {
            $encoder->startTag(self::SETTINGS_DEVICEPASSWORD);
            $encoder->startTag(self::SETTINGS_STATUS);
            if (!isset($result['set']['devicepassword'])) {
                $encoder->content(0);
            } else {
                $encoder->content($result['set']['devicepassword']);
            }
            $encoder->endTag(); // end self::SETTINGS_STATUS
            $encoder->endTag(); // end self::SETTINGS_DEVICEPASSWORD
        }
        if ($version >= Horde_ActiveSync::VERSION_TWELVE
            && isset($request['get']['userinformation'])
            && isset($result['get']['userinformation'])) {
            $encoder->startTag(self::SETTINGS_USERINFORMATION);
            $encoder->startTag(self::SETTINGS_STATUS);
            $encoder->content($result['get']['userinformation']['status']);
            $encoder->endTag(); // end self::SETTINGS_STATUS
            $encoder->startTag(self::SETTINGS_GET);

            // @todo remove accounts existence check for H6.
            if ($version >= Horde_ActiveSync::VERSION_FOURTEENONE) {
                if (!empty($result['get']['userinformation']['accounts'])) {
                    $encoder->startTag(self::SETTINGS_ACCOUNTS);
                    foreach ($result['get']['userinformation']['accounts'] as $account) {
                        $encoder->startTag(self::SETTINGS_ACCOUNT);

                        if (!empty($account['id'])) {
                            $encoder->startTag(self::SETTINGS_ACCOUNTID);
                            $encoder->content($account['id']);
                            $encoder->endTag();
                        }
                        if (!empty($account['accountname'])) {
                            $encoder->startTag(self::SETTINGS_ACCOUNTNAME);
                            $encoder->content($account['accountname']);
                            $encoder->endTag();
                        }
                        if (!empty($account['fullname'])) {
                            $encoder->startTag(self::SETTINGS_USERDISPLAYNAME);
                            $encoder->content($account['fullname']);
                            $encoder->endTag();
                        }
                        if (!empty($account['emailaddresses'])) {
                            $encoder->startTag(self::SETTINGS_EMAILADDRESSES);
                            $encoder->startTag(self::SETTINGS_PRIMARYSMTPADDRESS);
                            $encoder->content($account['emailaddresses'][0]);
                            $encoder->endTag(); // end self::SETTINGS_PRIMARYSMTPADDRESS
                            foreach ($account['emailaddresses'] as $value) {
                                $encoder->startTag(self::SETTINGS_SMTPADDRESS);
                                $encoder->content($value);
                                $encoder->endTag(); // end self::SETTINGS_SMTPADDRESS
                            }
                            $encoder->endTag(); // SETTINGS_EMAILADDRESSES
                        }
                        $encoder->endTag(); // SETTINGS_ACCOUNT
                    }
                    $encoder->endTag(); // SETTINGS_ACCOUNTS
                }
            } else { // EAS 12.0, 12.1, 14.0
                $encoder->startTag(self::SETTINGS_EMAILADDRESSES);
                if (!empty($result['get']['userinformation']['emailaddresses'])) {
                    foreach ($result['get']['userinformation']['emailaddresses'] as $value) {
                        $encoder->startTag(self::SETTINGS_SMTPADDRESS);
                        $encoder->content($value);
                        $encoder->endTag(); // end self::SETTINGS_SMTPADDRESS
                    }
                }
                $encoder->endTag(); // end self::SETTINGS_EMAILADDRESSES
            }
            $encoder->endTag(); // end self::SETTINGS_GET
            $encoder->endTag(); // end self::SETTINGS_USERINFORMATION
        }
        if (isset($request['get']['oof'])) {
            $oof = $this->_getOofObject($result['get']['oof']);
            $encoder->startTag(self::SETTINGS_OOF);

            $encoder->startTag(self::SETTINGS_STATUS);
            $encoder->content($result['get']['oof']['status']);
            $encoder->endTag(); // end self::SETTINGS_STATUS

            if ($result['get']['oof']['status'] == self::STATUS_SUCCESS) {
                $encoder->startTag(self::SETTINGS_GET);
                $oof->encodeStream($encoder);
                $encoder->endTag(); // end self::SETTINGS_GET
            }

            $encoder->endTag(); // end self::SETTINGS_OOF
        }
        if (isset($request['get']['rightsmanagementinfo'])) {
            $encoder->startTag(self::SETTINGS_RIGHTSMANAGEMENTINFO);
            $encoder->startTag(self::SETTINGS_STATUS);
            $encoder->content(self::STATUS_SUCCESS);
            $encoder->endTag();
            $encoder->endTag();
        }

        $encoder->endTag(); // end self::SETTINGS_SETTINGS

        return true;
    }

    /**
     * @todo remove for H6 when driver methods always return EAS objects.
     */
    protected function _getOofObject($info)
    {
        $info = new Horde_Support_Array($info);
        $oof = Horde_ActiveSync::messageFactory('Oof');
        $oof->state = $info['oofstate'];
        if (!empty($info['starttime'])) {
            $oof->starttime = new Horde_Date($info['starttime']);
            $oof->endtime = new Horde_Date($info['endtime']);
        }
        $msg = Horde_ActiveSync::messageFactory('OofMessage');
        $msg->internal = '';
        $msg->enabled = $info['oofmsgs'][0]['enabled'] ? 1 : "0";
        $msg->reply = $info['oofmsgs'][0]['replymessage'];
        $msg->bodytype = 'text';
        $oof->messages[] = $msg;

        return $oof;
    }

}
