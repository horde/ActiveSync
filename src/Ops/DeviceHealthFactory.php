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

use Horde_ActiveSync_Device;
use Horde_ActiveSync_SyncCache;

final class DeviceHealthFactory
{
    private readonly HealthEvaluator $evaluator;

    public function __construct(?HealthEvaluator $evaluator = null)
    {
        $this->evaluator = $evaluator ?? new HealthEvaluator();
    }

    public function fromDevice(
        Horde_ActiveSync_Device $device,
        Horde_ActiveSync_SyncCache $cache,
        ?int $lastSyncTs = null
    ): DeviceHealth {
        $properties = is_array($device->properties)
            ? $device->properties
            : [];
        $hbinterval = self::nullableInt($cache->hbinterval);
        $wait = self::nullableInt($cache->wait);
        if ($hbinterval === null && $wait !== null) {
            $hbinterval = $wait * 60;
        }

        return $this->evaluator->evaluate(new DeviceFacts(
            user: (string) $device->user,
            deviceId: (string) $device->id,
            deviceType: (string) $device->deviceType,
            version: self::nullableString($properties['version'] ?? null),
            rwstatus: (int) $device->rwstatus,
            accountOnlyRwstatus: (int) $device->accountOnlyRwstatus,
            blocked: !empty($properties['blocked']),
            lastSyncTs: $lastSyncTs,
            cacheTimestamp: self::nullableInt($cache->timestamp),
            hbinterval: $hbinterval,
            lasthbsyncstarted: self::nullableInt($cache->lasthbsyncstarted),
            lastsyncendnormal: self::nullableInt($cache->lastsyncendnormal),
            foldersyncrequired: $cache->getFolderSyncRequiredIgnoredCount(),
            collections: self::collectionFacts($cache->getCollections(false))
        ));
    }

    public function fromRow(
        array $deviceRow,
        array $cacheData,
        ?int $lastSyncTs = null
    ): DeviceHealth {
        $properties = self::properties($deviceRow['device_properties'] ?? []);
        $hbinterval = self::nullableInt($cacheData['hbinterval'] ?? null);
        $wait = self::nullableInt($cacheData['wait'] ?? null);
        if ($hbinterval === null && $wait !== null) {
            $hbinterval = $wait * 60;
        }

        return $this->evaluator->evaluate(new DeviceFacts(
            user: (string) ($deviceRow['device_user'] ?? ''),
            deviceId: (string) ($deviceRow['device_id'] ?? ''),
            deviceType: (string) ($deviceRow['device_type'] ?? ''),
            version: self::nullableString($properties['version'] ?? null),
            rwstatus: (int) ($deviceRow['device_rwstatus'] ?? 0),
            accountOnlyRwstatus:
                (int) ($deviceRow['device_accountonly_rwstatus'] ?? 0),
            blocked: !empty($properties['blocked']),
            lastSyncTs: $lastSyncTs,
            cacheTimestamp: self::nullableInt($cacheData['timestamp'] ?? null),
            hbinterval: $hbinterval,
            lasthbsyncstarted:
                self::nullableInt($cacheData['lasthbsyncstarted'] ?? null),
            lastsyncendnormal:
                self::nullableInt($cacheData['lastsyncendnormal'] ?? null),
            foldersyncrequired:
                (int) ($cacheData['foldersyncrequired'] ?? 0),
            collections: self::collectionFacts(
                is_array($cacheData['collections'] ?? null)
                    ? $cacheData['collections']
                    : []
            )
        ));
    }

    private static function properties(mixed $properties): array
    {
        if (is_array($properties)) {
            return $properties;
        }
        if (!is_string($properties) || $properties === '') {
            return [];
        }

        $decoded = @unserialize(
            $properties,
            ['allowed_classes' => false]
        );

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return CollectionFacts[]
     */
    private static function collectionFacts(array $collections): array
    {
        $facts = [];
        foreach ($collections as $id => $collection) {
            if (!is_array($collection)) {
                continue;
            }
            $collectionId = (string) ($collection['id'] ?? $id);
            $facts[] = new CollectionFacts(
                id: $collectionId,
                class: self::nullableString($collection['class'] ?? null),
                serverid:
                    self::nullableString($collection['serverid'] ?? null),
                lastsynckey:
                    self::nullableString($collection['lastsynckey'] ?? null),
                backlog: self::nullableInt($collection['backlog'] ?? null),
                backlogpings: (int) ($collection['backlogpings'] ?? 0),
                pingable: !empty($collection['pingable'])
            );
        }

        return $facts;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === false || $value === ''
            ? null
            : (int) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null || $value === false || $value === ''
            ? null
            : (string) $value;
    }
}
