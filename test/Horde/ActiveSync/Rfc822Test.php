<?php
/*
 * Unit tests for Horde_ActiveSync_Policies
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @package ActiveSync
 */
namespace Horde\ActiveSync;
use Horde_Test_Case as TestCase;
use \Horde_ActiveSync_Rfc822;
use \Horde_Mime_Headers;

class Rfc822Test extends TestCase
{
    /**
     * @dataProvider headersMultipartAlternativeProvider
     */
    public function testHeadersMultipartAlternative($fixture, $expected)
    {
        $rfc822 = new Horde_ActiveSync_Rfc822($fixture);

        $test = array_change_key_case(
            $rfc822->getHeaders()->toArray(),
            CASE_LOWER
        );
        ksort($test);

        $this->assertEquals(
            $expected,
            $test
        );

        if (is_resource($fixture)) {
            fclose($fixture);
        }
    }

    public function headersMultipartAlternativeProvider()
    {
        $expected = array_change_key_case(array(
            'Subject' => 'Testing',
            'From' => 'mrubinsk@horde.org',
            'Content-Type' => 'multipart/alternative;
 boundary=Apple-Mail-B1C01B47-00D8-4AFB-8B65-DF81C4E4B47D',
            'Message-Id' => '<D492BB4F-6A2E-4E58-B607-4E8849A72919@horde.org>',
            'Date' => 'Tue, 1 Jan 2013 18:10:37 -0500',
            'To' => 'Michael Rubinsky <mike@theupstairsroom.com>',
            'Content-Transfer-Encoding' => '7bit',
            'Mime-Version' => '1.0 (1.0)',
            'User-Agent' => 'Horde Application Framework 5'
        ), CASE_LOWER);
        ksort($expected);

        return array(
            array(
                file_get_contents(__DIR__ . '/fixtures/iOSMultipartAlternative.eml'),
                $expected
            ),
            array(
                fopen(__DIR__ . '/fixtures/iOSMultipartAlternative.eml', 'r'),
                $expected
            )
        );
    }

    public function testBaseMimePart()
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/iOSMultipartAlternative.eml');
        $rfc822 = new Horde_ActiveSync_Rfc822($fixture);
        $mimepart = $rfc822->getMimeObject();
        $expected =  array(
            'multipart/alternative',
            'text/plain',
            'text/html');

        $this->assertEquals($expected, $mimepart->contentTypeMap());
        $this->assertEquals(1, $mimepart->findBody('plain'));
        $this->assertEquals(2, $mimepart->findBody('html'));
    }

    /**
     * See Bug #13456  Wnen we add the Message-Id/User-Agent headers, make sure
     * we don't cause the subject header to not be MIME encoded.
     */
    public function testMIMEEncodingWhenStandardHeadersAreAdded()
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/mime_encoding.eml');
        $rfc822 = new Horde_ActiveSync_Rfc822($fixture, true);

        $hdrs = Horde_Mime_Headers::parseHeaders($rfc822->getString());
        $hdr_array = $hdrs->toArray(array('charset' => 'UTF-8'));
        $this->assertEquals('=?utf-8?b?w4PDhMOjw6s=?=', $hdr_array['Subject']);
    }

}
