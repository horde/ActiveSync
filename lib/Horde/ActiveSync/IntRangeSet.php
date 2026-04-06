<?php

declare(strict_types=1);
error_reporting(E_ALL);

/**
 * IntRangeSet v0.4
 *
 * Maintains an ordered, compact set of integer ranges.
 * Ranges are stored as [start, end] pairs in ascending order.
 *
 * String representation uses IMAP-style syntax: "1:5,7,10:15"
 * Note: fromString() only supports non-negative integers.
 *
 * @author Dmitry Petrov <dpetrov67@gmail.com>
 */
class IntRangeSet implements \IteratorAggregate
{
    /**
     * @param array<int, array{0: int, 1: int}> $ranges Sorted list of [start, end] pairs
     * @param int $count Cached total count of integers in the set
     */
    public function __construct(
        private array $ranges = [],
        private int $count = 0,
    ) {}

    /**
     * Return the index of the first range whose end >= $n, or count($this->ranges)
     * if no such range exists. Search starts at $lo.
     *
     * Early exits:
     *   - If $lo > last index or last range ends before $n, return past-the-end.
     *   - If the first candidate range already covers $n, return $lo immediately.
     */
    private function lowerBound(int $n, int $lo = 0): int
    {
        $hi = count($this->ranges) - 1;

        if ($hi < $lo || $this->ranges[$hi][1] < $n) return $hi + 1;
        if ($this->ranges[$lo][1] >= $n) return $lo;

        do {
            $mid = ($lo + $hi) >> 1;
            [, $midEnd] = $this->ranges[$mid];
            if ($midEnd < $n)       $lo = $mid + 1;
            elseif ($midEnd === $n) return $mid;
            else                    $hi = $mid - 1;
        } while ($lo <= $hi);

        return $lo;
    }

    /**
     * Add a single integer to the set, merging adjacent or overlapping ranges.
     */
    public function add(int $n): self
    {
        return $this->addRange($n, $n);
    }

    /**
     * Add a range of integers [start, end] to the set,
     * merging adjacent or overlapping ranges.
     * If start > end the values are swapped.
     *
     * @param int $lo Internal hint for lowerBound start index; do not use externally.
     */
    public function addRange(int $start, int $end, int $lo = 0): self
    {
        if ($start > $end) [$start, $end] = [$end, $start];

        $c = count($this->ranges);

        // First range whose end >= our start (could be adjacent or overlapping)
        $first = $this->lowerBound($start, $lo);

        // First range whose end >= our end (could be adjacent or overlapping)
        $last = $start === $end ? $first : $this->lowerBound($end, $first);

        // Absorb right neighbor if adjacent or overlapping
        if ($last < $c) {
            [$lastStart, $lastEnd] = $this->ranges[$last];
            if ($lastStart <= $end + 1) {
                $end = $lastEnd;
                $last++;
            }
        }

        // Check left neighbor for adjacency
        if ($first > 0) {
            [, $prevEnd] = $this->ranges[$first - 1];
            if ($prevEnd + 1 >= $start) --$first;
        }

        // Absorb left neighbor's start if it extends further left
        if ($first < $c) {
            [$firstStart] = $this->ranges[$first];
            $start = min($start, $firstStart);
        }

        // Compute count delta: subtract absorbed ranges, add new range
        $delta = $end - $start + 1;
        for ($k = $first; $k < $last; $k++) $delta -= $this->ranges[$k][1] - $this->ranges[$k][0] + 1;
        $this->count += $delta;

        if ($last - $first === 1) {
            $this->ranges[$first] = [$start, $end];
        } else {
            array_splice($this->ranges, $first, $last - $first, [[$start, $end]]);
        }
        return $this;
    }

