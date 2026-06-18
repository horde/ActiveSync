<?php

/**
 * Test trait for constructing Horde_ActiveSync server instances.
 *
 * Mixed into PHPUnit test cases so that protected helpers like
 * getMockBuilder() are in scope. Uses Horde_Controller_Request_Mock for the
 * HTTP layer (no PHPUnit request mocks) and PHPUnit only for driver/state
 * doubles.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Test\Support;

use Horde_ActiveSync;
use Horde_ActiveSync_Driver_Base;
use Horde_ActiveSync_State_Base;
use Horde_ActiveSync_Wbxml_Decoder;
use Horde_ActiveSync_Wbxml_Encoder;
use Horde_Controller_Request_Mock;

/**
 * @mixin \PHPUnit\Framework\TestCase
 */
trait ActiveSyncServerTrait
{
    /**
     * Build a test ActiveSync server.
     *
     * @param array $options  Optional configuration:
     *   - clientVersion: (string) MS-ASProtocolVersion header value.
     *   - maxVersion: (string) Server ceiling via setSupportedVersion().
     *   - serverVars: (array) $_SERVER values for Request_Mock.
     *   - getVars: (array) $_GET values for Request_Mock.
     *
     * @return object  Stdclass with server, driver, input, request fields.
     */
    protected function createActiveSyncServer(array $options = []): object
    {
        $serverVars = $options['serverVars'] ?? [
            'PHP_AUTH_USER' => 'mike',
            'PHP_AUTH_PW' => 'password',
        ];
        if (!empty($options['clientVersion'])) {
            $serverVars['HTTP_MS_ASPROTOCOLVERSION'] = $options['clientVersion'];
        }

        $requestVars = ['server' => $serverVars];
        if (!empty($options['getVars'])) {
            $requestVars['get'] = $options['getVars'];
        }

        $driver = $this->getMockBuilder(Horde_ActiveSync_Driver_Base::class)
            ->disableOriginalConstructor()
            ->getMock();

        $input = fopen('php://memory', 'wb+');
        $decoder = new Horde_ActiveSync_Wbxml_Decoder($input);
        $output = fopen('php://memory', 'wb+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($output);

        $state = $this->getMockBuilder(Horde_ActiveSync_State_Base::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Horde_Controller_Request_Mock($requestVars);

        $server = new Horde_ActiveSync(
            $driver,
            $decoder,
            $encoder,
            $state,
            $request
        );

        if (!empty($options['maxVersion'])) {
            $server->setSupportedVersion($options['maxVersion']);
        }

        return (object) [
            'server' => $server,
            'driver' => $driver,
            'input' => $input,
            'request' => $request,
        ];
    }
}
