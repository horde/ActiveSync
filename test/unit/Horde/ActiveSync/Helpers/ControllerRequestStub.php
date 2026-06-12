<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\ActiveSync\Test\Helpers;

use Horde_Controller_Request_Http;

/**
 * Test stub standing in for Horde_Controller_Request_Http.
 *
 * Extends the real request type so PHPUnit-built mocks satisfy the
 * Horde_ActiveSync constructor signature (which is typed against
 * Horde_Controller_Request_Http). Declared abstract so it cannot be
 * instantiated directly. Tests mock this class via getMockBuilder().
 */
abstract class ControllerRequestStub extends Horde_Controller_Request_Http
{
}
