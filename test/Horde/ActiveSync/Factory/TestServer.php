<?php
/**
 * Copyright 2014-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information. If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2011-2020 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @package   Horde_ActiveSync
 * @subpackage UnitTests
 */
namespace Horde\ActiveSync\Factory;
use Horde_ActiveSync_Wbxml_Decoder;
use Horde_ActiveSync_Wbxml_Encoder;
use Horde_ActiveSync;

/**
 * Factory to provide various test servers.
 *
 * @author     Michael J Rubinsky <mrubinsk@horde.org>
 * @category   Horde
 * @copyright  2014-2020 Horde LLC
 * @ignore
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @package    Horde_ActiveSync
 * @subpackage UnitTests
 */
class TestServer
{
    public $server;
    public $driver;
    public $input;
    public $_output;
    public $request;

    public function __construct($driver, $request = null)
    {
        $this->driver = $driver;
        $this->request = $request ?? $this->createMockRequest();
        $this->input = fopen('php://memory', 'wb+');
        $decoder = new Horde_ActiveSync_Wbxml_Decoder($this->input);
        $this->_output = fopen('php://memory', 'wb+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($this->_output);
        $state = $this->createMockState();

        $this->server = new Horde_ActiveSync($this->driver, $decoder, $encoder, $state, $this->request);
    }

    /**
     * Create mock request object without TestCase.
     * Uses anonymous class to stub Horde_Controller_Request_Http.
     */
    protected function createMockRequest()
    {
        return new class {
            private $_overrides = [];

            public function setOverride($method, $value) {
                $this->_overrides[$method] = $value;
            }

            public function getHeader($header) {
                return $this->_overrides['getHeader'] ?? '14.1';
            }

            public function getServerVars($var = null) {
                $vars = $this->_overrides['getServerVars'] ?? array('PHP_AUTH_USER' => 'mike', 'PHP_AUTH_PW' => 'password');
                return $var ? ($vars[$var] ?? null) : $vars;
            }
        };
    }

    /**
     * Create mock state object without TestCase.
     * Uses anonymous class to stub Horde_ActiveSync_State_Base.
     */
    protected function createMockState()
    {
        return new class extends \Horde_ActiveSync_State_Base {
            public function __construct() {}
            public function listDevices($user = null, $filter = []) { return []; }
            public function deviceExists($devId, $user = '') { return false; }
            public function loadDeviceInfo($devId, $user = '', $ignorecache = false) { return null; }
            public function loadState($collection, $synckey, $type = null, $id = '') {}
            public function setNewSyncKey($key) {}
            public function removeState($synckey) {}
            public function updateState($type, $params = [], $deviceId = null) {}
            public function save() {}
            public function deleteFolderState($devId, $class, $folderid) {}
            public function getCollectionState($devId, $folderid, $type) { return []; }
            public function getHeartbeatState($devId, $user = '') { return null; }
            public function setHeartbeatState($state) {}
            public function getSyncCache($devId, $user, $fields = null) { return []; }
            public function saveSyncCache($cache, $devId, $user) {}
            public function deleteSyncCache($devId, $user = null) {}
            public function loadFolderCache($devId, $user = '') { return false; }
            public function setStateParams($params = []) {}
            public function isDuplicatePIMAddition($id) { return false; }
            public function setHeartbeat($hb) {}
            public function getHeartbeat() { return 0; }
            public function setBackend($backend) {}
        };
    }

}
