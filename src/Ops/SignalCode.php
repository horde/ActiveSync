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

final class SignalCode
{
    public const HB_IN_FLIGHT = 'hb_in_flight';
    public const HB_STUCK = 'hb_stuck';
    public const HB_MISSING_END = 'hb_missing_end';
    public const HB_ABANDONED = 'hb_abandoned';
    public const FSR_WARN = 'fsr_warn';
    public const FSR_CRITICAL = 'fsr_critical';
    public const BLOCKED = 'blocked';
    public const WIPE_PENDING = 'wipe_pending';
    public const WIPE_COMPLETE = 'wipe_complete';
    public const BACKLOG_PENDING = 'backlog_pending';
    public const BACKLOG_STUCK = 'backlog_stuck';

    public static function all(): array
    {
        return [
            self::HB_IN_FLIGHT,
            self::HB_STUCK,
            self::HB_MISSING_END,
            self::HB_ABANDONED,
            self::FSR_WARN,
            self::FSR_CRITICAL,
            self::BLOCKED,
            self::WIPE_PENDING,
            self::WIPE_COMPLETE,
            self::BACKLOG_PENDING,
            self::BACKLOG_STUCK,
        ];
    }

    public static function describe(string $code): string
    {
        return match ($code) {
            self::HB_IN_FLIGHT => 'Heartbeat sync is currently in flight.',
            self::HB_STUCK => 'Heartbeat sync has exceeded its expected duration.',
            self::HB_MISSING_END => 'Heartbeat sync has no recorded normal end.',
            self::HB_ABANDONED => 'Last heartbeat sync ended abnormally; the request is no longer running.',
            self::FSR_WARN => 'Repeated FolderSync-required responses are approaching the limit.',
            self::FSR_CRITICAL => 'Repeated FolderSync-required responses reached the limit.',
            self::BLOCKED => 'Device is blocked.',
            self::WIPE_PENDING => 'A remote wipe is pending.',
            self::WIPE_COMPLETE => 'A remote wipe completed.',
            self::BACKLOG_PENDING => 'Collection backlog is awaiting recovery.',
            self::BACKLOG_STUCK => 'Collection backlog repeatedly triggered recovery.',
            default => throw new InvalidArgumentException(
                sprintf('Unknown health signal code: %s', $code)
            ),
        };
    }
}
