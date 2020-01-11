<?php

declare(strict_types=1);

namespace Phan\Analysis\ConditionVisitor;

use ast\Node;
use Phan\Analysis\ConditionVisitorInterface;
use Phan\Analysis\ConditionVisitorUtil;
use Phan\Language\Context;
use Phan\Language\UnionType;

/**
 * An expression with the side effect that the given node does not have type T
 */
class NotHasTypeCondition implements BinaryCondition
{
    /** @var UnionType the type that this is asserting an argument does not have */
    private $type;

    public function __construct(UnionType $type)
    {
        $this->type = $type;
    }

    /**
     * Assert that this condition applies to the variable $var (i.e. $var does not have type $union_type)
     *
     * @param Node $var
     * @param Node|int|string|float $unused_expr
     * @override
     */
    public function analyzeVar(ConditionVisitorInterface $visitor, Node $var, $unused_expr): Context
    {
        // Get the variable we're operating on
        $context = $visitor->getContext();
        try {
            $variable = $visitor->getVariableFromScope($var, $context);
        } catch (\Exception $_) {
            return $context;
        }
        if (\is_null($variable)) {
            return $context;
        }

        // Make a copy of the variable
        $variable = clone($variable);
        $code_base = $visitor->getCodeBase();
        $result_type = ConditionVisitorUtil::excludeMatchingTypes($code_base, $variable->getUnionType(), $this->type);

        $variable->setUnionType($result_type);

        // Overwrite the variable with its new type in this
        // scope without overwriting other scopes
        return $context->withScopeVariable(
            $variable
        );
    }

    /**
     * Assert that this condition applies to the variable $object. Unimplemented.
     *
     * @param Node|int|string|float $unused_object
     * @param Node|int|string|float $unused_expr
     */
    public function analyzeClassCheck(ConditionVisitorInterface $visitor, $unused_object, $unused_expr): Context
    {
        // Unimplemented, Not likely to be commonly used.
        return $visitor->getContext();
    }

    public function analyzeCall(ConditionVisitorInterface $unused_visitor, Node $unused_call_node, $unused_expr): ?Context
    {
        return null;
    }

    public function analyzeComplexCondition(ConditionVisitorInterface $unused_visitor, Node $unused_complex_node, $unused_expr): ?Context
    {
        // TODO: Could analyze get_class($array['field']) === stdClass::class (e.g. with AssignmentVisitor)
        return null;
    }
}
