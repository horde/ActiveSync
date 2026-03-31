<?php
/**
 * Test helper for creating test server instances.
 *
 * Replaces Factory\TestServer functionality to avoid TestCase inheritance issues.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Test\Helpers;

use PHPUnit\Framework\TestCase;
use Horde_ActiveSync_Wbxml_Decoder;
use Horde_ActiveSync_Wbxml_Encoder;
use Horde_ActiveSync;

class TestServerHelper
{
    /**
     * Create a test server with mocked dependencies.
     *
     * @param TestCase $testCase  The test case to use for creating mocks
     * @param array $params       Optional parameters:
     *                            - headerValue: Value for getHeader (default '14.1')
     *                            - serverVars: Array for getServerVars (default PHP_AUTH_USER/PW)
     *
     * @return object  Object with properties: server, driver, input, _output, request
     */
    public static function createTestServer(TestCase $testCase, array $params = [])
    {
        $headerValue = $params['headerValue'] ?? '14.1';
        $serverVars = $params['serverVars'] ?? array('PHP_AUTH_USER' => 'mike', 'PHP_AUTH_PW' => 'password');

        $driver = $testCase->getMockBuilder('Horde_ActiveSync_Driver_Base')
                            ->disableOriginalConstructor()
                            ->getMock();

        $input = fopen('php://memory', 'wb+');
        $decoder = new Horde_ActiveSync_Wbxml_Decoder($input);
        $output = fopen('php://memory', 'wb+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($output);

        $state = $testCase->getMockBuilder('Horde_ActiveSync_State_Base')
                            ->disableOriginalConstructor()
                            ->getMock();

        $request = $testCase->getMockBuilder('Horde_Controller_Request_Http')
                            ->disableOriginalConstructor()
                            ->getMock();
        $request->expects($testCase->any())
            ->method('getHeader')
            ->willReturn($headerValue);
        $request->expects($testCase->any())
            ->method('getServerVars')
            ->willReturn($serverVars);

        $server = new Horde_ActiveSync($driver, $decoder, $encoder, $state, $request);

        return (object)[
            'server' => $server,
            'driver' => $driver,
            'input' => $input,
            '_output' => $output,
            'request' => $request
        ];
    }
}
