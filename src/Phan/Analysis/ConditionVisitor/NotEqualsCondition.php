<?php

declare(strict_types=1);

namespace Phan\Analysis\ConditionVisitor;

use ast\Node;
use Phan\Analysis\ConditionVisitorInterface;
use Phan\Language\Context;

/**
 * This represents a not equals assertion implementation acting on two sides of a condition (!=)
 */
class NotEqualsCondition implements BinaryCondition
{
    /**
     * Assert that this condition applies to the variable $var (i.e. $var != $expr)
     *
     * @param Node $var
     * @param Node|int|string|float $expr
     * @override
     */
    public function analyzeVar(ConditionVisitorInterface $visitor, Node $var, $expr): Context
    {
        return $visitor->updateVariableToBeNotEqual($var, $expr);
    }

    /**
     * Assert that this condition applies to the variable $object (i.e. get_class($object) != $expr)
     *
     * @param Node|int|string|float $object
     * @param Node|int|string|float $expr
     * @override
     * @suppress PhanUnusedPublicMethodParameter
     */
    public function analyzeClassCheck(ConditionVisitorInterface $visitor, $object, $expr): Context
    {
        return $visitor->getContext();
    }

    public function analyzeCall(ConditionVisitorInterface $visitor, Node $call_node, $expr): ?Context
    {
        return (new EqualsCondition())->analyzeCall($visitor, $call_node, $expr, true);
    }

    public function analyzeComplexCondition(ConditionVisitorInterface $visitor, Node $complex_node, $expr): ?Context
    {
        return (new EqualsCondition())->analyzeComplexCondition($visitor, $complex_node, $expr, true);
    }

    /** @return static */
    public function withFlippedOperands(): BinaryCondition
    {
        return $this;
    }
}
