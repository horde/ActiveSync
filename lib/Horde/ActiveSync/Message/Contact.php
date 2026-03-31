<?php

/**
 * Horde_ActiveSync_Message_Contact::
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
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Horde_ActiveSync_Message_Contact::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 *
 * @property Horde_Date   $anniversary
 * @property string   $assistantname
 * @property string   $assistnamephonenumber
 * @property Horde_Date   $birthday
 * @property string   $business2phonenumber
 * @property string   $businesscity
 * @property string   $businesscountry
 * @property string   $businesspostalcode
 * @property string   $businessstate
 * @property string   $businessstreet
 * @property string   $businessfaxnumber
 * @property string   $businessphonenumber
 * @property string   $carphonenumber
 * @property array   $categories
 * @property array   $children
 * @property string   $companyname
 * @property string   $department
 * @property string   $email1address
 * @property string   $email2address
 * @property string   $email3address
 * @property string   $fileas
 * @property string   $firstname
 * @property string   $home2phonenumber
 * @property string   $homecity
 * @property string   $homecountry
 * @property string   $homepostalcode
 * @property string   $homestate
 * @property string   $homestreet
 * @property string   $homefaxnumber
 * @property string   $homephonenumber
 * @property string   $jobtitle
 * @property string   $lastname
 * @property string   $middlename
 * @property string   $mobilephonenumber
 * @property string   $officelocation
 * @property string   $othercity
 * @property string   $othercountry
 * @property string   $otherpostalcode
 * @property string   $otherstate
 * @property string   $otherstreet
 * @property string   $pagernumber
 * @property string   $radiophonenumber
 * @property string   $spouse
 * @property string   $suffix
 * @property string   $title
 * @property string   $webpage
 * @property string   $yomicompanyname
 * @property string   $yomifirstname
 * @property string   $yomilastname
 * @property string   $picture
 * @property string   $customerid
 * @property string   $governmentid
 * @property string   $imaddress
 * @property string   $imaddress2
 * @property string   $imaddress3
 * @property string   $managername
 * @property string   $companymainphone
 * @property string   $accountname
 * @property string   $nickname
 * @property string   $mms
 * @property string   $alias (EAS >= 14.0 only)
 * @property string   $weightedrank (EAS >= 14.0 only)
 * @property string   $body (EAS 2.5 only)
 * @property integer   $bodysize (EAS 2.5 only)
 * @property integer   $bodytruncated (EAS 2.5 only)
 * @property integer   $rtf (EAS 2.5 only)
 * @property Horde_ActiveSync_Message_AirSyncBaseBody   $airsyncbasebody (EAS >= 12.0 only)
 */
