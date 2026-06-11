<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\ActiveSync\Test\Helpers;

/**
 * Test stub standing in for Horde_Controller_Request_Http.
 *
 * Tests use this with PHPUnit's mock builder so the test suite does not have
 * to depend on horde/controller. Only the methods exercised by ActiveSync's
 * server are declared here.
 */
abstract class ControllerRequestStub
{
    abstract public function getHeader(string $header): mixed;

    abstract public function getServerVars(?string $var = null): mixed;

    abstract public function getGetVars(?string $var = null): mixed;
}