    /**
     * Remove a range of integers [start, end] from the set,
     * splitting or trimming overlapping ranges as necessary.
     * If start > end the values are swapped.
     */
    public function removeRange(int $start, int $end): self
    {
        if ($start > $end) [$start, $end] = [$end, $start];

        $c = count($this->ranges);

        // First range whose end >= start (first potentially affected range)
        $first = $this->lowerBound($start);

        // No ranges are affected
        if ($first >= $c || $this->ranges[$first][0] > $end) {
            return $this;
        }

        // First range whose end >= end (last potentially affected range)
        $last = $start === $end ? $first : $this->lowerBound($end, $first);

        $replacement = [];

        // If the first affected range starts before $start, preserve its left portion
        [$firstStart] = $this->ranges[$first];
        if ($firstStart < $start) {
            $replacement[] = [$firstStart, $start - 1];
        }

        // If the last affected range ends after $end, preserve its right portion
        if ($last < $c) {
            [, $lastEnd] = $this->ranges[$last];
            if ($lastEnd > $end) {
                $replacement[] = [$end + 1, $lastEnd];
            }
            $last++;
        }

        // Compute delta: sum of absorbed ranges minus preserved portions
        $delta = 0;
        for ($k = $first; $k < $last; $k++) $delta += $this->ranges[$k][1] - $this->ranges[$k][0] + 1;
        foreach ($replacement as [$rs, $re]) $delta -= $re - $rs + 1;
        $this->count -= $delta;

        if ($last - $first === 1 && count($replacement) === 1) {
            $this->ranges[$first] = $replacement[0];
        } else {
            array_splice($this->ranges, $first, $last - $first, $replacement);
        }
        return $this;
    }

    /**
     * Remove a single integer from the set, splitting a range if necessary.
     */
    public function remove(int $n): self
    {
        return $this->removeRange($n, $n);
    }

    /**
     * Merge all ranges of another IntRangeSet into this set.
     * Uses a two-pointer sweep for O(m+n) performance.
     */
    public function union(IntRangeSet $other): self
    {
        $ranges = [];
        $count  = 0;
        $ac = count($this->ranges) - 1;
        $bc = count($other->ranges) - 1;
        $aHasMore = $ac >= 0;
        $bHasMore = $bc >= 0;
        $aIdx = $bIdx = -1;
        if ($aHasMore) { [$aStart, $aEnd] = $this->ranges[++$aIdx]; }
        if ($bHasMore) { [$bStart, $bEnd] = $other->ranges[++$bIdx]; }

        while ($aHasMore || $bHasMore) {
            if (!$bHasMore || ($aHasMore && $aStart <= $bStart)) {
                $curStart = $aStart; $curEnd = $aEnd;
                if ($aHasMore = $aIdx < $ac) { [$aStart, $aEnd] = $this->ranges[++$aIdx]; }
            } else {
                $curStart = $bStart; $curEnd = $bEnd;
                if ($bHasMore = $bIdx < $bc) { [$bStart, $bEnd] = $other->ranges[++$bIdx]; }
            }

            do {
                $merged = false;
                if ($aHasMore && $aStart <= $curEnd + 1) {
                    if ($aEnd > $curEnd) $curEnd = $aEnd;
                    if ($aHasMore = $aIdx < $ac) { [$aStart, $aEnd] = $this->ranges[++$aIdx]; }
                    $merged = true;
                }
                if ($bHasMore && $bStart <= $curEnd + 1) {
                    if ($bEnd > $curEnd) $curEnd = $bEnd;
                    if ($bHasMore = $bIdx < $bc) { [$bStart, $bEnd] = $other->ranges[++$bIdx]; }
                    $merged = true;
                }
            } while ($merged);

            $ranges[] = [$curStart, $curEnd];
            $count   += $curEnd - $curStart + 1;
        }

        $this->ranges = $ranges;
        $this->count  = $count;
        return $this;
    }

