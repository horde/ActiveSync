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

use BadMethodCallException;
use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CollectionFactList::class)]
#[CoversClass(CollectionFacts::class)]
class CollectionFactListTest extends TestCase
{
    public function testIndexesSnapshotsByCollectionId(): void
    {
        $mail = new CollectionFacts(id: 'mail', class: 'Email', serverid: 'INBOX');
        $calendar = new CollectionFacts(id: 'calendar', class: 'Calendar');
        $list = new CollectionFactList([$mail, $calendar]);

        $this->assertCount(2, $list);
        $this->assertSame($mail, $list->get('mail'));
        $this->assertSame($calendar, $list['calendar']);
        $this->assertNull($list->get('missing'));
        $this->assertFalse(isset($list['missing']));
        $this->assertSame(['mail', 'calendar'], array_map(
            static fn (CollectionFacts $facts): string => $facts->id,
            $list->all()
        ));
    }

    public function testLaterSnapshotReplacesTheSameId(): void
    {
        $list = new CollectionFactList([
            new CollectionFacts(id: 'mail', class: 'Email'),
            new CollectionFacts(id: 'mail', class: 'Calendar'),
        ]);

        $this->assertCount(1, $list);
        $this->assertSame('Calendar', $list['mail']->class);
    }

    public function testRejectsEntriesThatAreNotSnapshots(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CollectionFactList([new CollectionFacts(id: 'mail'), 'mail']);
    }

    public function testMissingOffsetThrows(): void
    {
        $list = new CollectionFactList([new CollectionFacts(id: 'mail')]);

        $this->expectException(OutOfBoundsException::class);
        $list['calendar'];
    }

    public function testListIsImmutable(): void
    {
        $list = new CollectionFactList();

        $this->expectException(BadMethodCallException::class);
        $list['mail'] = new CollectionFacts(id: 'mail');
    }
}
