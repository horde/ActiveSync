<?php
require 'int_range_set.php';

$pass = 0;
$fail = 0;

function check(string $label, string $got, string $expected): void {
    global $pass, $fail;
    if ($got === $expected) {
        echo "  PASS  $label\n";
        $pass++;
    } else {
        echo "  FAIL  $label\n";
        echo "        expected: $expected\n";
        echo "        got:      $got\n";
        $fail++;
    }
}

function checkInt(string $label, int $got, int $expected): void {
    global $pass, $fail;
    if ($got === $expected) {
        echo "  PASS  $label\n";
        $pass++;
    } else {
        echo "  FAIL  $label\n";
        echo "        expected: $expected\n";
        echo "        got:      $got\n";
        $fail++;
    }
}

function checkBool(string $label, bool $got, bool $expected): void {
    global $pass, $fail;
    if ($got === $expected) {
        echo "  PASS  $label\n";
        $pass++;
    } else {
        echo "  FAIL  $label\n";
        echo "        expected: " . ($expected ? 'true' : 'false') . "\n";
        echo "        got:      " . ($got     ? 'true' : 'false') . "\n";
        $fail++;
    }
}



// -------------------------------------------------------------------------
echo "=== add() / addRange() ===\n";

$s = new IntRangeSet();
check('empty set', (string)$s, '');

$s->add(5);
check('add single', (string)$s, '5');

$s->add(10);
check('add non-adjacent', (string)$s, '5,10');

$s->add(6);
check('add adjacent right', (string)$s, '5:6,10');

$s->add(4);
check('add adjacent left', (string)$s, '4:6,10');

$s->add(5);
check('add duplicate', (string)$s, '4:6,10');

$s->add(7); $s->add(8); $s->add(9);
check('bridge two ranges', (string)$s, '4:10');

$s->addRange(6, 8);
check('addRange inside existing', (string)$s, '4:10');

$s->addRange(1, 4);
check('addRange extending left', (string)$s, '1:10');

$s->addRange(10, 13);
check('addRange extending right', (string)$s, '1:13');

$s->addRange(-2, 0);
check('addRange adjacent left boundary', (string)$s, '-2:13');

$s->addRange(14, 16);
check('addRange adjacent right boundary', (string)$s, '-2:16');

$s = new IntRangeSet();
$s->addRange(1, 3)->addRange(6, 8)->addRange(11, 13);
check('setup three ranges', (string)$s, '1:3,6:8,11:13');
$s->addRange(2, 12);
check('addRange spanning multiple ranges', (string)$s, '1:13');

$s = new IntRangeSet();
$s->addRange(7, 7);
check('addRange single value', (string)$s, '7');

$s = new IntRangeSet();
$s->addRange(10, 20);
$s->addRange(1, 3);
check('addRange before all', (string)$s, '1:3,10:20');

$s->addRange(25, 30);
check('addRange after all', (string)$s, '1:3,10:20,25:30');

// addRange reversed
$s = new IntRangeSet();
$s->addRange(10, 5);
check('addRange reversed', (string)$s, '5:10');

// removeRange reversed
$s = new IntRangeSet();
$s->addRange(1, 20);
$s->removeRange(15, 5);
check('removeRange reversed', (string)$s, '1:4,16:20');

// fromString reversed range is accepted and normalized
$s = IntRangeSet::fromString('10:5');
check('fromString reversed accepted', (string)$s, '5:10');

// fromString invalid input throws
try {
    IntRangeSet::fromString('abc');
    check('fromString invalid throws', 'no exception', 'exception');
} catch (\InvalidArgumentException $e) {
    check('fromString invalid throws', 'exception', 'exception');
}

// -------------------------------------------------------------------------
echo "\n=== remove() / removeRange() ===\n";

$s = new IntRangeSet();
$s->remove(5);
check('remove from empty', (string)$s, '');

$s->addRange(1, 10);
$s->remove(15);
check('remove non-existent', (string)$s, '1:10');

