<?php

/**
 * Shared fixtures for Bug #13711 and related HTML truncation tests.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Test\Support;

use Horde_ActiveSync;
use Horde_ActiveSync_Mime;
use Horde_Imap_Client_Fetch_Results;
use ReflectionClass;

class Bug13711Fixtures
{
    private const HTML_MIME = 'TzoyMToiSG9yZGVfQWN0aXZlU3luY19NaW1lIjoyOntzOjg6IgAqAF9iYXNlIjtDOjE1OiJIb3JkZV9NaW1lX1BhcnQiOjI4MDp7YToyMDp7aTowO2k6MTtpOjE7czo0OiJ0ZXh0IjtpOjI7czo0OiJodG1sIjtpOjM7czoxNjoicXVvdGVkLXByaW50YWJsZSI7aTo0O2E6MDp7fWk6NTtzOjA6IiI7aTo2O3M6MDoiIjtpOjc7YToxOntzOjQ6InNpemUiO3M6NToiMzAzMzYiO31pOjg7YToxOntzOjc6ImNoYXJzZXQiO3M6NToidXRmLTgiO31pOjk7YTowOnt9aToxMDtzOjE6IjEiO2k6MTE7czoxOiIKIjtpOjEyO2E6MDp7fWk6MTM7TjtpOjE0O2k6MzAzMzY7aToxNTtOO2k6MTY7TjtpOjE3O2I6MDtpOjE4O2I6MDtpOjE5O047fX1zOjE4OiIAKgBfaGFzQXR0YWNobWVudHMiO047fQ==';

    public static function htmlMime(): Horde_ActiveSync_Mime
    {
        return unserialize(base64_decode(self::HTML_MIME));
    }

    public static function htmlFetchResults(): Horde_Imap_Client_Fetch_Results
    {
        return unserialize(base64_decode(file_get_contents(__DIR__ . '/../../fixtures/fixture_fetch')));
    }

    /**
     * Fetch results for a calendar-only message (text/calendar, no plain/html).
     *
     * @return Horde_Imap_Client_Fetch_Results[]
     */
    public static function calendarOnlyFetchSequence(): array
    {
        $dir = __DIR__ . '/../../fixtures';

        return [
            unserialize(base64_decode(file_get_contents($dir . '/bug13711_first'))),
            unserialize(base64_decode(file_get_contents($dir . '/bug13711_second'))),
            unserialize(base64_decode(file_get_contents($dir . '/bug13711_fourth'))),
        ];
    }

    public static function setActiveSyncProtocolVersion(string $version): void
    {
        $ref = new ReflectionClass(Horde_ActiveSync::class);
        $prop = $ref->getProperty('_version');
        $prop->setAccessible(true);
        $prop->setValue(null, $version);
    }
}
