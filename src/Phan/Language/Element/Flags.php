<?php

declare(strict_types=1);

namespace Phan\Language\Element;

/**
 * Flags contains bit flags that Phan adds to elements
 * and methods for manipulating those bit flags.
 *
 * (manipulated by Element->getPhanFlags(), ElementPhanFlags())
 */
class Flags
{
    public const IS_DEPRECATED                = (1 << 1);
    public const IS_PHP_INTERNAL              = (1 << 2);

    public const IS_PARENT_CONSTRUCTOR_CALLED = (1 << 3);

    public const IS_RETURN_TYPE_UNDEFINED     = (1 << 4);
    public const HAS_RETURN                   = (1 << 5);
    public const IS_OVERRIDE                  = (1 << 6);
    public const HAS_YIELD                    = (1 << 7);

    public const HAS_STATIC_VARIABLE          = (1 << 8);  // used on function-likes
    public const CLASS_HAS_DYNAMIC_PROPERTIES = (1 << 8);  // used on classes
    public const IS_CLONE_OF_VARIADIC         = (1 << 9);
    public const CLASS_FORBID_UNDECLARED_MAGIC_PROPERTIES = (1 << 10);
    public const CLASS_FORBID_UNDECLARED_MAGIC_METHODS    = (1 << 11);

    public const IS_NS_INTERNAL               = (1 << 12);
    public const IS_FROM_PHPDOC               = (1 << 13);

    // These can be combined in 3 ways, see Parameter->getReferenceType()
    public const IS_READ_REFERENCE            = (1 << 14);
    public const IS_WRITE_REFERENCE           = (1 << 15);
    public const IS_IGNORED_REFERENCE         = (1 << 16);  // only applies to parameters, does not conflict with other types
    // End of reference types

    // This will be compared against IS_OVERRIDE
    public const IS_OVERRIDE_INTENDED         = (1 << 16);

    public const IS_PARAM_USING_NULLABLE_SYNTAX = (1 << 17);

    // For dead code detection
    public const WAS_PROPERTY_READ = (1 << 18);
    public const WAS_PROPERTY_WRITTEN = (1 << 19);
    // For methods
    public const NO_NAMED_ARGUMENTS = (1 << 19);

    public const IS_DYNAMIC_PROPERTY = (1 << 20);
    // Is this a dynamic global constant?
    public const IS_DYNAMIC_CONSTANT = (1 << 20);
    public const IS_CONSTRUCTOR_USED_FOR_SIDE_EFFECTS = (1 << 20);
    // A property can be read-only, write-only, or neither, but not both.
    // This is independent of being a magic property.
    // IS_READ_ONLY can also be set on classes as @phan-immutable
    public const IS_READ_ONLY = (1 << 21);
    public const IS_WRITE_ONLY = (1 << 22);
    public const HAS_STATIC_UNION_TYPE = (1 << 23);
    public const HAS_TEMPLATE_TYPE = (1 << 24);

    public const IS_OVERRIDDEN_BY_ANOTHER = (1 << 25);
    // Currently applies only to some variables (e.g. static variables)
    public const IS_CONSTANT_DEFINITION = (1 << 26);
    // only set on methods
    public const HAS_TENTATIVE_RETURN_TYPE = (1 << 26);
    // Also used for `@phan-hardcode-return-type`
    public const HARDCODED_RETURN_TYPE = (1 << 26);

    // Flag to be set on fake __construct methods (e.g. for classes/interfaces without having them defined explicitly)
    // Currently for strict visibility checking, because fake constructors have public visibility by default, and Phan
    // fails thinking that child classes are violating the visibility if they have a private or protected __construct
    // only set on methods.
    public const IS_FAKE_CONSTRUCTOR = (1 << 27);
    // only set on properties.
    public const IS_ENUM_PROPERTY = (1 << 27);

    public const IS_EXTERNAL_MUTATION_FREE = (1 << 28);
    public const IS_SIDE_EFFECT_FREE = self::IS_READ_ONLY | self::IS_EXTERNAL_MUTATION_FREE;
    // @abstract tag on class constants or other elements
    public const IS_PHPDOC_ABSTRACT = (1 << 29);

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
    ): int {
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
    ): bool {
        return (($bit_vector & $flag) === $flag);
    }
}
