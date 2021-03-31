<?php
/*
 * Unit tests for Horde_ActiveSync_Mime
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @package ActiveSync
 */
namespace Horde\ActiveSync;
use Horde_Test_Case as TestCase;
use \Horde_ActiveSync_Mime;
use \Horde_Mime_Headers;
use \Horde_Mime_Part;
use \Horde_ActiveSync_Mime_Headers_Addresses;
use \Horde_ActiveSync_Mime_Iterator;

class MimeTest extends TestCase
{

   public function testHasAttachmentsWithNoAttachment()
   {
        $fixture = file_get_contents(__DIR__ . '/fixtures/email_plain.eml');
        $mime = new Horde_ActiveSync_Mime(Horde_Mime_Part::parseMessage($fixture));
        $this->assertEquals(false, $mime->hasAttachments());
        $this->assertEquals(false, $mime->isSigned());
        $this->assertEquals(false, $mime->hasiCalendar());

        $fixture = file_get_contents(__DIR__ . '/fixtures/iOSMultipartAlternative.eml');
        $mime = new Horde_ActiveSync_Mime(Horde_Mime_Part::parseMessage($fixture));
        $this->assertEquals(false, $mime->hasAttachments());
        $this->assertEquals(false, $mime->isSigned());
        $this->assertEquals(false, $mime->hasiCalendar());
   }

   public function testSignedNoAttachment()
   {
        $fixture = file_get_contents(__DIR__ . '/fixtures/email_signed.eml');
        $mime = new Horde_ActiveSync_Mime(Horde_Mime_Part::parseMessage($fixture));
        $this->assertEquals(false, $mime->hasAttachments());
        $this->assertEquals(true, $mime->isSigned());
        $this->assertEquals(false, $mime->hasiCalendar());

       $fixture = file_get_contents(__DIR__ . '/fixtures/encrypted.eml');
       $mime = new Horde_ActiveSync_Mime(Horde_Mime_Part::parseMessage($fixture));
       $this->assertEquals(false, $mime->isSigned());
   }

   public function testIsEncrypted()
   {
       $fixture = file_get_contents(__DIR__ . '/fixtures/encrypted.eml');
       $mime = new Horde_ActiveSync_Mime(Horde_Mime_Part::parseMessage($fixture));
       $this->assertEquals(true, $mime->isEncrypted());

       $fixture = file_get_contents(__DIR__ . '/fixtures/email_signed.eml');
       $mime = new Horde_ActiveSync_Mime(Horde_Mime_Part::parseMessage($fixture));
       $this->assertEquals(false, $mime->isEncrypted());
   }

   public function testHasAttachmentsWithAttachment()
   {
        $fixture = file_get_contents(__DIR__ . '/fixtures/signed_attachment.eml');
        $mime = new Horde_ActiveSync_Mime(Horde_Mime_Part::parseMessage($fixture));
        $this->assertEquals(true, $mime->hasAttachments());
        $this->assertEquals(true, $mime->isSigned());
        $this->assertEquals(false, $mime->hasiCalendar());
   }

   public function testReplaceMime()
   {
        $fixture = file_get_contents(__DIR__ . '/fixtures/signed_attachment.eml');
        $mime = new Horde_ActiveSync_Mime(Horde_Mime_Part::parseMessage($fixture));
        foreach ($mime->contentTypeMap() as $id => $type) {
            if ($mime->isAttachment($id, $type)) {
                $part = new Horde_Mime_Part();
                $part->setType('text/plain');
                $part->setContents(sprintf(
                    'An attachment named %s was removed by Horde_ActiveSync_Test',
                    $mime->getPart($id)->getName(true))
                );
                $mime->removePart($id);
                $mime->addPart($part);
            }
        }

        $this->assertEquals(true, $mime->hasAttachments());
        $this->assertEquals('An attachment named foxtrotjobs.png was removed by Horde_ActiveSync_Test', $mime->getPart('3')->getContents());
    }

    public function testRfc822MessageWithMultipartDoesNotIterate()
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/rfc822_multipart.eml');
        $mime = new Horde_ActiveSync_Mime(Horde_Mime_Part::parseMessage($fixture));
        $iterator = new Horde_ActiveSync_Mime_Iterator($mime->base);
        $this->assertEquals(5, $iterator->count());
    }

   public function testHasiCalendar()
   {
        $fixture = file_get_contents(__DIR__ . '/fixtures/invitation_one.eml');
        $mime = new Horde_ActiveSync_Mime(Horde_Mime_Part::parseMessage($fixture));
        $this->assertEquals(true, $mime->hasAttachments());
        $this->assertEquals(false, $mime->isSigned());
        $this->assertEquals(true, (boolean)$mime->hasiCalendar());
   }

   public function testIdna()
   {
      $fixture = file_get_contents(__DIR__ . '/fixtures/idna.eml');
      $headers = Horde_Mime_Headers::parseHeaders($fixture);
      foreach (array('from', 'to', 'cc') as $n) {
          if ($header = $headers->getHeader($n)) {
              $obj = new Horde_ActiveSync_Mime_Headers_Addresses($n, $header->full_value);
              $headers->removeHeader($n);
              $headers->addHeaderOb($obj);
          }
      }
      $this->assertEquals('Subject: TT Belieferungsstart der Schulfrei-Exemplare =?utf-8?b?ZsO8cg==?=
 das Schuljahr 2017/2018
Date: Fri, 1 Sep 2017 09:52:05 +0100
Message-ID: <CC5F7757CE6E614EB669EF841EE099F067CB1F@srvmbx01.moserholding.com.i>
MIME-Version: 1.0
Content-Type: multipart/alternative;
 boundary="----=_NextPart_000_0674_01D36791.91B0D380"
from: Michael J Rubinsky <mrubinsk@horde.org>
to: Jan Schneider <jan@horde.org>
cc: direktion@-abc.at, direktion@nms.-nd.abc.de

', $headers->toString());
   }


}
