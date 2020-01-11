<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\Language\UnionType;

/**
 * Represents an assertion on a parameter type.
 *
 * @internal
 * @phan-immutable
 */
class Assertion
{
    public const IS_OF_TYPE = 1;
    public const IS_NOT_OF_TYPE = 2;
    public const IS_TRUE = 3;
    public const IS_FALSE = 4;

    /** @var UnionType the type that is used in this assertion */
    public $union_type;
    /** @var string the parameter name that the assertion affects */
    public $param_name;
    /** @var 1|2|3|4 an enum self::IS_* */
    public $assertion_type;

    /**
     * @param 1|2|3|4 $assertion_type
     */
    public function __construct(UnionType $union_type, string $param_name, int $assertion_type)
    {
        $this->union_type = $union_type;
        $this->param_name = $param_name;
        $this->assertion_type = $assertion_type;
    }
}
