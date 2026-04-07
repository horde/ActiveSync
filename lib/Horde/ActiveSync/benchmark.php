<?php

declare(strict_types=1);
require 'int_range_set.php';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function mem(): int
{
    return memory_get_usage(false);
}

function benchmark(string $label, callable $setup, callable $fn, int $iterations = 5): void
{
    // Warmup
    $data = $setup();
    $fn($data);

    $times = [];
    $memUsed = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $data = $setup();
        $memBefore = mem();
        $start = hrtime(true);
        $fn($data);
        $elapsed = hrtime(true) - $start;
        $memUsed = mem() - $memBefore;
        $times[] = $elapsed;
    }

    sort($times);
    $median = $times[(int)($iterations / 2)];
    printf("  %-50s %8.2f ms   mem: %+d KB\n",
        $label,
        $median / 1e6,
        (int)($memUsed / 1024)
    );
}

function printMemory(string $label, int $bytes): void
{
    printf("  %-50s %d KB\n", $label, (int)($bytes / 1024));
}

function section(string $title): void
{
    echo "\n=== $title ===\n";
}

// ---------------------------------------------------------------------------
// Data generators
// ---------------------------------------------------------------------------

function makeRanges(int $count, int $avgSize, int $max): array
{
    $ranges = [];
    $pos = 1;
    for ($i = 0; $i < $count; $i++) {
        $start = $pos + rand(1, 10);
        $end   = $start + rand(1, $avgSize * 2 - 1);
        if ($end > $max) break;
        $ranges[] = [$start, $end];
        $pos = $end + 1;
    }
    return $ranges;
}

function makeIntRangeSet(array $ranges): IntRangeSet
{
    $s = new IntRangeSet();
    foreach ($ranges as [$start, $end]) $s->addRange($start, $end);
    return $s;
}

function makeIntArray(array $ranges): array
{
    $arr = [];
    foreach ($ranges as [$start, $end])
        for ($i = $start; $i <= $end; $i++) $arr[] = $i;
    return $arr;
}

function makeUidString(array $ranges): string
{
    return implode(',', array_map(
        fn($r) => $r[0] === $r[1] ? (string)$r[0] : "{$r[0]}:{$r[1]}",
        $ranges
    ));
}

// ---------------------------------------------------------------------------
// Standard test data
// ---------------------------------------------------------------------------

$ranges      = makeRanges(500, 10, 100_000);    // ~500 ranges, ~5000 integers
$rangesLarge = makeRanges(500, 100, 1_000_000); // ~500 ranges, ~50000 integers
$rangesA     = makeRanges(500, 100, 1_000_000);
$rangesB     = makeRanges(500, 100, 1_000_000);

// ---------------------------------------------------------------------------
// 1. Build
// ---------------------------------------------------------------------------

section('Build (500 ranges, ~5000 integers)');

benchmark('IntRangeSet::addRange()', fn() => null, function() use ($ranges) {
    makeIntRangeSet($ranges);
});

benchmark('array push + sort', fn() => null, function() use ($ranges) {
    $arr = makeIntArray($ranges);
    sort($arr);
});

// ---------------------------------------------------------------------------
// 2. Memory footprint
// ---------------------------------------------------------------------------

section('Memory footprint (500 ranges, ~5000 integers)');

$memBefore = mem(); $set = makeIntRangeSet($ranges); $memSet = mem() - $memBefore;
$memBefore = mem(); $arr = makeIntArray($ranges);    $memArr = mem() - $memBefore;
printMemory('IntRangeSet', $memSet);
printMemory('int array',   $memArr);
unset($set, $arr);

section('Memory footprint — dense set (1 range, 1000000 integers)');

$memBefore = mem(); $set = (new IntRangeSet())->addRange(1, 1_000_000); $memSet = mem() - $memBefore;
$memBefore = mem(); $arr = range(1, 1_000_000);                         $memArr = mem() - $memBefore;
printMemory('IntRangeSet (1 range)', $memSet);
printMemory('int array (1M items)',  $memArr);
unset($set, $arr);

// ---------------------------------------------------------------------------
// 3. contains()
// ---------------------------------------------------------------------------

section('contains() — 1000 random lookups');

$lookups = array_map(fn() => rand(1, 100_000), range(1, 1000));

benchmark('IntRangeSet::contains()', fn() => makeIntRangeSet($ranges), function($set) use ($lookups) {
    foreach ($lookups as $n) $set->contains($n);
});

benchmark('in_array()', fn() => makeIntArray($ranges), function($arr) use ($lookups) {
    foreach ($lookups as $n) in_array($n, $arr);
});

benchmark('array binary search', fn() => makeIntArray($ranges), function($arr) use ($lookups) {
    foreach ($lookups as $n) {
        $lo = 0; $hi = count($arr) - 1;
        while ($lo <= $hi) {
            $mid = ($lo + $hi) >> 1;
            if ($arr[$mid] < $n)     $lo = $mid + 1;
            elseif ($arr[$mid] > $n) $hi = $mid - 1;
            else break;
        }
    }
});

section('contains() — 1000 sequential lookups');

$sequential = range(1, 1000);

