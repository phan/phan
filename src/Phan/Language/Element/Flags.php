<?php declare(strict_types=1);

namespace Phan\Language\Element;

/**
 * Flags contains bit flags that Phan adds to elements
 * and methods for manipulating those bit flags.
 *
 * (manipulated by Element->getPhanFlags(), ElementPhanFlags())
 */
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
    const IS_IGNORED_REFERENCE         = (1 << 16);  // only applies to parameters, does not conflict with other types
    // End of reference types

    // This will be compared against IS_OVERRIDE
    const IS_OVERRIDE_INTENDED         = (1 << 16);

    const IS_PARAM_USING_NULLABLE_SYNTAX = (1 << 17);

    // For dead code detection
    const WAS_PROPERTY_READ = (1 << 18);
    const WAS_PROPERTY_WRITTEN = (1 << 19);

    const IS_DYNAMIC_PROPERTY = (1 << 20);
    // Is this a dynamic global constant?
    const IS_DYNAMIC_CONSTANT = (1 << 20);
    const IS_CONSTRUCTOR_USED_FOR_SIDE_EFFECTS = (1 << 20);
    // A property can be read-only, write-only, or neither, but not both.
    // This is independent of being a magic property.
    // IS_READ_ONLY can also be set on classes as @phan-immutable
    const IS_READ_ONLY = (1 << 21);
    const IS_WRITE_ONLY = (1 << 22);
    const HAS_STATIC_UNION_TYPE = (1 << 23);
    const HAS_TEMPLATE_TYPE = (1 << 24);

    const IS_OVERRIDDEN_BY_ANOTHER = (1 << 25);
    // Currently applies only to some variables (e.g. static variables)
    const IS_CONSTANT_DEFINITION = (1 << 26);

    // Flag to be set on fake __construct methods (e.g. for classes/interfaces without having them defined explicitly)
    // Currently for strict visibility checking, because fake constructors have public visibility by default, and Phan
    // fails thinking that child classes are violating the visibility if they have a private or protected __construct
    const IS_FAKE_CONSTRUCTOR = (1 << 27);
    const IS_EXTERNAL_MUTATION_FREE = (1 << 28);
    const IS_SIDE_EFFECT_FREE = self::IS_READ_ONLY | self::IS_EXTERNAL_MUTATION_FREE;
    /**
     * @suppress PhanUnreferencedPublicClassConstant
     * @deprecated alias of side effect free, will be removed in the future.
     */
    const IS_PURE = self::IS_SIDE_EFFECT_FREE;

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
        return $value
            ? ($bit_vector | $flag)
            : ($bit_vector & (~$flag));
    }

    /**
     * @param int $bit_vector
     * The bit vector we'd like to get the state for
     *
     * @param int $flag
     * The flag we'd like to get the state for
     *
     * @return bool
     * True if all bits in the flag are enabled in the bit
     * vector, else false.
     */
    public static function bitVectorHasState(
        int $bit_vector,
        int $flag
    ) : bool {
        return (($bit_vector & $flag) === $flag);
    }
}