class Horde_ActiveSync_Message_Contact extends Horde_ActiveSync_Message_Base
{
    /* POOMCONTACTS */
    public const ANNIVERSARY           = 'POOMCONTACTS:Anniversary';
    public const ASSISTANTNAME         = 'POOMCONTACTS:AssistantName';
    public const ASSISTNAMEPHONENUMBER = 'POOMCONTACTS:AssistnamePhoneNumber';
    public const BIRTHDAY              = 'POOMCONTACTS:Birthday';
    public const BODY                  = 'POOMCONTACTS:Body';
    public const BODYSIZE              = 'POOMCONTACTS:BodySize';
    public const BODYTRUNCATED         = 'POOMCONTACTS:BodyTruncated';
    public const BUSINESS2PHONENUMBER  = 'POOMCONTACTS:Business2PhoneNumber';
    public const BUSINESSCITY          = 'POOMCONTACTS:BusinessCity';
    public const BUSINESSCOUNTRY       = 'POOMCONTACTS:BusinessCountry';
    public const BUSINESSPOSTALCODE    = 'POOMCONTACTS:BusinessPostalCode';
    public const BUSINESSSTATE         = 'POOMCONTACTS:BusinessState';
    public const BUSINESSSTREET        = 'POOMCONTACTS:BusinessStreet';
    public const BUSINESSFAXNUMBER     = 'POOMCONTACTS:BusinessFaxNumber';
    public const BUSINESSPHONENUMBER   = 'POOMCONTACTS:BusinessPhoneNumber';
    public const CARPHONENUMBER        = 'POOMCONTACTS:CarPhoneNumber';
    public const CATEGORIES            = 'POOMCONTACTS:Categories';
    public const CATEGORY              = 'POOMCONTACTS:Category';
    public const CHILDREN              = 'POOMCONTACTS:Children';
    public const CHILD                 = 'POOMCONTACTS:Child';
    public const COMPANYNAME           = 'POOMCONTACTS:CompanyName';
    public const DEPARTMENT            = 'POOMCONTACTS:Department';
    public const EMAIL1ADDRESS         = 'POOMCONTACTS:Email1Address';
    public const EMAIL2ADDRESS         = 'POOMCONTACTS:Email2Address';
    public const EMAIL3ADDRESS         = 'POOMCONTACTS:Email3Address';
    public const FILEAS                = 'POOMCONTACTS:FileAs';
    public const FIRSTNAME             = 'POOMCONTACTS:FirstName';
    public const HOME2PHONENUMBER      = 'POOMCONTACTS:Home2PhoneNumber';
    public const HOMECITY              = 'POOMCONTACTS:HomeCity';
    public const HOMECOUNTRY           = 'POOMCONTACTS:HomeCountry';
    public const HOMEPOSTALCODE        = 'POOMCONTACTS:HomePostalCode';
    public const HOMESTATE             = 'POOMCONTACTS:HomeState';
    public const HOMESTREET            = 'POOMCONTACTS:HomeStreet';
    public const HOMEFAXNUMBER         = 'POOMCONTACTS:HomeFaxNumber';
    public const HOMEPHONENUMBER       = 'POOMCONTACTS:HomePhoneNumber';
    public const JOBTITLE              = 'POOMCONTACTS:JobTitle';
    public const LASTNAME              = 'POOMCONTACTS:LastName';
    public const MIDDLENAME            = 'POOMCONTACTS:MiddleName';
    public const MOBILEPHONENUMBER     = 'POOMCONTACTS:MobilePhoneNumber';
    public const OFFICELOCATION        = 'POOMCONTACTS:OfficeLocation';
    public const OTHERCITY             = 'POOMCONTACTS:OtherCity';
    public const OTHERCOUNTRY          = 'POOMCONTACTS:OtherCountry';
    public const OTHERPOSTALCODE       = 'POOMCONTACTS:OtherPostalCode';
    public const OTHERSTATE            = 'POOMCONTACTS:OtherState';
    public const OTHERSTREET           = 'POOMCONTACTS:OtherStreet';
    public const PAGERNUMBER           = 'POOMCONTACTS:PagerNumber';
    public const RADIOPHONENUMBER      = 'POOMCONTACTS:RadioPhoneNumber';
    public const SPOUSE                = 'POOMCONTACTS:Spouse';
    public const SUFFIX                = 'POOMCONTACTS:Suffix';
    public const TITLE                 = 'POOMCONTACTS:Title';
    public const WEBPAGE               = 'POOMCONTACTS:WebPage';
    public const YOMICOMPANYNAME       = 'POOMCONTACTS:YomiCompanyName';
    public const YOMIFIRSTNAME         = 'POOMCONTACTS:YomiFirstName';
    public const YOMILASTNAME          = 'POOMCONTACTS:YomiLastName';
    public const RTF                   = 'POOMCONTACTS:Rtf';
    public const PICTURE               = 'POOMCONTACTS:Picture';

    /* POOMCONTACTS2 */
    public const CUSTOMERID            = 'POOMCONTACTS2:CustomerId';
    public const GOVERNMENTID          = 'POOMCONTACTS2:GovernmentId';
    public const IMADDRESS             = 'POOMCONTACTS2:IMAddress';
    public const IMADDRESS2            = 'POOMCONTACTS2:IMAddress2';
    public const IMADDRESS3            = 'POOMCONTACTS2:IMAddress3';
    public const MANAGERNAME           = 'POOMCONTACTS2:ManagerName';
    public const COMPANYMAINPHONE      = 'POOMCONTACTS2:CompanyMainPhone';
    public const ACCOUNTNAME           = 'POOMCONTACTS2:AccountName';
    public const NICKNAME              = 'POOMCONTACTS2:NickName';
    public const MMS                   = 'POOMCONTACTS2:MMS';

    /* EAS 14 (Only used in Recipient Information Cache responses) */
    public const ALIAS                 = 'POOMCONTACTS:Alias';
    public const WEIGHTEDRANK          = 'POOMCONTACTS:WeightedRank';

    public $categories = [];

