<?php declare(strict_types=1);
namespace Phan\Library;

use Phan\Language\Type;

/**
 * An alternative to Phan\Library\Set.
 * This is useful when sets are small (0 or 1 elements), and frequently cloned.
 * Because of copy on write, cloning the generated arrays is done automatically and efficiently.
 */
final class ArraySet
{
    // This is a collection of utilities. It cannot be instantiated.
    private function __construct()
    {
    }

    /**
     * @param Type $object
     * @return Type[]
     * @suppress PhanUnreferencedPublicMethod callers inlined this for performanced.
     */
    public static function singleton($object) : array
    {
        return [
            \spl_object_id($object) => $object,
        ];
    }

    /**
     * @param Type[]|\Iterator|null $object_list
     * @return Type[]
     */
    public static function from_list($object_list = null) : array
    {
        $result = [];
        foreach ($object_list ?? [] as $object) {
            \assert(
                \is_object($object),
                'ArraySet should contain only objects'
            );
            $result[\spl_object_id($object)] = $object;
        }
        return $result;
    }

    /**
     * @param Type[] $object_set
     * @param \Closure $cb - Closure mapping Type to boolean.
     * @return bool
     */
    public static function exists(array $object_set, \Closure $cb)
    {
        foreach ($object_set as $e) {
            if ($cb($e)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Type[] $array
     * @param \Closure $cb
     * @return Type|false
     */
    public static function find(array $array, \Closure $cb)
    {
        foreach ($array as $e) {
            if ($cb($e)) {
                return $e;
            }
        }
        return false;
    }

    /**
     * @param Type[] $object_set - Map from object id to Type
     * @param Type[] $candidate_type_list - List of Type
     */
    public static function containsAny(array $object_set, array $candidate_type_list) : bool
    {
        foreach ($candidate_type_list as $type) {
            if (isset($object_set[\spl_object_id($type)])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Just use array_filter - array_filter preserves keys.
     * @param Type[] $object_set
     * @param \Closure $cb
     * @deprecated
     * @suppress PhanUnreferencedPublicMethod callers inlined this for performanced.
     */
    public static function filter(array $object_set, \Closure $cb) : array
    {
        return \array_filter($object_set, $cb);
    }

    /**
     * @param Type[] $object_set
     * @param \Closure $cb - Maps Type -> Type
     * @return Type[] $object_set
     */
    public static function map(array $object_set, \Closure $cb) : array
    {
        $result = [];
        foreach ($object_set as $object) {
            $new_object = $cb($object);
            \assert(
                \is_object($new_object),
                'ArraySet should contain only objects'
            );
            $result[\spl_object_id($new_object)] = $new_object;
        }
        return $result;
    }

    /**
     * @param Type[] $object_set
     * @param Type $object - object to search for
     * @return bool
     */
    public static function contains(array $object_set, $object) : bool
    {
        return isset($object_set[\spl_object_id($object)]);
    }

    /**
     * @param Type[][] $sets
     * @return Type[] - A set of Type made for efficient lookup
     */
    public static function unionAll(array $sets)
    {
        if (\count($sets) === 1) {
            return \reset($sets);
        }
        $result = [];
        foreach ($sets as $set) {
            if (\count($result) === 0) {
                $result = $set;
            } else {
                $result += $set;
            }
        }
        return $result;
    }

    /**
     * Helper function for assertions.
     * @param Type[] $object_set
     * @return bool - Whether or not this is an object set.
     * @suppress PhanUnreferencedPublicMethod this is only used as a sanity check
     */
    public static function is_array_set(array $object_set)
    {
        foreach ($object_set as $key => $object) {
            if (!\is_object($object) || \spl_object_id($object) !== $key) {
                return false;
            }
        }
        return true;
    }
}
