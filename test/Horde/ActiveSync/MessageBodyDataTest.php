<?php
/**
 * Unit tests for Horde_ActiveSync_Folder_Imap
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @package ActiveSync
 */
namespace Horde\ActiveSync;
use PHPUnit\Framework\TestCase;

class MessageBodyDataTest extends TestCase
{
    public function testReturnProperlyTruncatedHtml()
    {
        $this->markTestSkipped('Requires horde/controller package');
    }

    public function testReturnHtmlNoTruncation()
    {
        $this->markTestSkipped('Requires horde/controller package');
    }

    protected function _getFixturesFor13711()
    {
        $fetch_ret = unserialize(base64_decode(file_get_contents(__DIR__ . '/fixtures/fixture_fetch')));
        return $this->onConsecutiveCalls($fetch_ret);
    }

}
