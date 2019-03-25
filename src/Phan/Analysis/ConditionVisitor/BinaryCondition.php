<?php declare(strict_types=1);

namespace Phan\Analysis\ConditionVisitor;

use ast\Node;
use Phan\Analysis\ConditionVisitorInterface;
use Phan\Language\Context;

/**
 * This represents an assertion implementation acting on two sides of a condition (!=, ==, ===, etc)
 */
interface BinaryCondition
{
    /**
     * Assert that this condition applies to the variable $var (i.e. $var OPERATION $expr)
     *
     * @param Node $var
     * @param Node|int|string|float $expr
     * @return Context
     */
    public function analyzeVar(ConditionVisitorInterface $visitor, Node $var, $expr) : Context;

    /**
     * Assert that this condition applies to the variable $object (i.e. get_class($object) OPERATION $expr)
     *
     * @param Node|int|string|float $object
     * @param Node|int|string|float $expr
     * @return Context
     */
    public function analyzeClassCheck(ConditionVisitorInterface $visitor, $object, $expr) : Context;

    /**
     * Assert that this condition applies to the function call $call_node (i.e. is_string($object) OPERATION $expr)
     *
     * @param Node $call_node a node of kind AST_CALL
     * @param Node|string|int|float $expr
     * @return ?Context
     */
    public function analyzeCall(ConditionVisitorInterface $visitor, $call_node, $expr);
}