$s->remove(1);
check('remove start', (string)$s, '2:10');

$s->remove(10);
check('remove end', (string)$s, '2:9');

$s->remove(5);
check('remove middle splits', (string)$s, '2:4,6:9');

$s = new IntRangeSet();
$s->add(7);
$s->remove(7);
check('remove sole element', (string)$s, '');

$s = new IntRangeSet();
$s->addRange(1, 20);
$s->removeRange(8, 12);
check('removeRange inside splits', (string)$s, '1:7,13:20');

$s = new IntRangeSet();
$s->addRange(5, 10);
$s->removeRange(5, 10);
check('removeRange exact match', (string)$s, '');

$s = new IntRangeSet();
$s->addRange(5, 10);
$s->removeRange(3, 7);
check('removeRange overlapping left', (string)$s, '8:10');

$s = new IntRangeSet();
$s->addRange(5, 10);
$s->removeRange(8, 13);
check('removeRange overlapping right', (string)$s, '5:7');

$s = new IntRangeSet();
$s->addRange(1, 3)->addRange(6, 8)->addRange(11, 13);
$s->removeRange(1, 13);
check('removeRange wipes multiple', (string)$s, '');

$s = new IntRangeSet();
$s->addRange(1, 5)->addRange(10, 15)->addRange(20, 25);
$s->removeRange(3, 22);
check('removeRange partial trim both ends', (string)$s, '1:2,23:25');

$s = new IntRangeSet();
$s->addRange(10, 20);
$s->removeRange(1, 5);
check('removeRange before all', (string)$s, '10:20');

$s->removeRange(25, 30);
check('removeRange after all', (string)$s, '10:20');

$s = new IntRangeSet();
$s->addRange(1, 10);
$s->removeRange(5, 5);
check('removeRange single value', (string)$s, '1:4,6:10');

// diff() partial remainder after B exhausted mid-overlap
$a = new IntRangeSet();
$a->addRange(1, 20);
$b = new IntRangeSet();
$b->addRange(1, 10);
checkDiff('diff partial remainder a after b exhausted',
    IntRangeSet::diff($a, $b),
    '11:20', '1:10', '');

// diff() single values disjoint
checkDiff('diff single values disjoint',
    IntRangeSet::diff(IntRangeSet::fromString('1,3,5'), IntRangeSet::fromString('2,4,6')),
    '1,3,5', '', '2,4,6');

// subtract self
$a = new IntRangeSet();
$a->addRange(1, 10);
$a->subtract($a);
check('subtract self', (string)$a, '');

// count after subtract
$a = new IntRangeSet();
$a->addRange(1, 10);
$b = new IntRangeSet();
$b->addRange(3, 7);
$a->subtract($b);
checkInt('count after subtract', $a->count(), 5);

// -------------------------------------------------------------------------
echo "\n=== fromString() / __toString() ===\n";

$s = IntRangeSet::fromString('');
check('fromString empty', (string)$s, '');

$s = IntRangeSet::fromString('7');
check('fromString single value', (string)$s, '7');

$s = IntRangeSet::fromString('1:5');
check('fromString single range', (string)$s, '1:5');

$s = IntRangeSet::fromString('1:5,7,10:15');
check('fromString mixed', (string)$s, '1:5,7,10:15');

$original = '1:5,7,10:15';
$s = IntRangeSet::fromString($original);
check('round-trip simple', (string)$s, $original);

$s = IntRangeSet::fromString('1:5,3:8');
check('fromString merges overlapping', (string)$s, '1:8');

$s = IntRangeSet::fromString('1:5,6:10');
check('fromString merges adjacent', (string)$s, '1:10');

// fromString colon only throws
try {
    IntRangeSet::fromString(':');
    check('fromString colon only throws', 'no exception', 'exception');
} catch (\InvalidArgumentException $e) {
    check('fromString colon only throws', 'exception', 'exception');
}

