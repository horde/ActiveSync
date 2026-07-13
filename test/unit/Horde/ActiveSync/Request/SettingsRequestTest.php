<?php

/**
 * Unit tests for Horde_ActiveSync_Request_Settings helpers.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project (http://www.horde.org/)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Request;

use Horde_ActiveSync_Request_Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Horde_ActiveSync_Request_Settings::class)]
class SettingsRequestTest extends TestCase
{
    public function testGetOofObjectDefaultsWhenNoMessagesConfigured()
    {
        $oof = $this->_getOofObject([
            'status' => Horde_ActiveSync_Request_Settings::STATUS_SUCCESS,
            'oofstate' => Horde_ActiveSync_Request_Settings::OOF_STATE_DISABLED,
            'oofmsgs' => [],
        ]);

        $this->assertSame(
            Horde_ActiveSync_Request_Settings::OOF_STATE_DISABLED,
            $oof->state
        );
        $this->assertCount(1, $oof->messages);
        $this->assertSame('0', $oof->messages[0]->enabled);
        $this->assertSame('', $oof->messages[0]->reply);
        $this->assertSame('text', $oof->messages[0]->bodytype);
    }

    public function testGetOofObjectUsesConfiguredMessage()
    {
        $oof = $this->_getOofObject([
            'status' => Horde_ActiveSync_Request_Settings::STATUS_SUCCESS,
            'oofstate' => Horde_ActiveSync_Request_Settings::OOF_STATE_ENABLED,
            'oofmsgs' => [
                [
                    'enabled' => true,
                    'replymessage' => 'Out of office.',
                    'bodytype' => 'HTML',
                ],
            ],
        ]);

        $this->assertSame(1, $oof->messages[0]->enabled);
        $this->assertSame('Out of office.', $oof->messages[0]->reply);
        $this->assertSame('HTML', $oof->messages[0]->bodytype);
    }

    /**
     * @param array $info
     *
     * @return \Horde_ActiveSync_Message_Oof
     */
    protected function _getOofObject(array $info)
    {
        $request = (new ReflectionClass(Horde_ActiveSync_Request_Settings::class))
            ->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(Horde_ActiveSync_Request_Settings::class))
            ->getMethod('_getOofObject');
        $method->setAccessible(true);

        return $method->invoke($request, $info);
    }
}
