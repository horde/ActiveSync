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
use Horde_Test_Case as TestCase;
use \Horde_ActiveSync_Wbxml_Decoder;
use \Horde_ActiveSync_Wbxml_Encoder;
use \Horde_ActiveSync;

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
class TestServer extends TestCase
{
    public $server;
    public $driver;
    public $input;
    public $_output;
    public $request;

    public function __construct($params = array())
    {        
        $this->driver = $this->getMockBuilder('Horde_ActiveSync_Driver_Base')
                                ->disableOriginalConstructor()
                                ->getMock();
        $this->input = fopen('php://memory', 'wb+');
        $decoder = new Horde_ActiveSync_Wbxml_Decoder($this->input);
        $this->_output = fopen('php://memory', 'wb+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($this->_output);
        $state = $this->getMockBuilder('Horde_ActiveSync_State_Base')
                        ->disableOriginalConstructor()
                        ->getMock();
        $this->request = $this->getMockBuilder('Horde_Controller_Request_Http')
                                ->disableOriginalConstructor()
                                ->getMock();
        $this->markTestSkipped('Methods parameterized below "getHeader" and "getServerVars" arent configurable. ');

        $this->request->expects($this->any())
            ->method('getHeader')
            ->will($this->returnValue('14.1'));
        $this->request->expects($this->any())
            ->method('getServerVars')
            ->will($this->returnValue(array('PHP_AUTH_USER' => 'mike', 'PHP_AUTH_PW' => 'password')));
        $this->server = new Horde_ActiveSync($this->driver, $decoder, $encoder, $state, $this->request);
    }

}