// fromString empty part throws
try {
    IntRangeSet::fromString('1,,3');
    check('fromString empty part throws', 'no exception', 'exception');
} catch (\InvalidArgumentException $e) {
    check('fromString empty part throws', 'exception', 'exception');
}

// -------------------------------------------------------------------------
echo "\n=== union() ===\n";

// Union with empty set
$a = new IntRangeSet();
$a->addRange(1, 10);
$a->union(new IntRangeSet());
check('union with empty', (string)$a, '1:10');

// Union from empty set
$a = new IntRangeSet();
$b = new IntRangeSet();
$b->addRange(1, 10);
$a->union($b);
check('union from empty', (string)$a, '1:10');

// Union non-overlapping
$a = new IntRangeSet();
$a->addRange(1, 5);
$b = new IntRangeSet();
$b->addRange(10, 15);
$a->union($b);
check('union non-overlapping', (string)$a, '1:5,10:15');

// Union overlapping
$a = new IntRangeSet();
$a->addRange(1, 10);
$b = new IntRangeSet();
$b->addRange(5, 15);
$a->union($b);
check('union overlapping', (string)$a, '1:15');

// Union adjacent
$a = new IntRangeSet();
$a->addRange(1, 5);
$b = new IntRangeSet();
$b->addRange(6, 10);
$a->union($b);
check('union adjacent', (string)$a, '1:10');

// Union with self
$a = new IntRangeSet();
$a->addRange(1, 10);
$a->union($a);
check('union with self', (string)$a, '1:10');

// union() cross-side merge bug check
$a = new IntRangeSet();
$a->addRange(1, 5)->addRange(8, 12);
$b = new IntRangeSet();
$b->addRange(6, 9);
$a->union($b);
check('union cross-side merge', (string)$a, '1:12');

// -------------------------------------------------------------------------
echo "\n=== intersect() ===\n";

// Intersect with empty
$a = new IntRangeSet();
$a->addRange(1, 10);
$a->intersect(new IntRangeSet());
check('intersect with empty', (string)$a, '');

// Intersect from empty
$a = new IntRangeSet();
$b = new IntRangeSet();
$b->addRange(1, 10);
$a->intersect($b);
check('intersect from empty', (string)$a, '');

// Intersect identical
$a = new IntRangeSet();
$a->addRange(1, 10);
$b = new IntRangeSet();
$b->addRange(1, 10);
$a->intersect($b);
check('intersect identical', (string)$a, '1:10');

// Intersect non-overlapping
$a = new IntRangeSet();
$a->addRange(1, 5);
$b = new IntRangeSet();
$b->addRange(10, 15);
$a->intersect($b);
check('intersect non-overlapping', (string)$a, '');

// Intersect partial overlap
$a = new IntRangeSet();
$a->addRange(1, 10);
$b = new IntRangeSet();
$b->addRange(5, 15);
$a->intersect($b);
check('intersect partial overlap', (string)$a, '5:10');

// A contains B
$a = new IntRangeSet();
$a->addRange(1, 20);
$b = new IntRangeSet();
$b->addRange(5, 10);
$a->intersect($b);
check('intersect a contains b', (string)$a, '5:10');

// Multiple ranges
$a = new IntRangeSet();
$a->addRange(1, 5)->addRange(10, 15)->addRange(20, 25);
$b = new IntRangeSet();
$b->addRange(3, 12)->addRange(22, 30);
$a->intersect($b);
check('intersect multiple ranges', (string)$a, '3:5,10:12,22:25');

// -------------------------------------------------------------------------
echo "\n=== subtract() ===\n";

$a = new IntRangeSet();
$a->addRange(1, 10);
$a->subtract(new IntRangeSet());
check('subtract empty set', (string)$a, '1:10');

$a = new IntRangeSet();
$b = new IntRangeSet();
$b->addRange(1, 10);
$a->subtract($b);
check('subtract from empty set', (string)$a, '');

