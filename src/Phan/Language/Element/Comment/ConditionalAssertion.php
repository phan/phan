<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\Language\UnionType;

/**
 * Represents an assertion on the type of a parameter that depends on what the function returns:
 * `(at)phan-assert-if-true T $param`, `(at)phan-assert-if-false T $param`, and the phpstan-/psalm- forms.
 *
 * @phan-immutable
 */
class ConditionalAssertion
{
    /** @var UnionType the type that is used in this assertion */
    public $union_type;
    /** @var string the parameter name that the assertion affects */
    public $param_name;
    /** @var bool true for -assert-if-true, false for -assert-if-false */
    public $if_true;
    /** @var bool whether the parameter is asserted not to be of the type (`!T`) */
    public $is_negated;
    /**
     * @var bool whether the opposite is assumed when the function returns the other value.
     * (false for `=T`, and for the psalm- form)
     */
    public $applies_to_other_branch;

    public function __construct(UnionType $union_type, string $param_name, bool $if_true, bool $is_negated, bool $applies_to_other_branch)
    {
        $this->union_type = $union_type;
        $this->param_name = $param_name;
        $this->if_true = $if_true;
        $this->is_negated = $is_negated;
        $this->applies_to_other_branch = $applies_to_other_branch;
    }
}
