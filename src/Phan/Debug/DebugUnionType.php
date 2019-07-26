<?php declare(strict_types=1);

namespace Phan\Debug;

use Phan\Language\Type;
use Phan\Language\UnionType;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * Utility for debugging assignments to a given union type.
 * This can be used when creating a union type to figure out the causes of unexpected error messages.
 *
 * Not used as part of normal phan operations.
 */
class DebugUnionType extends UnionType
{

    /**
     * Add a type name to the list of types
     * @override
     */
    public function withType(Type $type) : UnionType
    {
        \printf("%s: Adding type %s to %s", \spl_object_hash($this), (string)$type, (string)$this);
        \debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        return parent::withType($type);
    }

    /**
     * Add the given types to this type
     */
    public function withUnionType(UnionType $union_type) : UnionType
    {
        \printf("%s: Adding union type %s to %s", \spl_object_hash($this), (string)$union_type, (string)$this);
        \debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        return parent::withUnionType($union_type);
    }
}
