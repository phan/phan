<?php declare(strict_types=1);

namespace Phan\Debug;

use Phan\Language\Type;
use Phan\Language\UnionType;

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
     *
     * @return void
     * @override
     */
    public function addType(Type $type)
    {
        \printf("%s: Adding type %s to %s", \spl_object_hash($this), (string)$type, (string)$this);
        \debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        parent::addType($type);
    }

    /**
     * Add the given types to this type
     *
     * @return void
     */
    public function addUnionType(UnionType $union_type)
    {
        \printf("%s: Adding union type %s to %s", \spl_object_hash($this), (string)$union_type, (string)$this);
        \debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        parent::addUnionType($union_type);
    }
}