benchmark('IntRangeSet::contains() sequential', fn() => makeIntRangeSet($ranges), function($set) use ($sequential) {
    foreach ($sequential as $n) $set->contains($n);
});

benchmark('array binary search sequential', fn() => makeIntArray($ranges), function($arr) use ($sequential) {
    foreach ($sequential as $n) {
        $lo = 0; $hi = count($arr) - 1;
        while ($lo <= $hi) {
            $mid = ($lo + $hi) >> 1;
            if ($arr[$mid] < $n)     $lo = $mid + 1;
            elseif ($arr[$mid] > $n) $hi = $mid - 1;
            else break;
        }
    }
});

// ---------------------------------------------------------------------------
// 4. Iteration
// ---------------------------------------------------------------------------

section('Iteration over all elements');

benchmark('IntRangeSet::getIterator()', fn() => makeIntRangeSet($ranges), function($set) {
    $sum = 0;
    foreach ($set as $v) $sum += $v;
});

benchmark('array foreach', fn() => makeIntArray($ranges), function($arr) {
    $sum = 0;
    foreach ($arr as $v) $sum += $v;
});

// ---------------------------------------------------------------------------
// 5. count()
// ---------------------------------------------------------------------------

section('count() x1000');

benchmark('IntRangeSet::count()', fn() => makeIntRangeSet($ranges), function($set) {
    for ($i = 0; $i < 1000; $i++) $set->count();
});

benchmark('count(array)', fn() => makeIntArray($ranges), function($arr) {
    for ($i = 0; $i < 1000; $i++) count($arr);
});

// ---------------------------------------------------------------------------
// 6. add/remove individual values
// ---------------------------------------------------------------------------

section('add() / remove() — 1000 individual values');

$values = array_map(fn() => rand(1, 100_000), range(1, 1000));

benchmark('IntRangeSet::add() x1000', fn() => makeIntRangeSet($ranges), function($set) use ($values) {
    foreach ($values as $v) $set->add($v);
});

benchmark('array_push + sort x1000', fn() => makeIntArray($ranges), function($arr) use ($values) {
    foreach ($values as $v) {
        if (!in_array($v, $arr)) { $arr[] = $v; sort($arr); }
    }
});

benchmark('IntRangeSet::remove() x1000', fn() => makeIntRangeSet($ranges), function($set) use ($values) {
    foreach ($values as $v) $set->remove($v);
});

benchmark('array_search + unset x1000', fn() => makeIntArray($ranges), function($arr) use ($values) {
    foreach ($values as $v) {
        $k = array_search($v, $arr);
        if ($k !== false) array_splice($arr, $k, 1);
    }
});

// ---------------------------------------------------------------------------
// 7. Worst-case addRange() — reverse order
// ---------------------------------------------------------------------------

section('addRange() — reverse order (worst case for splice)');

$reversedRanges = array_reverse($ranges);

benchmark('IntRangeSet::addRange() reverse', fn() => null, function() use ($reversedRanges) {
    makeIntRangeSet($reversedRanges);
});

benchmark('IntRangeSet::addRange() forward', fn() => null, function() use ($ranges) {
    makeIntRangeSet($ranges);
});

// ---------------------------------------------------------------------------
// 8. Large number of tiny ranges
// ---------------------------------------------------------------------------

section('Large number of tiny ranges (10000 single-element ranges)');

$singleRanges = array_map(fn($i) => [$i * 2, $i * 2], range(1, 10_000)); // all odd gaps

benchmark('IntRangeSet build 10000 singles', fn() => null, function() use ($singleRanges) {
    makeIntRangeSet($singleRanges);
});

benchmark('array build 10000 singles', fn() => null, function() use ($singleRanges) {
    makeIntArray($singleRanges);
});

// ---------------------------------------------------------------------------
// 9. removeRange() spanning many ranges
// ---------------------------------------------------------------------------

section('removeRange() spanning many ranges');

benchmark('IntRangeSet::removeRange() spanning all', fn() => makeIntRangeSet($ranges), function($set) {
    $set->removeRange(0, 100_000);
});

benchmark('array_filter equivalent', fn() => makeIntArray($ranges), function($arr) {
    array_filter($arr, fn($v) => $v < 0 || $v > 100_000);
});

// ---------------------------------------------------------------------------
// 10. subtract()
// ---------------------------------------------------------------------------

section('union() vs array_merge+sort+unique');

benchmark('IntRangeSet::union()', fn() => [makeIntRangeSet($rangesA), makeIntRangeSet($rangesB)], function($data) {
    [$a, $b] = $data;
    $a->union($b);
});

benchmark('array_merge+unique+sort', fn() => [makeIntArray($rangesA), makeIntArray($rangesB)], function($data) {
    [$a, $b] = $data;
    $result = array_unique(array_merge($a, $b));
    sort($result);
});

section('intersect() vs array_intersect()');

benchmark('IntRangeSet::intersect()', fn() => [makeIntRangeSet($rangesA), makeIntRangeSet($rangesB)], function($data) {
    [$a, $b] = $data;
    $a->intersect($b);
});

