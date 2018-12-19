<?php declare(strict_types=1);

namespace Phan\Analysis\ConditionVisitor;

use ast\Node;
use Phan\Analysis\ConditionVisitorInterface;
use Phan\Language\Context;

/**
 * This represents an identical/equals assertion implementation acting on two sides of a condition (===)
 */
class IdenticalCondition implements BinaryCondition
{
    /**
     * Assert that this condition applies to the variable $var (i.e. $var === $expr)
     *
     * @param Node $var
     * @param Node|int|string|float $expr
     * @return Context
     * @override
     */
    public function analyzeVar(ConditionVisitorInterface $visitor, Node $var, $expr) : Context
    {
        return $visitor->updateVariableToBeIdentical($var, $expr);
    }

    /**
     * Assert that this condition applies to the variable $object (i.e. get_class($object) === $expr)
     *
     * @param Node|int|string|float $object
     * @param Node|int|string|float $expr
     * @return Context
     */
    public function analyzeClassCheck(ConditionVisitorInterface $visitor, $object, $expr) : Context
    {
        return $visitor->analyzeClassAssertion($object, $expr) ?? $visitor->getContext();
    }
}
