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
use \Horde_ActiveSync_Wbxml_Encoder;
use \Horde_Mime_Headers;
use \Horde_ActiveSync_Policies;

class PolicyTest extends TestCase
{
    public function testDefaultWbxml()
    {
        $this->markTestIncomplete('Needs updated fixture.');
        $stream = fopen('php://memory', 'w+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($stream);
        $handler = new Horde_ActiveSync_Policies($encoder);
        $handler->toWbxml();
        rewind($stream);
        $results = stream_get_contents($stream);
        fclose($stream);
        $fixture = file_get_contents(__DIR__ . '/fixtures/default_policies.wbxml');
        $this->assertEquals($fixture, $results);
    }

    public function testDefaultXml()
    {
        $stream = fopen('php://memory', 'w+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($stream);
        $handler = new Horde_ActiveSync_Policies($encoder);
        $handler->toXml();
        rewind($stream);
        $results = stream_get_contents($stream);
        fclose($stream);
        $fixture = file_get_contents(__DIR__ . '/fixtures/default_policies.xml');
        $this->assertEquals($fixture, $results);
    }

}