    /**
     * Remove all ranges of another IntRangeSet from this set.
     * Uses a two-pointer sweep for O(m+n) performance.
     */
    public function subtract(IntRangeSet $other): self
    {
        $ranges = [];
        $count  = 0;
        $ac = count($this->ranges) - 1;
        $bc = count($other->ranges) - 1;
        $aHasMore = $ac >= 0;
        $bHasMore = $bc >= 0;
        $aIdx = $bIdx = -1;
        if ($aHasMore) { [$aStart, $aEnd] = $this->ranges[++$aIdx]; }
        if ($bHasMore) { [$bStart, $bEnd] = $other->ranges[++$bIdx]; }

        while ($aHasMore) {
            if (!$bHasMore || $aEnd < $bStart) {
                $ranges[] = [$aStart, $aEnd];
                $count   += $aEnd - $aStart + 1;
                if ($aHasMore = $aIdx < $ac) { [$aStart, $aEnd] = $this->ranges[++$aIdx]; }
            } elseif ($bEnd < $aStart) {
                if ($bHasMore = $bIdx < $bc) { [$bStart, $bEnd] = $other->ranges[++$bIdx]; }
            } else {
                if ($aStart < $bStart) {
                    $ranges[] = [$aStart, $bStart - 1];
                    $count   += $bStart - $aStart;
                }
                if ($aEnd <= $bEnd) {
                    if ($aHasMore = $aIdx < $ac) { [$aStart, $aEnd] = $this->ranges[++$aIdx]; }
                } else {
                    $aStart = $bEnd + 1;
                    if ($bHasMore = $bIdx < $bc) { [$bStart, $bEnd] = $other->ranges[++$bIdx]; }
                }
            }
        }

        $this->ranges = $ranges;
        $this->count  = $count;
        return $this;
    }

    /**
     * Retain only the ranges present in both this set and another.
     * Uses a two-pointer sweep for O(m+n) performance.
     */
    public function intersect(IntRangeSet $other): self
    {
        $ranges = [];
        $count  = 0;
        $ac = count($this->ranges) - 1;
        $bc = count($other->ranges) - 1;
        $aHasMore = $ac >= 0;
        $bHasMore = $bc >= 0;
        $aIdx = $bIdx = -1;
        if ($aHasMore) { [$aStart, $aEnd] = $this->ranges[++$aIdx]; }
        if ($bHasMore) { [$bStart, $bEnd] = $other->ranges[++$bIdx]; }

        while ($aHasMore && $bHasMore) {
            if ($aEnd < $bStart) {
                if ($aHasMore = $aIdx < $ac) { [$aStart, $aEnd] = $this->ranges[++$aIdx]; }
            } elseif ($bEnd < $aStart) {
                if ($bHasMore = $bIdx < $bc) { [$bStart, $bEnd] = $other->ranges[++$bIdx]; }
            } else {
                $iStart = max($aStart, $bStart);
                $iEnd   = min($aEnd, $bEnd);
                $ranges[] = [$iStart, $iEnd];
                $count   += $iEnd - $iStart + 1;

                if ($aEnd < $bEnd) {
                    if ($aHasMore = $aIdx < $ac) { [$aStart, $aEnd] = $this->ranges[++$aIdx]; }
                } elseif ($bEnd < $aEnd) {
                    if ($bHasMore = $bIdx < $bc) { [$bStart, $bEnd] = $other->ranges[++$bIdx]; }
                } else {
                    if ($aHasMore = $aIdx < $ac) { [$aStart, $aEnd] = $this->ranges[++$aIdx]; }
                    if ($bHasMore = $bIdx < $bc) { [$bStart, $bEnd] = $other->ranges[++$bIdx]; }
                }
            }
        }

        $this->ranges = $ranges;
        $this->count  = $count;
        return $this;
    }

