<?php declare(strict_types=1);
namespace Phan\Library\Hasher;

use Phan\Library\Hasher;

/**
 * Hasher implementation mapping keys to sequential groups (first key to 0, second key to 1, looping back to 0)
 * getGroup() is called exactly once on each string to be hashed.
 * See https://en.wikipedia.org/wiki/Consistent_hashing
 */
class Consistent implements Hasher {
    const VIRTUAL_COPY_COUNT = 16;  // Larger number means a more balanced distribution.
    const MAX = 0x40000000;  // i.e. (1 << 30)
    /** @var int */
    protected $_groupCount;
    /** @var int[] - Sorted list of hash values, for binary search. */
    protected $_hashRingIds;
    /** @var int[] - Groups corresponding to hash values in _hashRingIds */
    protected $_hashRingGroups;

    public function __construct(int $groupCount) {
        $this->_groupCount = $groupCount;

        $map = [];
        for ($group = 0; $group < $groupCount; $group++) {
            foreach (self::get_hashes_for_group($group) as $hash) {
                $map[$hash] = $group;
            }
        }
        $hashRingIds = [];
        $hashRingGroups = [];
        ksort($map);
        foreach ($map as $key => $group) {
            $hashRingIds[] = $key;
            $hashRingGroups[] = $group;
        }
        // ... and make the map wrap around.
        $hashRingIds[] = self::MAX - 1;
        $hashRingGroups[] = reset($map);

        $this->_hashRingIds = $hashRingIds;
        $this->_hashRingGroups = $hashRingGroups;
    }

    /**
     * Do a binary search in the consistent hashing ring to find the group.
     * @return int - an integer between 0 and $this->_groupCount - 1, inclusive
     */
    public function getGroup(string $key) : int {
        $searchHash = self::generate_key_hash($key);
        $begin = 0;
        $end = count($this->_hashRingIds) - 1;
        while ($begin <= $end) {
            $pos = $begin + (($end - $begin) >> 1);
            $curVal = $this->_hashRingIds[$pos];
            if ($searchHash > $curVal) {
                $begin = $pos + 1;
            } else {
                $end = $pos - 1;
            }
        }
        // Postcondition: $this->_hashRingIds[$begin] >= $searchHash, and $this->_hashRingIds[$begin - 1] does not exist or is less than $searchHash.

        // Fetch the group corresponding to that hash in the hash ring.
        return $this->_hashRingGroups[$begin];
    }

    /**
     * No-op reset
     * @return void
     */
    public function reset() { }

    /**
     * @return int[]
     */
    public static function get_hashes_for_group(int $group) : array {
        $hashes = [];
        for ($i = 0; $i < self::VIRTUAL_COPY_COUNT; $i++) {
            $hashes[$i] = self::generate_key_hash("${i}@$group");
        }
        return $hashes;
    }

    /**
     * Returns a 30-bit signed integer (i.e. in the range [0, self::MAX-1])
     * Designed to work on 32-bit php installations as well.
     */
    public static function generate_key_hash(string $material) : int {
        $bits = md5($material);
        $result = ((intval($bits[0], 16) & 3) << 28) ^ intval(substr($bits, 1, 7), 16);
        return $result;
    }
}