benchmark('array_intersect()', fn() => [makeIntArray($rangesA), makeIntArray($rangesB)], function($data) {
    [$a, $b] = $data;
    array_intersect($a, $b);
});


$rangesA = makeRanges(500, 100, 1_000_000);
$rangesB = makeRanges(500, 100, 1_000_000);

benchmark('IntRangeSet::subtract()', fn() => [makeIntRangeSet($rangesA), makeIntRangeSet($rangesB)], function($data) {
    [$a, $b] = $data;
    $a->subtract($b);
});

benchmark('array_diff()', fn() => [makeIntArray($rangesA), makeIntArray($rangesB)], function($data) {
    [$a, $b] = $data;
    array_diff($a, $b);
});

// ---------------------------------------------------------------------------
// 11. diff() variants
// ---------------------------------------------------------------------------

section('diff() — identical sets');

benchmark('IntRangeSet::diff() identical', fn() => [makeIntRangeSet($rangesA), makeIntRangeSet($rangesA)], function($data) {
    [$a, $b] = $data;
    IntRangeSet::diff($a, $b);
});

section('diff() — completely disjoint sets');

$rangesC = makeRanges(500, 10, 50_000);
$rangesD = makeRanges(500, 10, 50_000);
// Force disjoint by offsetting D
$rangesD = array_map(fn($r) => [$r[0] + 60_000, $r[1] + 60_000], $rangesD);

benchmark('IntRangeSet::diff() disjoint', fn() => [makeIntRangeSet($rangesC), makeIntRangeSet($rangesD)], function($data) {
    [$a, $b] = $data;
    IntRangeSet::diff($a, $b);
});

benchmark('array_diff() disjoint', fn() => [makeIntArray($rangesC), makeIntArray($rangesD)], function($data) {
    [$a, $b] = $data;
    array_diff($a, $b);
    array_diff($b, $a);
    array_intersect($a, $b);
});

section('diff() — large overlapping sets (~50000 integers each)');

benchmark('IntRangeSet::diff()', fn() => [makeIntRangeSet($rangesLarge), makeIntRangeSet($rangesLarge)], function($data) {
    [$a, $b] = $data;
    IntRangeSet::diff($a, $b);
});

benchmark('array_diff() on int arrays', fn() => [makeIntArray($rangesLarge), makeIntArray($rangesLarge)], function($data) {
    [$a, $b] = $data;
    array_diff($a, $b);
    array_diff($b, $a);
    array_intersect($a, $b);
});

// ---------------------------------------------------------------------------
// 12. fromString()
// ---------------------------------------------------------------------------

section('fromString() parsing');

$uidString      = makeUidString($ranges);
$uidStringLarge = makeUidString($rangesLarge);


$uidStringUnsorted = makeUidString(array_reverse($ranges));

benchmark('IntRangeSet::fromString() ~500 ranges', fn() => null, function() use ($uidString) {
    IntRangeSet::fromString($uidString);
});

benchmark('IntRangeSet::fromString() ~500 ranges unsorted', fn() => null, function() use ($uidStringUnsorted) {
    IntRangeSet::fromString($uidStringUnsorted);
});

benchmark('explode+array build ~500 ranges sorted', fn() => null, function() use ($uidString) {
    $arr = [];
    foreach (explode(',', $uidString) as $part) {
        $m = explode(':', $part);
        $start = (int)$m[0]; $end = isset($m[1]) ? (int)$m[1] : $start;
        for ($i = $start; $i <= $end; $i++) $arr[] = $i;
    }
});

benchmark('explode+array build ~500 ranges unsorted+dedup', fn() => null, function() use ($uidStringUnsorted) {
    $arr = [];
    foreach (explode(',', $uidStringUnsorted) as $part) {
        $m = explode(':', $part);
        $start = (int)$m[0]; $end = isset($m[1]) ? (int)$m[1] : $start;
        for ($i = $start; $i <= $end; $i++) $arr[] = $i;
    }
    $arr = array_unique($arr);
    sort($arr);
});

// ---------------------------------------------------------------------------
// 13. Simulated IMAP sync
// ---------------------------------------------------------------------------

section('Simulated IMAP sync (parse, diff, add, remove)');

$prevRanges = makeRanges(400, 10, 100_000);
$newRanges  = makeRanges(400, 10, 100_000);
$prevString = makeUidString($prevRanges);
$newString  = makeUidString($newRanges);

benchmark('IntRangeSet IMAP sync', fn() => null, function() use ($prevString, $newString) {
    $prev = IntRangeSet::fromString($prevString);
    $curr = IntRangeSet::fromString($newString);
    [$removed, $unchanged, $added] = IntRangeSet::diff($prev, $curr);
    $result = clone $prev;
    $result->subtract($removed)->union($added);
});

benchmark('array IMAP sync', fn() => null, function() use ($prevRanges, $newRanges) {
    $prev = makeIntArray($prevRanges);
    $curr = makeIntArray($newRanges);
    $removed   = array_diff($prev, $curr);
    $added     = array_diff($curr, $prev);
    $result    = array_diff($prev, $removed);
    $result    = array_merge($result, $added);
    sort($result);
});

echo "\n";
