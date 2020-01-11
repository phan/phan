<?php

declare(strict_types=1);

namespace Phan\Library\Hasher;

use Phan\Library\Hasher;

/**
 * Hasher implementation mapping keys to sequential groups (first key to 0, second key to 1, looping back to 0)
 * getGroup() is called exactly once on each string to be hashed.
 * See https://en.wikipedia.org/wiki/Consistent_hashing
 */
class Consistent implements Hasher
{
    /** A larger number means a more balanced distribution. */
    private const VIRTUAL_COPY_COUNT = 16;
    /** i.e. (1 << 30) */
    private const MAX = 0x40000000;
    /** @var list<int> - Sorted list of hash values, for binary search. */
    protected $hash_ring_ids;
    /** @var list<int> - Groups corresponding to hash values in hash_ring_ids */
    protected $hash_ring_groups;

    public function __construct(int $group_count)
    {
        $map = self::generateMap($group_count);
        $hash_ring_ids = [];
        $hash_ring_groups = [];
        foreach ($map as $key => $group) {
            $hash_ring_ids[] = $key;
            $hash_ring_groups[] = $group;
        }
        // ... and make the map wrap around.
        $hash_ring_ids[] = self::MAX - 1;
        $hash_ring_groups[] = \reset($map) ?: 0;

        $this->hash_ring_ids = $hash_ring_ids;
        $this->hash_ring_groups = $hash_ring_groups;
    }

    /**
     * @return associative-array<int,int> maps points in the field to the corresponding group (for consistent hashing)
     */
    private static function generateMap(int $group_count): array
    {
        $map = [];
        for ($group = 0; $group < $group_count; $group++) {
            foreach (self::getHashesForGroup($group) as $hash) {
                $map[$hash] = $group;
            }
        }
        \ksort($map);
        return $map;
    }
    /**
     * Do a binary search in the consistent hashing ring to find the group.
     * @return int - an integer between 0 and $this->group_count - 1, inclusive
     */
    public function getGroup(string $key): int
    {
        $search_hash = self::generateKeyHash($key);
        $begin = 0;
        $end = \count($this->hash_ring_ids) - 1;
        while ($begin <= $end) {
            $pos = $begin + (($end - $begin) >> 1);
            $cur_val = $this->hash_ring_ids[$pos];
            if ($search_hash > $cur_val) {
                $begin = $pos + 1;
            } else {
                $end = $pos - 1;
            }
        }
        // Postcondition: $this->hash_ring_ids[$begin] >= $search_hash, and $this->hash_ring_ids[$begin - 1] does not exist or is less than $search_hash.

        // Fetch the group corresponding to that hash in the hash ring.
        return $this->hash_ring_groups[$begin];
    }

    /**
     * No-op reset
     */
    public function reset(): void
    {
    }

    /**
     * @return list<int> A list of VIRTUAL_COPY_COUNT hashes for group $i in the consistent hash ring.
     */
    public static function getHashesForGroup(int $group): array
    {
        $hashes = [];
        for ($i = 0; $i < self::VIRTUAL_COPY_COUNT; $i++) {
            $hashes[$i] = self::generateKeyHash("${i}@$group");
        }
        return $hashes;
    }

    /**
     * Returns a 30-bit signed integer (i.e. in the range [0, self::MAX-1])
     * Designed to work on 32-bit php installations as well.
     */
    public static function generateKeyHash(string $material): int
    {
        $bits = \md5($material);
        $result = ((\intval($bits[0], 16) & 3) << 28) ^ \intval(\substr($bits, 1, 7), 16);
        return $result;
    }
}
