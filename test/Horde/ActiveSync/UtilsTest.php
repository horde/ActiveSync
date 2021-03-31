<?php
/*
 * Unit tests for Horde_ActiveSync_Utils::
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @package ActiveSync
 */
namespace Horde\ActiveSync;
use Horde_Test_Case as TestCase;
use \Horde_ActiveSync_Utils;

class UtilsTest extends TestCase
{
    public function testBase64Uri()
    {
        /* Settings Request for versions >= 12.1 */
        $url = 'oBEJBBBOaW5lMkVDN0VDMEJCNTREBAGJpmIHQW5kcm9pZAcBAA==';
        $results = Horde_ActiveSync_Utils::decodeBase64($url);
        $fixture = array(
            'ProtVer' => '16.0',
            'Cmd' => 'Settings',
            'Locale' => 1033,
            'DeviceId' => '4e696e65324543374543304242353444',
            'PolicyKey' => 1655081217,
            'DeviceType' => 'Android',
            'SaveInSent' => false,
            'AcceptMultiPart' => false
        );
        $this->assertEquals($fixture, $results);

        /* Smart Forward */
        $url = 'eQIJBBCuTs0Z9ZK6Vldwb/dM8JusBHVeHIQDUFBDBwEBAwYxMTkyODEBBUlOQk9Y';
        $results = Horde_ActiveSync_Utils::decodeBase64($url);
        $results['PolicyKey'] = sprintf('%u', $results['PolicyKey']);

        // This is binary data, test it separately.
        $fixture = array(
            'ProtVer' => '12.1',
            'Cmd' => 'SmartForward',
            'Locale' => 1033,
            'DeviceId' => 'ae4ecd19f592ba5657706ff74cf09bac',
            'PolicyKey' => '2216451701',
            'DeviceType' => 'PPC',
            'ItemId' => '119281',
            'CollectionId' => 'INBOX',
            'AcceptMultiPart' => false,
            'SaveInSent' => true
        );
        $this->assertEquals($fixture, $results);
    }

    public function testBodyTypePref()
    {
        $this->markTestIncomplete('Needs refactoring.');
        $fixture = array(
            'bodyprefs' => array(Horde_ActiveSync::BODYPREF_TYPE_HTML => true, Horde_ActiveSync::BODYPREF_TYPE_MIME => true)
        );

        $this->assertEquals(Horde_ActiveSync::BODYPREF_TYPE_HTML, Horde_ActiveSync_Utils_Mime::getBodyTypePref($fixture));
        $this->assertEquals(Horde_ActiveSync::BODYPREF_TYPE_MIME, Horde_ActiveSync_Utils_Mime::getBodyTypePref($fixture, false));

        $fixture = array(
            'bodyprefs' => array(Horde_ActiveSync::BODYPREF_TYPE_HTML => true)
        );
        $this->assertEquals(Horde_ActiveSync::BODYPREF_TYPE_HTML, Horde_ActiveSync_Utils_Mime::getBodyTypePref($fixture));
        $this->assertEquals(Horde_ActiveSync::BODYPREF_TYPE_HTML, Horde_ActiveSync_Utils_Mime::getBodyTypePref($fixture, false));

        $fixture = array(
            'bodyprefs' => array(Horde_ActiveSync::BODYPREF_TYPE_HTML => true)
        );
        $this->assertEquals(Horde_ActiveSync::BODYPREF_TYPE_HTML, Horde_ActiveSync_Utils_Mime::getBodyTypePref($fixture));
        $this->assertEquals(Horde_ActiveSync::BODYPREF_TYPE_HTML, Horde_ActiveSync_Utils_Mime::getBodyTypePref($fixture, false));

        $fixture = array(
            'bodyprefs' => array(Horde_ActiveSync::BODYPREF_TYPE_MIME => true)
        );
        $this->assertEquals(Horde_ActiveSync::BODYPREF_TYPE_MIME, Horde_ActiveSync_Utils_Mime::getBodyTypePref($fixture));
        $this->assertEquals(Horde_ActiveSync::BODYPREF_TYPE_MIME, Horde_ActiveSync_Utils_Mime::getBodyTypePref($fixture, false));
    }
}