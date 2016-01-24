<?php declare(strict_types=1);
namespace Phan\Language\Element;

class Flags
{
    // Typed Structural Element
    const IS_DEPRECATED                = (1<<32);
    const IS_INTERNAL                  = (1<<33);

    // Classes
    const IS_PARENT_CONSTRUCTOR_CALLED = (1<<34);

    // Methods
    const IS_RETURN_TYPE_UNDEFINED     = (1<<35);
    const HAS_RETURN                   = (1<<36);
    const IS_OVERRIDE                  = (1<<37);

    /**
     * Either enable or disable the given flag on
     * the given bit vector.
     *
     * @param int $bit_vector
     * The bit vector we're operating on
     *
     * @param int $flag
     * The flag we're setting on the bit vector such
     * as Flags::IS_DEPRECATED.
     *
     * @param bool $value
     * True to or the flag in, false to & the bit vector
     * with the flags negation
     *
     * @return int
     * A new bit vector with the given flag set or unset
     */
    public static function bitVectorWithState(
        int $bit_vector,
        int $flag,
        bool $value
    ) : int {
        $bit_vector = $value
            ? ($bit_vector | $flag)
            : ($bit_vector & (~$flag));

        return $bit_vector;
    }

    /**
     * @param int $bit_vector
     * The bit vector we'd like to get the state for
     *
     * @param int $flag
     * The flag we'd like to get the state for
     *
     * @return bool
     * True if all bits in the flag are eanbled in the bit
     * vector, else false.
     */
    public static function bitVectorHasState(
        int $bit_vector,
        int $flag
    ) : bool {
        return (($bit_vector & $flag) == $flag);
    }

}
