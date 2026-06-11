<?php

/**
 * Test helper for creating mock loggers.
 *
 * Replaces Horde_Test_Log functionality.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Test\Helpers;

use Horde_Log_Logger;
use Horde_Log_Handler_Mock;
use Exception;

class LogHelper
{
    private static $logHandler;

    /**
     * Creates a mock logger for testing.
     *
     * @return Horde_Log_Logger
     */
    public static function createMockLogger(): Horde_Log_Logger
    {
        if (!class_exists('Horde_Log_Logger')) {
            throw new Exception('The "Horde_Log" package is missing!');
        }
        self::$logHandler = new Horde_Log_Handler_Mock();
        return new Horde_Log_Logger(self::$logHandler);
    }

    /**
     * Get the log handler for assertions.
     *
     * @return Horde_Log_Handler_Mock
     */
    public static function getLogHandler(): Horde_Log_Handler_Mock
    {
        return self::$logHandler;
    }

    /**
     * Assert that the log contains the given number of messages.
     *
     * @param int $expectedCount
     */
    public static function assertLogCount(\PHPUnit\Framework\TestCase $test, int $expectedCount): void
    {
        $test->assertEquals($expectedCount, count(self::$logHandler->events));
    }

    /**
     * Assert that the log contains a specific message.
     *
     * @param int $level
     * @param string $message
     */
    public static function assertLogContains(\PHPUnit\Framework\TestCase $test, int $level, string $message): void
    {
        foreach (self::$logHandler->events as $event) {
            if ($event['priority'] == $level && strpos($event['message'], $message) !== false) {
                return;
            }
        }
        $test->fail("Log does not contain message: $message");
    }
}
