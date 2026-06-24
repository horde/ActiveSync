<?php

/*
 * Unit tests for Autodiscover functionality.
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @package ActiveSync
 */

namespace Horde\ActiveSync;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Horde\ActiveSync\Test\Support\ActiveSyncServerTrait;

#[CoversNothing]
class AutodiscoverTest extends TestCase
{
    use ActiveSyncServerTrait;

    /**
     * Tests autodiscover functionality when passed a proper XML data structure
     * containing an email address that needs to be mapped to a username.
     */
    public function testAutodiscoverWithProperXML()
    {
        $fixture = $this->createActiveSyncServer();

        $request = <<<EOT
            <?xml version="1.0" encoding="utf-8"?>
            <Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/mobilesync/requestschema/2006">
            <Request>
            <EMailAddress>mike@example.com</EMailAddress>
            <AcceptableResponseSchema>
            http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006
            </AcceptableResponseSchema>
            </Request>
            </Autodiscover>
            EOT;
        fwrite($fixture->input, $request);
        rewind($fixture->input);

        // Mock the getUsernameFromEmail method to return 'mike' when 'mike@example.com'
        // is passed.
        $fixture->driver->expects($this->once())
            ->method('getUsernameFromEmail')
            ->willReturnMap([['mike@example.com', 'mike']]);

        // Mock authenticate to return true only if mike is passed as username.
        $fixture->driver->expects($this->any())
            ->method('authenticate')
            ->willReturnMap([['mike', 'password', null, true]]);

        // Setup is called once, and must return true.
        $fixture->driver->expects($this->once())
            ->method('setup')
            ->willReturn(true);

        // Checks that the correct schema was detected.
        $mock_driver_parameters = [
            'request_schema' => 'http://schemas.microsoft.com/exchange/autodiscover/mobilesync/requestschema/2006',
            'response_schema' => 'http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006'];

        // ...and will only return this if it was.
        $mock_driver_results = [
            'display_name' => 'Michael Rubinsky',
            'email' => 'mike@example.com',
            'culture' => 'en:en',
            'username' => 'mike',
            'url' => 'https://example.com/Microsoft-Server-ActiveSync',
        ];

        $fixture->driver->expects($this->once())
            ->method('autoDiscover')
            ->willReturnMap([[$mock_driver_parameters, $mock_driver_results]]);

        $fixture->server->handleRequest('Autodiscover', 'testdevice');

        // Test the results
        $expected = <<<EOT
            <?xml version="1.0" encoding="utf-8"?>
                          <Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">
                            <Response xmlns="http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006">
                              <Culture>en:en</Culture>
                              <User>
                                <DisplayName>Michael Rubinsky</DisplayName>
                                <EMailAddress>mike@example.com</EMailAddress>
                              </User>
                              <Action>
                                <Settings>
                                  <Server>
                                    <Type>MobileSync</Type>
                                    <Url>https://example.com/Microsoft-Server-ActiveSync</Url>
                                    <Name>https://example.com/Microsoft-Server-ActiveSync</Name>
                                   </Server>
                                </Settings>
                              </Action>
                            </Response>
                          </Autodiscover>
            EOT;
        $fixture->server->encoder->getStream()->rewind();
        $this->assertEquals($expected, $fixture->server->encoder->getStream()->getString());
    }