    /**
     * Property mapping.
     *
     * @var array
     */
    protected $_mapping = [
        self::ANNIVERSARY           => [self::KEY_ATTRIBUTE => 'anniversary', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        self::BIRTHDAY              => [self::KEY_ATTRIBUTE => 'birthday', self::KEY_TYPE => self::TYPE_DATE_DASHES],
        self::WEBPAGE               => [self::KEY_ATTRIBUTE => 'webpage'],
        self::CHILDREN              => [self::KEY_ATTRIBUTE => 'children', self::KEY_VALUES => self::CHILD],
        self::BUSINESSCOUNTRY       => [self::KEY_ATTRIBUTE => 'businesscountry'],
        self::DEPARTMENT            => [self::KEY_ATTRIBUTE => 'department'],
        self::EMAIL1ADDRESS         => [self::KEY_ATTRIBUTE => 'email1address'],
        self::EMAIL2ADDRESS         => [self::KEY_ATTRIBUTE => 'email2address'],
        self::EMAIL3ADDRESS         => [self::KEY_ATTRIBUTE => 'email3address'],
        self::BUSINESSFAXNUMBER     => [self::KEY_ATTRIBUTE => 'businessfaxnumber'],
        self::FILEAS                => [self::KEY_ATTRIBUTE => 'fileas'],
        self::FIRSTNAME             => [self::KEY_ATTRIBUTE => 'firstname'],
        self::HOMECITY              => [self::KEY_ATTRIBUTE => 'homecity'],
        self::HOMECOUNTRY           => [self::KEY_ATTRIBUTE => 'homecountry'],
        self::HOMEFAXNUMBER         => [self::KEY_ATTRIBUTE => 'homefaxnumber'],
        self::HOMEPHONENUMBER       => [self::KEY_ATTRIBUTE => 'homephonenumber'],
        self::HOME2PHONENUMBER      => [self::KEY_ATTRIBUTE => 'home2phonenumber'],
        self::HOMEPOSTALCODE        => [self::KEY_ATTRIBUTE => 'homepostalcode'],
        self::HOMESTATE             => [self::KEY_ATTRIBUTE => 'homestate'],
        self::HOMESTREET            => [self::KEY_ATTRIBUTE => 'homestreet'],
        self::BUSINESSCITY          => [self::KEY_ATTRIBUTE => 'businesscity'],
        self::MIDDLENAME            => [self::KEY_ATTRIBUTE => 'middlename'],
        self::MOBILEPHONENUMBER     => [self::KEY_ATTRIBUTE => 'mobilephonenumber'],
        self::SUFFIX                => [self::KEY_ATTRIBUTE => 'suffix'],
        self::COMPANYNAME           => [self::KEY_ATTRIBUTE => 'companyname'],
        self::OTHERCITY             => [self::KEY_ATTRIBUTE => 'othercity'],
        self::OTHERCOUNTRY          => [self::KEY_ATTRIBUTE => 'othercountry'],
        self::CARPHONENUMBER        => [self::KEY_ATTRIBUTE => 'carphonenumber'],
        self::OTHERPOSTALCODE       => [self::KEY_ATTRIBUTE => 'otherpostalcode'],
        self::OTHERSTATE            => [self::KEY_ATTRIBUTE => 'otherstate'],
        self::OTHERSTREET           => [self::KEY_ATTRIBUTE => 'otherstreet'],
        self::PAGERNUMBER           => [self::KEY_ATTRIBUTE => 'pagernumber'],
        self::TITLE                 => [self::KEY_ATTRIBUTE => 'title'],
        self::BUSINESSPOSTALCODE    => [self::KEY_ATTRIBUTE => 'businesspostalcode'],
        self::ASSISTANTNAME         => [self::KEY_ATTRIBUTE => 'assistantname'],
        self::ASSISTNAMEPHONENUMBER => [self::KEY_ATTRIBUTE => 'assistnamephonenumber'],
        self::LASTNAME              => [self::KEY_ATTRIBUTE => 'lastname'],
        self::SPOUSE                => [self::KEY_ATTRIBUTE => 'spouse'],
        self::BUSINESSSTATE         => [self::KEY_ATTRIBUTE => 'businessstate'],
        self::BUSINESSSTREET        => [self::KEY_ATTRIBUTE => 'businessstreet'],
        self::BUSINESSPHONENUMBER   => [self::KEY_ATTRIBUTE => 'businessphonenumber'],
        self::BUSINESS2PHONENUMBER  => [self::KEY_ATTRIBUTE => 'business2phonenumber'],
        self::JOBTITLE              => [self::KEY_ATTRIBUTE => 'jobtitle'],
        self::YOMIFIRSTNAME         => [self::KEY_ATTRIBUTE => 'yomifirstname'],
        self::YOMILASTNAME          => [self::KEY_ATTRIBUTE => 'yomilastname'],
        self::YOMICOMPANYNAME       => [self::KEY_ATTRIBUTE => 'yomicompanyname'],
        self::OFFICELOCATION        => [self::KEY_ATTRIBUTE => 'officelocation'],
        self::RADIOPHONENUMBER      => [self::KEY_ATTRIBUTE => 'radiophonenumber'],
        self::CATEGORIES            => [self::KEY_ATTRIBUTE => 'categories', self::KEY_VALUES => self::CATEGORY],
        self::PICTURE               => [self::KEY_ATTRIBUTE => 'picture'],

        // POOMCONTACTS2
        self::CUSTOMERID            => [self::KEY_ATTRIBUTE => 'customerid'],
        self::GOVERNMENTID          => [self::KEY_ATTRIBUTE => 'governmentid'],
        self::IMADDRESS             => [self::KEY_ATTRIBUTE => 'imaddress'],
        self::IMADDRESS2            => [self::KEY_ATTRIBUTE => 'imaddress2'],
        self::IMADDRESS3            => [self::KEY_ATTRIBUTE => 'imaddress3'],
        self::MANAGERNAME           => [self::KEY_ATTRIBUTE => 'managername'],
        self::COMPANYMAINPHONE      => [self::KEY_ATTRIBUTE => 'companymainphone'],
        self::ACCOUNTNAME           => [self::KEY_ATTRIBUTE => 'accountname'],
        self::NICKNAME              => [self::KEY_ATTRIBUTE => 'nickname'],
        self::MMS                   => [self::KEY_ATTRIBUTE => 'mms'],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'anniversary'           => false,
        'assistantname'         => false,
        'assistnamephonenumber' => false,
        'birthday'              => false,
        'business2phonenumber'  => false,
        'businesscity'          => false,
        'businesscountry'       => false,
        'businesspostalcode'    => false,
        'businessstate'         => false,
        'businessstreet'        => false,
        'businessfaxnumber'     => false,
        'businessphonenumber'   => false,
        'carphonenumber'        => false,
        'children'              => [],
        'companyname'           => false,
        'department'            => false,
        'email1address'         => false,
        'email2address'         => false,
        'email3address'         => false,
        'fileas'                => false,
        'firstname'             => false,
        'home2phonenumber'      => false,
        'homecity'              => false,
        'homecountry'           => false,
        'homepostalcode'        => false,
        'homestate'             => false,
        'homestreet'            => false,
        'homefaxnumber'         => false,
        'homephonenumber'       => false,
        'jobtitle'              => false,
        'lastname'              => false,
        'middlename'            => false,
        'mobilephonenumber'     => false,
        'officelocation'        => false,
        'othercity'             => false,
        'othercountry'          => false,
        'otherpostalcode'       => false,
        'otherstate'            => false,
        'otherstreet'           => false,
        'pagernumber'           => false,
        'radiophonenumber'      => false,
        'spouse'                => false,
        'suffix'                => false,
        'title'                 => false,
        'webpage'               => false,
        'yomicompanyname'       => false,
        'yomifirstname'         => false,
        'yomilastname'          => false,
        'picture'               => false,
        'categories'            => false,

        // POOMCONTACTS2
        'customerid'            => false,
        'governmentid'          => false,
        'imaddress'             => false,
        'imaddress2'            => false,
        'imaddress3'            => false,
        'managername'           => false,
        'companymainphone'      => false,
        'accountname'           => false,
        'nickname'              => false,
        'mms'                   => false,
    ];

    /**
     * Const'r
     *
     * @see Horde_ActiveSync_Message_Base::__construct()
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        if ($this->_version < Horde_ActiveSync::VERSION_TWELVE) {
            $this->_mapping += [
                self::BODY                  => [self::KEY_ATTRIBUTE => 'body'],
                self::BODYSIZE              => [self::KEY_ATTRIBUTE => 'bodysize'],
                self::BODYTRUNCATED         => [self::KEY_ATTRIBUTE => 'bodytruncated'],
                self::RTF                   => [self::KEY_ATTRIBUTE => 'rtf'],
            ];

            $this->_properties += [
                'body'                  => false,
                'bodysize'              => false,
                'bodytruncated'         => 0,
                'rtf'                   => false,
            ];
        } else {
            $this->_mapping += [
                Horde_ActiveSync::AIRSYNCBASE_BODY => [self::KEY_ATTRIBUTE => 'airsyncbasebody', self::KEY_TYPE => 'Horde_ActiveSync_Message_AirSyncBaseBody'],
            ];
            $this->_properties += [
                'airsyncbasebody' => false,
            ];
            if ($this->_version > Horde_ActiveSync::VERSION_TWELVEONE) {
                $this->_mapping += [
                    self::ALIAS => [self::KEY_ATTRIBUTE => 'alias'],
                    self::WEIGHTEDRANK => [self::KEY_ATTRIBUTE  => 'weightedrank'],
                ];
                $this->_properties += [
                    'alias' => false,
                    'weightedrank' => false,
                ];
            }
        }
    }

    /**
     * Return message type
     *
     * @return string
     */
    public function getClass()
    {
        return 'Contacts';
    }

    /**
     * Check if we should send a specific property even if it's empty.
     *
     * @param string $tag  The property tag.
     *
     * @return boolean
     */
    protected function _checkSendEmpty($tag)
    {
        if ($tag == self::BODYTRUNCATED && $this->bodysize > 0) {
            return true;
        }

        return false;
    }

    /**
     * Override parent class so we can normalize the Date object before
     * returning it.
     *
     * @param string $ts  The timestamp
     *
     * @return Horde_Date|boolean  The Horde_Date object (UTC) or false if
     *     unable to parse the date.
     */
    protected function _parseDate($ts)
    {
        if (!($date = parent::_parseDate($ts))) {
            return false;
        }

        // Since some clients send the date as YYYY-MM-DD only, the best we can
        // do is assume that it is in the same timezone as the user's default
        // timezone - so convert it to UTC and be done with it.
        if ($date->timezone != 'UTC') {
            $date->setTimezone('UTC');
        }

        // @todo: Remove this in H6.
        if (empty($this->_device)) {
            return $date;
        }

        return $this->_device->normalizePoomContactsDates($date);
    }

    /**
     * Format a date string for sending to the EAS client.
     *
     * @param Horde_Date $dt  The Horde_Date object to format
     *                        (should normally be in local tz).
     * @param integer $type   The type to format as (TYPE_DATE or TYPE_DATE_DASHES)
     *
     * @return string  The formatted date
     */
    protected function _formatDate(Horde_Date $dt, $type)
    {
        if (empty($this->_device)) {
            $date = $dt;
        } else {
            $date = $this->_device->normalizePoomContactsDates($dt, true);
        }
        return parent::_formatDate($date, $type);
    }

    /**
     * Determines if the property specified has been ghosted by the client.
     * A property is ghosted if it is NOT listed in the SUPPORTED list sent
     * by the client AND is NOT present in the request data.
     *
     * @param string $property  The property to check
     * @param array  $options   An array of options:
     *     - ignoreEmptyPictureTagCheck: boolean If true, will not check for the
     *       QUIRK_INCORRECTLY_SENDS_EMPTY_PICTURE_TAG quirk. @since  2.32.0
     *
     * @return boolean
     */
    public function isGhosted($property, $options = [])
    {
        // MS-ASCMD 2.2.3.168:
        // An empty SUPPORTED container indicates that ALL elements able to be
        // ghosted ARE ghosted. A *missing* SUPPORTED tag indicates that NO
        // fields are ghosted - any ghostable properties are always considered
        // NOT ghosted. Some clients like iOS 4.x screw this up by not sending
        // any SUPPORTED container and also not sending the picture field during
        // edits.
        if ($property == $this->_mapping[self::PICTURE][self::KEY_ATTRIBUTE]) {
            if (empty($options['ignoreEmptyPictureTagCheck'])
                && $this->_device->hasQuirk(Horde_ActiveSync_Device::QUIRK_INCORRECTLY_SENDS_EMPTY_PICTURE_TAG)
                && ((!empty($this->_exists[$property])
                && $this->{$property} == '') || empty($this->_exists[$property]))) {
                return true;
            }

            if (empty($this->_exists[$property])
                && empty($this->_supported)
                && $this->_device->hasQuirk(Horde_ActiveSync_Device::QUIRK_NEEDS_SUPPORTED_PICTURE_TAG)) {
                return true;
            }
        }

        return parent::isGhosted($property);
    }

}