$a = new IntRangeSet();
$a->addRange(1, 10);
$b = new IntRangeSet();
$b->addRange(1, 10);
$a->subtract($b);
check('subtract identical set', (string)$a, '');

$a = new IntRangeSet();
$a->addRange(1, 5);
$b = new IntRangeSet();
$b->addRange(7, 10);
$a->subtract($b);
check('subtract non-overlapping', (string)$a, '1:5');

$a = new IntRangeSet();
$a->addRange(1, 20);
$b = new IntRangeSet();
$b->addRange(8, 12);
$a->subtract($b);
check('subtract splits range', (string)$a, '1:7,13:20');

$a = new IntRangeSet();
$a->addRange(5, 10);
$b = new IntRangeSet();
$b->addRange(1, 20);
$a->subtract($b);
check('subtract superset', (string)$a, '');

$a = new IntRangeSet();
$a->addRange(1, 30);
$b = new IntRangeSet();
$b->addRange(5, 8)->addRange(12, 15)->addRange(20, 25);
$a->subtract($b);
check('subtract multiple from one', (string)$a, '1:4,9:11,16:19,26:30');

$a = new IntRangeSet();
$a->addRange(1, 5)->addRange(8, 12)->addRange(15, 20);
$b = new IntRangeSet();
$b->addRange(3, 17);
$a->subtract($b);
check('subtract one from multiple', (string)$a, '1:2,18:20');

$a = new IntRangeSet();
$a->addRange(1, 10);
$b = new IntRangeSet();
$b->addRange(3, 7);
$a->subtract($b);
check('other set unchanged', (string)$b, '3:7');

// -------------------------------------------------------------------------
echo "\n=== diff() ===\n";

function checkDiff(string $label, array $diff, string $removed, string $unchanged, string $added): void {
    global $pass, $fail;
    [$r, $u, $a] = $diff;
    $ok = (string)$r === $removed
       && (string)$u === $unchanged
       && (string)$a === $added;
    if ($ok) {
        echo "  PASS  $label\n";
        $pass++;
    } else {
        echo "  FAIL  $label\n";
        if ((string)$r !== $removed)   echo "        removed   expected: $removed   got: $r\n";
        if ((string)$u !== $unchanged) echo "        unchanged expected: $unchanged got: $u\n";
        if ((string)$a !== $added)     echo "        added     expected: $added     got: $a\n";
        $fail++;
    }
}

// Both empty
checkDiff('both empty',
    IntRangeSet::diff(new IntRangeSet(), new IntRangeSet()),
    '', '', '');

// A empty, B non-empty
checkDiff('a empty',
    IntRangeSet::diff(new IntRangeSet(), IntRangeSet::fromString('1:5')),
    '', '', '1:5');

// B empty, A non-empty
checkDiff('b empty',
    IntRangeSet::diff(IntRangeSet::fromString('1:5'), new IntRangeSet()),
    '1:5', '', '');

// Identical sets
checkDiff('identical sets',
    IntRangeSet::diff(IntRangeSet::fromString('1:5,10:15'), IntRangeSet::fromString('1:5,10:15')),
    '', '1:5,10:15', '');

// Completely disjoint — A entirely before B
checkDiff('disjoint a before b',
    IntRangeSet::diff(IntRangeSet::fromString('1:5'), IntRangeSet::fromString('10:15')),
    '1:5', '', '10:15');

// Completely disjoint — B entirely before A
checkDiff('disjoint b before a',
    IntRangeSet::diff(IntRangeSet::fromString('10:15'), IntRangeSet::fromString('1:5')),
    '10:15', '', '1:5');

// Partial overlap — A extends left
checkDiff('partial overlap a extends left',
    IntRangeSet::diff(IntRangeSet::fromString('1:10'), IntRangeSet::fromString('5:15')),
    '1:4', '5:10', '11:15');

