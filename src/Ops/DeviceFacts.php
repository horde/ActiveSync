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

final class DeviceFacts
{
    /**
     * @param CollectionFacts[] $collections
     */
    public function __construct(
        public readonly string $user,
        public readonly string $deviceId,
        public readonly string $deviceType,
        public readonly ?string $version,
        public readonly int $rwstatus,
        public readonly int $accountOnlyRwstatus,
        public readonly bool $blocked,
        public readonly ?int $lastSyncTs,
        public readonly ?int $cacheTimestamp,
        public readonly ?int $hbinterval,
        public readonly ?int $lasthbsyncstarted,
        public readonly ?int $lastsyncendnormal,
        public readonly int $foldersyncrequired,
        public readonly array $collections
    ) {
    }
}