    /**
     * Test workarounds for broken clients that don't send proper XML with
     * autodiscover requests. In this case, the user/email is taken from the
     * HTTP Basic auth data.
     */
    public function testAutodiscoverWithMissingXML()
    {
        // Basic auth: mike:password
        $auth = 'Basic bWlrZTpwYXNzd29yZA==';
        $fixture = $this->createActiveSyncServer([
            'serverVars' => [
                'HTTP_AUTHORIZATION' => $auth,
            ],
        ]);

        // Mock the getUsernameFromEmail method to return 'mike' when 'mike'
        // is passed.
        $fixture->driver->expects($this->once())
            ->method('getUsernameFromEmail')
            ->willReturnMap([['mike', 'mike']]);

        // Mock authenticate to return true only if 'mike' is passed as username
        // and 'password' is passed as the password.
        $fixture->driver->expects($this->any())
            ->method('authenticate')
            ->willReturnMap([['mike', 'password', null, true]]);

        // Setup is called once, and must return true.
        $fixture->driver->expects($this->once())
            ->method('setup')
            ->willReturn(true);

        // Checks that the correct schema was detected.
        $mock_driver_parameters = [
            'request_schema' => 'http://schemas.microsoft.com/exchange/autodiscover/mobilesync/requestschema/2006',
            'response_schema' => 'http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006'];

        // ...and will only return this if it was.
        $mock_driver_results = [
            'display_name' => 'Michael Rubinsky',
            'email' => 'mike@example.com',
            'culture' => 'en:en',
            'username' => 'mike',
            'url' => 'https://example.com/Microsoft-Server-ActiveSync',
        ];

        $fixture->driver->expects($this->once())
            ->method('autoDiscover')
            ->willReturnMap([[$mock_driver_parameters, $mock_driver_results]]);

        $fixture->server->handleRequest('Autodiscover', 'testdevice');

        // Test the results
        $expected = <<<EOT
            <?xml version="1.0" encoding="utf-8"?>
                          <Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">
                            <Response xmlns="http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006">
                              <Culture>en:en</Culture>
                              <User>
                                <DisplayName>Michael Rubinsky</DisplayName>
                                <EMailAddress>mike@example.com</EMailAddress>
                              </User>
                              <Action>
                                <Settings>
                                  <Server>
                                    <Type>MobileSync</Type>
                                    <Url>https://example.com/Microsoft-Server-ActiveSync</Url>
                                    <Name>https://example.com/Microsoft-Server-ActiveSync</Name>
                                   </Server>
                                </Settings>
                              </Action>
                            </Response>
                          </Autodiscover>
            EOT;
        $fixture->server->encoder->getStream()->rewind();
        $this->assertEquals($expected, $fixture->server->encoder->getStream()->getString());
    }

    /**
     * Tests the unauthenticated Autodiscover v2 (JSON) endpoint, which maps the
     * requested protocol to the relevant service URL.
     */
    public function testAutodiscoverV2JsonReturnsActiveSyncUrl()
    {
        $fixture = $this->createActiveSyncServer([
            'serverVars' => [
                'REQUEST_URI' => '/autodiscover/autodiscover.json/v1.0/mike@example.com?Protocol=ActiveSync',
            ],
            'getVars' => ['Protocol' => 'ActiveSync'],
        ]);

        // v2 is unauthenticated: no authenticate()/setup() calls are expected.
        $fixture->driver->expects($this->once())
            ->method('autoDiscover')
            ->willReturnMap([
                [['protocol' => 'ActiveSync'], 2, ['protocol' => 'ActiveSync', 'url' => 'https://example.com/Microsoft-Server-ActiveSync']],
            ]);

        $fixture->server->handleRequest('Autodiscover', 'testdevice');

        $fixture->server->encoder->getStream()->rewind();
        $this->assertEquals(
            '{"Protocol":"ActiveSync","Url":"https://example.com/Microsoft-Server-ActiveSync"}',
            $fixture->server->encoder->getStream()->getString()
        );
    }

    /**
     * Tests that a v2 (JSON) request omitting the required Protocol parameter
     * falls back to ActiveSync instead of triggering an undefined index error.
     */
    public function testAutodiscoverV2JsonDefaultsProtocolWhenMissing()
    {
        $fixture = $this->createActiveSyncServer([
            'serverVars' => [
                'REQUEST_URI' => '/autodiscover/autodiscover.json/v1.0/mike@example.com',
            ],
        ]);

        $fixture->driver->expects($this->once())
            ->method('autoDiscover')
            ->willReturnMap([
                [['protocol' => 'ActiveSync'], 2, ['protocol' => 'ActiveSync', 'url' => 'https://example.com/Microsoft-Server-ActiveSync']],
            ]);

        $fixture->server->handleRequest('Autodiscover', 'testdevice');

        $fixture->server->encoder->getStream()->rewind();
        $this->assertEquals(
            '{"Protocol":"ActiveSync","Url":"https://example.com/Microsoft-Server-ActiveSync"}',
            $fixture->server->encoder->getStream()->getString()
        );
    }

}
