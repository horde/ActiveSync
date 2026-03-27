<?php
/*
 * Unit tests for Autodiscover functionality.
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @package ActiveSync
 */
namespace Horde\ActiveSync;
use PHPUnit\Framework\TestCase;

class AutodiscoverTest extends TestCase
{
    /**
     * Tests autodiscover functionality when passed a proper XML data structure
     * containing an email address that needs to be mapped to a username.
     *
     */
    public function testAutodiscoverWithProperXML()
    {
        $this->markTestSkipped('Requires horde/controller package and complex mock setup');
    }

    /**
     * Test workarounds for broken clients that don't send proper XML with
     * autodiscover requests. In this case, the user/email is taken from the
     * HTTP Basic auth data.
     */
    public function testAutodiscoverWithMissingXML()
    {
        $this->markTestSkipped('Requires horde/controller package and complex mock setup');
    }

}
