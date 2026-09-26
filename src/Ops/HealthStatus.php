<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Ops;

use InvalidArgumentException;

final class HealthStatus
{
    public const OK = 'ok';
    public const WARN = 'warn';
    public const CRITICAL = 'critical';

    public static function rank(string $status): int
    {
        return match ($status) {
            self::OK => 0,
            self::WARN => 1,
            self::CRITICAL => 2,
            default => throw new InvalidArgumentException(
                sprintf('Unknown health status: %s', $status)
            ),
        };
    }

    public static function worst(string ...$statuses): string
    {
        $worst = self::OK;
        foreach ($statuses as $status) {
            if (self::rank($status) > self::rank($worst)) {
                $worst = $status;
            }
        }

        return $worst;
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, [self::OK, self::WARN, self::CRITICAL], true);
    }
}