    /**
     * Compare two sets in a single pass, returning three disjoint sets:
     *   [0] removed:   ranges present in $a but not $b
     *   [1] unchanged: ranges present in both $a and $b
     *   [2] added:     ranges present in $b but not $a
     *
     * Together the three sets account for every integer in $a or $b exactly once.
     *
     * @return array{0: IntRangeSet, 1: IntRangeSet, 2: IntRangeSet}
     */
    public static function diff(IntRangeSet $a, IntRangeSet $b): array
    {
        $removedRanges   = []; $removedCount   = 0;
        $unchangedRanges = []; $unchangedCount = 0;
        $addedRanges     = []; $addedCount     = 0;

        $ac = count($a->ranges) - 1;
        $bc = count($b->ranges) - 1;
        $aHasMore = $ac >= 0;
        $bHasMore = $bc >= 0;
        $aIdx = $bIdx = -1;
        if ($aHasMore) { [$aStart, $aEnd] = $a->ranges[++$aIdx]; }
        if ($bHasMore) { [$bStart, $bEnd] = $b->ranges[++$bIdx]; }

        while ($aHasMore || $bHasMore) {
            if (!$bHasMore || ($aHasMore && $aEnd < $bStart)) {
                $removedRanges[] = [$aStart, $aEnd];
                $removedCount   += $aEnd - $aStart + 1;
                if ($aHasMore = $aIdx < $ac) { [$aStart, $aEnd] = $a->ranges[++$aIdx]; }
            } elseif (!$aHasMore || $bEnd < $aStart) {
                $addedRanges[] = [$bStart, $bEnd];
                $addedCount   += $bEnd - $bStart + 1;
                if ($bHasMore = $bIdx < $bc) { [$bStart, $bEnd] = $b->ranges[++$bIdx]; }
            } else {
                if ($aStart < $bStart) {
                    $removedRanges[] = [$aStart, $bStart - 1];
                    $removedCount   += $bStart - $aStart;
                } elseif ($bStart < $aStart) {
                    $addedRanges[] = [$bStart, $aStart - 1];
                    $addedCount   += $aStart - $bStart;
                }

                $uStart = max($aStart, $bStart);
                $uEnd   = min($aEnd, $bEnd);
                $unchangedRanges[] = [$uStart, $uEnd];
                $unchangedCount   += $uEnd - $uStart + 1;

                $aConsumed = false;
                $bConsumed = false;
                if ($aEnd < $bEnd)     { $bStart = $aEnd + 1; $aConsumed = true; }
                elseif ($bEnd < $aEnd) { $aStart = $bEnd + 1; $bConsumed = true; }
                else                   { $aConsumed = true; $bConsumed = true;   }

                if ($aConsumed) {
                    if ($aHasMore = $aIdx < $ac) { [$aStart, $aEnd] = $a->ranges[++$aIdx]; }
                }
                if ($bConsumed) {
                    if ($bHasMore = $bIdx < $bc) { [$bStart, $bEnd] = $b->ranges[++$bIdx]; }
                }
            }
        }

        return [
            new self($removedRanges,   $removedCount),
            new self($unchangedRanges, $unchangedCount),
            new self($addedRanges,     $addedCount),
        ];
    }

    /**
     * Iterate over every integer in the set in ascending order.
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->ranges as [$start, $end]) {
            for ($i = $start; $i <= $end; $i++) {
                yield $i;
            }
        }
    }

    /**
     * Parse an IMAP-style UID set string, e.g. "1:5,7,10:15"
     * and return a new IntRangeSet.
     * Only non-negative integers are supported in string format.
     * Input may be unsorted or contain overlapping/adjacent ranges.
     *
     * @throws \InvalidArgumentException on malformed input
     */
    public static function fromString(string $s): self
    {
        $set = new self();
        if ($s === '') return $set;

        $c = 0;
        foreach (explode(',', $s) as $part) {
            if (!preg_match('/^(\d+)(?::(\d+))?$/', $part, $m)) {
                throw new \InvalidArgumentException("Invalid range part: '$part'");
            }
            $start = (int)$m[1];
            $end   = isset($m[2]) ? (int)$m[2] : $start;
            if ($start <= $end) [$rs, $re] = [$start, $end];
            else                [$rs, $re] = [$end, $start];
            if ($c === 0 || $set->ranges[$c - 1][1] < $rs - 1) {
                $set->ranges[] = [$rs, $re];
                $set->count   += $re - $rs + 1;
                $c++;
            } else {
                $set->addRange($rs, $re, $c - 1);
                $c = count($set->ranges);
            }
        }

        return $set;
    }

    /**
     * Check whether a value is present in the set.
     */
    public function contains(int $n): bool
    {
        $i = $this->lowerBound($n);
        return $i < count($this->ranges) && $this->ranges[$i][0] <= $n;
    }

    /**
     * Return the total count of integers in the set.
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Render the set as an IMAP-style UID set string, e.g. "1:5,7,10:15"
     * Single-element ranges are shown as just the number (e.g. "7" not "7:7").
     */
    public function __toString(): string
    {
        return implode(',', array_map(
            fn($r) => $r[0] === $r[1] ? (string)$r[0] : "{$r[0]}:{$r[1]}",
            $this->ranges
        ));
    }
}
