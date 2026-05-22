<?php

/**
 * Stub for Horde_Controller_Request_Http to allow tests to run without horde/controller.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

/**
 * Minimal stub interface for Horde_Controller_Request_Http.
 * Allows tests to create mocks without requiring the full horde/controller package.
 */
interface Horde_Controller_Request_Http
{
    public function getHeader($header);
    public function getServerVars($var = null);
    public function getGetVars($var = null);
}
