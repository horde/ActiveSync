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

namespace Horde\ActiveSync\Calendar;

/**
 * A calendar date or date-time resolved to a point in time.
 */
final class ResolvedTime
{
    /**
     * @param int         $timestamp       Unix timestamp.
     * @param string|null $unresolvedTzid  Set when the value named a TZID that
     *                                     could not be resolved. The timestamp
     *                                     then reads the wall-clock time in the
     *                                     server time zone, so the caller can
     *                                     report it.
     */
    public function __construct(
        public readonly int $timestamp,
        public readonly ?string $unresolvedTzid = null
    ) {
    }
}