// Partial overlap — B extends left
checkDiff('partial overlap b extends left',
    IntRangeSet::diff(IntRangeSet::fromString('5:15'), IntRangeSet::fromString('1:10')),
    '11:15', '5:10', '1:4');

// A contains B
checkDiff('a contains b',
    IntRangeSet::diff(IntRangeSet::fromString('1:20'), IntRangeSet::fromString('5:10')),
    '1:4,11:20', '5:10', '');

// B contains A
checkDiff('b contains a',
    IntRangeSet::diff(IntRangeSet::fromString('5:10'), IntRangeSet::fromString('1:20')),
    '', '5:10', '1:4,11:20');

// Multiple ranges each side, interleaved
checkDiff('interleaved ranges',
    IntRangeSet::diff(IntRangeSet::fromString('1:5,11:15,21:25'), IntRangeSet::fromString('3:13,23:30')),
    '1:2,14:15,21:22', '3:5,11:13,23:25', '6:10,26:30');

// Single values
checkDiff('single values overlap',
    IntRangeSet::diff(IntRangeSet::fromString('1,2,3'), IntRangeSet::fromString('2,3,4')),
    '1', '2:3', '4');

// -------------------------------------------------------------------------
echo "\n=== getIterator() ===\n";

// Empty set
$result = [];
foreach (new IntRangeSet() as $v) $result[] = $v;
check('iterate empty', implode(',', $result), '');

// Single value
$result = [];
foreach (IntRangeSet::fromString('5') as $v) $result[] = $v;
check('iterate single value', implode(',', $result), '5');

// Single range
$result = [];
foreach (IntRangeSet::fromString('1:5') as $v) $result[] = $v;
check('iterate single range', implode(',', $result), '1,2,3,4,5');

// Multiple ranges
$result = [];
foreach (IntRangeSet::fromString('1:3,7,10:12') as $v) $result[] = $v;
check('iterate multiple ranges', implode(',', $result), '1,2,3,7,10,11,12');

// Ranges are always stored in ascending order
$s = new IntRangeSet();
$s->addRange(5, 7)->addRange(1, 3);
$result = [];
foreach ($s as $v) $result[] = $v;
check('iterate ascending order', implode(',', $result), '1,2,3,5,6,7');

// Iterate over negative numbers
$s = new IntRangeSet();
$s->addRange(-3, -1);
$result = [];
foreach ($s as $v) $result[] = $v;
check('iterate negative numbers', implode(',', $result), '-3,-2,-1');

// diff() partial remainder carried across multiple iterations
checkDiff('diff partial remainder carried across iterations',
    IntRangeSet::diff(IntRangeSet::fromString('1:30'), IntRangeSet::fromString('5:10,15:20,25:30')),
    '1:4,11:14,21:24', '5:10,15:20,25:30', '');

// -------------------------------------------------------------------------
echo "\n=== contains() ===\n";

$s = new IntRangeSet();
$s->addRange(1, 5)->addRange(10, 15);
checkBool('contains inside first range',  $s->contains(3),  true);
checkBool('contains start of range',      $s->contains(1),  true);
checkBool('contains end of range',        $s->contains(5),  true);
checkBool('contains inside second range', $s->contains(12), true);
checkBool('contains gap between ranges',  $s->contains(7),  false);
checkBool('contains before all ranges',   $s->contains(0),  false);
checkBool('contains after all ranges',    $s->contains(16), false);

// -------------------------------------------------------------------------
echo "\n=== count() ===\n";

$s = new IntRangeSet();
checkInt('count empty', $s->count(), 0);
$s->add(5);
checkInt('count single', $s->count(), 1);
$s->addRange(1, 3);
checkInt('count after addRange', $s->count(), 4);
$s->addRange(10, 14);
checkInt('count two ranges', $s->count(), 9);
$s->removeRange(2, 11);
checkInt('count after removeRange', $s->count(), 4);

// -------------------------------------------------------------------------
echo "\n=== Results ===\n";
echo "Passed: $pass\n";
echo "Failed: $fail\n";
