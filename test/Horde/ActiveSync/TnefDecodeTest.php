<?php

declare(strict_types=1);

namespace Horde\ActiveSync;

use Horde_ActiveSync_Imap_Message;
use Horde_Mime_Part;
use PHPUnit\Framework\TestCase;

/**
 * Tests _decodeTnefData() contract: given a Horde_Mime_Part containing
 * TNEF binary data, returns a multipart/mixed Horde_Mime_Part wrapper
 * with decoded attachments as children.
 * @coversNothing
 */
class TnefDecodeTest extends TestCase
{
    public function testDecodeTnefReturnsMultipartWithAttachments(): void
    {
        $tnefBinary = base64_decode(
            file_get_contents(__DIR__ . '/fixtures/TnefAttachments.txt')
        );

        $inputPart = new Horde_Mime_Part();
        $inputPart->setType('application/ms-tnef');
        $inputPart->setName('winmail.dat');
        $inputPart->setContents($tnefBinary);
        $inputPart->setMimeId('2');

        $message = new TestableTnefMessage();
        $result = $message->decodeTnef($inputPart);

        $this->assertInstanceOf(Horde_Mime_Part::class, $result);
        $this->assertEquals('multipart/mixed', $result->getType());
        $this->assertEquals('winmail.dat', $result->getName());
        $this->assertEquals('0', $result->getMimeId());

        $parts = [];
        foreach ($result as $id => $part) {
            if ($part === $result) {
                continue;
            }
            $parts[] = $part;
        }

        $this->assertGreaterThanOrEqual(2, count($parts));

        $this->assertEquals('application/rtf', $parts[0]->getType());

        $this->assertEquals('image/jpeg', $parts[1]->getType());
        $this->assertEquals('hasselhoff_birthday.jpg', $parts[1]->getName());
    }

    public function testDecodeTnefReturnsFalseOnInvalidData(): void
    {
        $inputPart = new Horde_Mime_Part();
        $inputPart->setType('application/ms-tnef');
        $inputPart->setName('winmail.dat');
        $inputPart->setContents('not valid tnef data');
        $inputPart->setMimeId('1');

        $message = new TestableTnefMessage();
        $result = $message->decodeTnef($inputPart);

        $this->assertFalse($result);
    }
}

/**
 * Test subclass that exposes _decodeTnefData without needing IMAP dependencies.
 */
class TestableTnefMessage extends Horde_ActiveSync_Imap_Message
{
    public function __construct() {}

    public function decodeTnef(Horde_Mime_Part $data)
    {
        return $this->_decodeTnefData($data);
    }
}
