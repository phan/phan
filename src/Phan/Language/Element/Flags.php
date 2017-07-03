<?php declare(strict_types=1);
namespace Phan\Language\Element;

class Flags
{
    const IS_DEPRECATED                = (1 << 1);
    const IS_PHP_INTERNAL              = (1 << 2);

    const IS_PARENT_CONSTRUCTOR_CALLED = (1 << 3);

    const IS_RETURN_TYPE_UNDEFINED     = (1 << 4);
    const HAS_RETURN                   = (1 << 5);
    const IS_OVERRIDE                  = (1 << 6);
    const HAS_YIELD                    = (1 << 7);

    const CLASS_HAS_DYNAMIC_PROPERTIES = (1 << 8);
    const IS_CLONE_OF_VARIADIC         = (1 << 9);
    const CLASS_FORBID_UNDECLARED_MAGIC_PROPERTIES = (1 << 10);
    const CLASS_FORBID_UNDECLARED_MAGIC_METHODS    = (1 << 11);

    const IS_NS_INTERNAL               = (1 << 12);
    const IS_FROM_PHPDOC               = (1 << 13);

    // These can be combined in 3 ways, see Parameter->getReferenceType()
    const IS_READ_REFERENCE            = (1 << 14);
    const IS_WRITE_REFERENCE           = (1 << 15);
    // End of reference types

    // This will be compared against IS_OVERRIDE
    const IS_OVERRIDE_INTENDED         = (1 << 16);

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
