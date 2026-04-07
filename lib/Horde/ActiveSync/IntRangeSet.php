<?php

declare(strict_types=1);
error_reporting(E_ALL);

/**
 * IntRangeSet v0.5
 *
 * Maintains an ordered, compact set of integer ranges.
 * Ranges are stored as a flat array [start1, end1, start2, end2, ...]
 * in ascending order.
 *
 * String representation uses IMAP-style syntax: "1:5,7,10:15"
 * Note: fromString() only supports non-negative integers.
 *
 * @author: Dmitry Petrov <dpetrov67@gmail.com>
 */
class IntRangeSet implements \IteratorAggregate
{
    /**
     * Flat array of alternating start/end values: [start1, end1, start2, end2, ...]
     * @var int[]
     */
    private array $ranges;

    /** @var int Cached total count of integers in the set */
    private int $count;

    public function __construct(array $ranges = [], int $count = 0)
    {
        $this->ranges = $ranges;
        $this->count  = $count;
    }

    /**
     * Return the flat index of the first range whose end >= $n,
     * or count($this->ranges) if no such range exists.
     * Search starts at flat index $lo.
     */
    private function lowerBound(int $n, int $lo = 0): int
    {
        $hi = count($this->ranges) - 2;

        if ($hi < $lo || $this->ranges[$hi + 1] < $n) return $hi + 2;
        if ($this->ranges[$lo + 1] >= $n) return $lo;

        do {
            // Round down to even index
            $mid = (($lo + $hi) >> 1) & ~1;
            $midEnd = $this->ranges[$mid + 1];
            if ($midEnd < $n)       $lo = $mid + 2;
            elseif ($midEnd === $n) return $mid;
            else                    $hi = $mid - 2;
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

        $c     = count($this->ranges);
        $first = $this->lowerBound($start, $lo);
        $last  = $start === $end ? $first : $this->lowerBound($end, $first);

        // Absorb right neighbor if adjacent or overlapping
        if ($last < $c) {
            $lastStart = $this->ranges[$last];
            if ($lastStart <= $end + 1) {
                $end = $this->ranges[$last + 1];
                $last += 2;
            }
        }

        // Check left neighbor for adjacency ($first - 1 is the end value of previous range)
        if ($first > 0 && $this->ranges[$first - 1] + 1 >= $start) {
            $first -= 2;
        }

        // Absorb left neighbor's start if it extends further left
        if ($first < $c && $this->ranges[$first] < $start) {
            $start = $this->ranges[$first];
        }

        // Compute count delta: subtract absorbed ranges, add new range
        $delta = $end - $start + 1;
        for ($k = $first; $k < $last; $k += 2) $delta -= $this->ranges[$k + 1] - $this->ranges[$k] + 1;
        $this->count += $delta;

        if ($last - $first === 2) {
            $this->ranges[$first]     = $start;
            $this->ranges[$first + 1] = $end;
        } else {
            array_splice($this->ranges, $first, $last - $first, [$start, $end]);
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

        $c     = count($this->ranges);
        $first = $this->lowerBound($start);

        if ($first >= $c || $this->ranges[$first] > $end) {
            return $this;
        }

        $last = $start === $end ? $first : $this->lowerBound($end, $first);

        $replacement = [];

        // If the first affected range starts before $start, preserve its left portion
        $firstStart = $this->ranges[$first];
        if ($firstStart < $start) {
            $replacement[] = $firstStart;
            $replacement[] = $start - 1;
        }

        // If the last affected range ends after $end, preserve its right portion
        if ($last < $c) {
            $lastEnd = $this->ranges[$last + 1];
            if ($lastEnd > $end) {
                $replacement[] = $end + 1;
                $replacement[] = $lastEnd;
            }
            $last += 2;
        }

        // Compute delta: sum of absorbed ranges minus preserved portions
        $delta = 0;
        for ($k = $first; $k < $last; $k += 2) $delta += $this->ranges[$k + 1] - $this->ranges[$k] + 1;
        $rc = count($replacement);
        for ($k = 0; $k < $rc; $k += 2) $delta -= $replacement[$k + 1] - $replacement[$k] + 1;
        $this->count -= $delta;

        if ($last - $first === 2 && $rc === 2) {
            $this->ranges[$first]     = $replacement[0];
            $this->ranges[$first + 1] = $replacement[1];
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
        $ac = count($this->ranges) - 2;
        $bc = count($other->ranges) - 2;
        $aHasMore = $ac >= 0;
        $bHasMore = $bc >= 0;
        $aIdx = $bIdx = -2;
        if ($aHasMore) { $aStart = $this->ranges[$aIdx  = 0]; $aEnd = $this->ranges[1]; }
        if ($bHasMore) { $bStart = $other->ranges[$bIdx = 0]; $bEnd = $other->ranges[1]; }

        while ($aHasMore || $bHasMore) {
            if (!$bHasMore || ($aHasMore && $aStart <= $bStart)) {
                $curStart = $aStart; $curEnd = $aEnd;
                if ($aHasMore = $aIdx < $ac) { $aStart = $this->ranges[$aIdx += 2]; $aEnd = $this->ranges[$aIdx + 1]; }
            } else {
                $curStart = $bStart; $curEnd = $bEnd;
                if ($bHasMore = $bIdx < $bc) { $bStart = $other->ranges[$bIdx += 2]; $bEnd = $other->ranges[$bIdx + 1]; }
            }

            do {
                $merged = false;
                if ($aHasMore && $aStart <= $curEnd + 1) {
                    if ($aEnd > $curEnd) $curEnd = $aEnd;
                    if ($aHasMore = $aIdx < $ac) { $aStart = $this->ranges[$aIdx += 2]; $aEnd = $this->ranges[$aIdx + 1]; }
                    $merged = true;
                }
                if ($bHasMore && $bStart <= $curEnd + 1) {
                    if ($bEnd > $curEnd) $curEnd = $bEnd;
                    if ($bHasMore = $bIdx < $bc) { $bStart = $other->ranges[$bIdx += 2]; $bEnd = $other->ranges[$bIdx + 1]; }
                    $merged = true;
                }
            } while ($merged);

            $ranges[] = $curStart;
            $ranges[] = $curEnd;
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
        $ac = count($this->ranges) - 2;
        $bc = count($other->ranges) - 2;
        $aHasMore = $ac >= 0;
        $bHasMore = $bc >= 0;
        $aIdx = $bIdx = -2;
        if ($aHasMore) { $aStart = $this->ranges[$aIdx  = 0]; $aEnd = $this->ranges[1]; }
        if ($bHasMore) { $bStart = $other->ranges[$bIdx = 0]; $bEnd = $other->ranges[1]; }

        while ($aHasMore) {
            if (!$bHasMore || $aEnd < $bStart) {
                $ranges[] = $aStart; $ranges[] = $aEnd;
                $count   += $aEnd - $aStart + 1;
                if ($aHasMore = $aIdx < $ac) { $aStart = $this->ranges[$aIdx += 2]; $aEnd = $this->ranges[$aIdx + 1]; }
            } elseif ($bEnd < $aStart) {
                if ($bHasMore = $bIdx < $bc) { $bStart = $other->ranges[$bIdx += 2]; $bEnd = $other->ranges[$bIdx + 1]; }
            } else {
                if ($aStart < $bStart) {
                    $ranges[] = $aStart; $ranges[] = $bStart - 1;
                    $count   += $bStart - $aStart;
                }
                if ($aEnd <= $bEnd) {
                    if ($aHasMore = $aIdx < $ac) { $aStart = $this->ranges[$aIdx += 2]; $aEnd = $this->ranges[$aIdx + 1]; }
                } else {
                    $aStart = $bEnd + 1;
                    if ($bHasMore = $bIdx < $bc) { $bStart = $other->ranges[$bIdx += 2]; $bEnd = $other->ranges[$bIdx + 1]; }
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
        $ac = count($this->ranges) - 2;
        $bc = count($other->ranges) - 2;
        $aHasMore = $ac >= 0;
        $bHasMore = $bc >= 0;
        $aIdx = $bIdx = -2;
        if ($aHasMore) { $aStart = $this->ranges[$aIdx  = 0]; $aEnd = $this->ranges[1]; }
        if ($bHasMore) { $bStart = $other->ranges[$bIdx = 0]; $bEnd = $other->ranges[1]; }

        while ($aHasMore && $bHasMore) {
            if ($aEnd < $bStart) {
                if ($aHasMore = $aIdx < $ac) { $aStart = $this->ranges[$aIdx += 2]; $aEnd = $this->ranges[$aIdx + 1]; }
            } elseif ($bEnd < $aStart) {
                if ($bHasMore = $bIdx < $bc) { $bStart = $other->ranges[$bIdx += 2]; $bEnd = $other->ranges[$bIdx + 1]; }
            } else {
                $iStart = $aStart > $bStart ? $aStart : $bStart;
                $iEnd   = $aEnd   < $bEnd   ? $aEnd   : $bEnd;
                $ranges[] = $iStart; $ranges[] = $iEnd;
                $count   += $iEnd - $iStart + 1;

                if ($aEnd < $bEnd) {
                    if ($aHasMore = $aIdx < $ac) { $aStart = $this->ranges[$aIdx += 2]; $aEnd = $this->ranges[$aIdx + 1]; }
                } elseif ($bEnd < $aEnd) {
                    if ($bHasMore = $bIdx < $bc) { $bStart = $other->ranges[$bIdx += 2]; $bEnd = $other->ranges[$bIdx + 1]; }
                } else {
                    if ($aHasMore = $aIdx < $ac) { $aStart = $this->ranges[$aIdx += 2]; $aEnd = $this->ranges[$aIdx + 1]; }
                    if ($bHasMore = $bIdx < $bc) { $bStart = $other->ranges[$bIdx += 2]; $bEnd = $other->ranges[$bIdx + 1]; }
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

        $ac = count($a->ranges) - 2;
        $bc = count($b->ranges) - 2;
        $aHasMore = $ac >= 0;
        $bHasMore = $bc >= 0;
        $aIdx = $bIdx = -2;
        if ($aHasMore) { $aStart = $a->ranges[$aIdx  = 0]; $aEnd = $a->ranges[1]; }
        if ($bHasMore) { $bStart = $b->ranges[$bIdx  = 0]; $bEnd = $b->ranges[1]; }

        while ($aHasMore || $bHasMore) {
            if (!$bHasMore || ($aHasMore && $aEnd < $bStart)) {
                $removedRanges[] = $aStart; $removedRanges[] = $aEnd;
                $removedCount   += $aEnd - $aStart + 1;
                if ($aHasMore = $aIdx < $ac) { $aStart = $a->ranges[$aIdx += 2]; $aEnd = $a->ranges[$aIdx + 1]; }
            } elseif (!$aHasMore || $bEnd < $aStart) {
                $addedRanges[] = $bStart; $addedRanges[] = $bEnd;
                $addedCount   += $bEnd - $bStart + 1;
                if ($bHasMore = $bIdx < $bc) { $bStart = $b->ranges[$bIdx += 2]; $bEnd = $b->ranges[$bIdx + 1]; }
            } else {
                if ($aStart < $bStart) {
                    $removedRanges[] = $aStart; $removedRanges[] = $bStart - 1;
                    $removedCount   += $bStart - $aStart;
                    $uStart = $bStart;
                } elseif ($bStart < $aStart) {
                    $addedRanges[] = $bStart; $addedRanges[] = $aStart - 1;
                    $addedCount   += $aStart - $bStart;
                    $uStart = $aStart;
                } else {
                    $uStart = $aStart;
                }

                $uEnd = $aEnd < $bEnd ? $aEnd : $bEnd;
                $unchangedRanges[] = $uStart; $unchangedRanges[] = $uEnd;
                $unchangedCount   += $uEnd - $uStart + 1;

                $aConsumed = false;
                $bConsumed = false;
                if ($aEnd < $bEnd)     { $bStart = $aEnd + 1; $aConsumed = true; }
                elseif ($bEnd < $aEnd) { $aStart = $bEnd + 1; $bConsumed = true; }
                else                   { $aConsumed = true; $bConsumed = true;   }

                if ($aConsumed) {
                    if ($aHasMore = $aIdx < $ac) { $aStart = $a->ranges[$aIdx += 2]; $aEnd = $a->ranges[$aIdx + 1]; }
                }
                if ($bConsumed) {
                    if ($bHasMore = $bIdx < $bc) { $bStart = $b->ranges[$bIdx += 2]; $bEnd = $b->ranges[$bIdx + 1]; }
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
        for ($i = 0, $c = count($this->ranges); $i < $c; $i += 2) {
            for ($n = $this->ranges[$i]; $n <= $this->ranges[$i + 1]; $n++) {
                yield $n;
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
            if ($start > $end) [$start, $end] = [$end, $start];
            if ($c === 0 || $set->ranges[$c - 1] < $start - 1) {
                $set->ranges[] = $start;
                $set->ranges[] = $end;
                $set->count   += $end - $start + 1;
                $c += 2;
            } else {
                $set->addRange($start, $end, $c - 2);
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
        return $i < count($this->ranges) && $this->ranges[$i] <= $n;
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
        $parts = [];
        for ($i = 0, $c = count($this->ranges); $i < $c; $i += 2) {
            $parts[] = $this->ranges[$i] === $this->ranges[$i + 1]
                ? (string)$this->ranges[$i]
                : "{$this->ranges[$i]}:{$this->ranges[$i + 1]}";
        }
        return implode(',', $parts);
    }
}
