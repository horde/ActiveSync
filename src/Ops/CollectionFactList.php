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

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use OutOfBoundsException;
use Traversable;

/**
 * Immutable collection-id map of SyncCache health snapshots.
 *
 * @implements ArrayAccess<string, CollectionFacts>
 * @implements IteratorAggregate<int, CollectionFacts>
 */
final class CollectionFactList implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * @var array<string, CollectionFacts>
     */
    private array $byId;

    /**
     * @param iterable<mixed, CollectionFacts> $collections
     */
    public function __construct(iterable $collections = [])
    {
        $byId = [];
        foreach ($collections as $collection) {
            if (!$collection instanceof CollectionFacts) {
                throw new InvalidArgumentException(
                    'Collection fact lists accept CollectionFacts entries only.'
                );
            }
            $byId[$collection->id] = $collection;
        }
        $this->byId = $byId;
    }

    public function get(string $id): ?CollectionFacts
    {
        return $this->byId[$id] ?? null;
    }

    /**
     * @return list<CollectionFacts>
     */
    public function all(): array
    {
        return array_values($this->byId);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->all());
    }

    public function count(): int
    {
        return count($this->byId);
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && isset($this->byId[$offset]);
    }

    public function offsetGet(mixed $offset): CollectionFacts
    {
        if (!is_string($offset) || !isset($this->byId[$offset])) {
            throw new OutOfBoundsException(
                'Collection fact list has no entry for this id.'
            );
        }

        return $this->byId[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('Collection fact lists are immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('Collection fact lists are immutable.');
    }
}
