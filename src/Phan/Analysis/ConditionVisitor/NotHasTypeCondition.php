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
     * @param Node|int|string|float $expr @unused-param
     * @override
     */
    public function analyzeVar(ConditionVisitorInterface $visitor, Node $var, $expr): Context
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
     * @param Node|int|string|float $object @unused-param
     * @param Node|int|string|float $expr @unused-param
     */
    public function analyzeClassCheck(ConditionVisitorInterface $visitor, $object, $expr): Context
    {
        // Unimplemented, Not likely to be commonly used.
        return $visitor->getContext();
    }

    /**
     * @unused-param $visitor
     * @unused-param $call_node
     * @unused-param $expr
     */
    public function analyzeCall(ConditionVisitorInterface $visitor, Node $call_node, $expr): ?Context
    {
        return null;
    }

    /**
     * @unused-param $visitor
     * @unused-param $complex_node
     * @unused-param $expr
     */
    public function analyzeComplexCondition(ConditionVisitorInterface $visitor, Node $complex_node, $expr): ?Context
    {
        // TODO: Could analyze get_class($array['field']) === stdClass::class (e.g. with AssignmentVisitor)
        return null;
    }
}
