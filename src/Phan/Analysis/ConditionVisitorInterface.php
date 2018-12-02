<?php declare(strict_types=1);
namespace Phan\Analysis;

use ast\Node;
use Phan\Language\Context;

/**
 * This implements common functionality to update variables based on checks within a conditional (of an if/elseif/else/while/for/assert(), etc.)
 *
 * Classes using ConditionVisitorUtil must implement this trait.
 */
interface ConditionVisitorInterface
{
    /**
     * Returns this ConditionVisitorInterface's Context.
     * This is needed by subclasses of BinaryCondition.
     */
    public function getContext() : Context;

    /**
     * @param Node $var_node
     * @param Node|int|float|string $expr
     * @return Context - Constant after inferring type from an expression such as `if ($x === 'literal')`
     */
    public function updateVariableToBeIdentical(
        Node $var_node,
        $expr,
        Context $context = null
    ) : Context;

    /**
     * @param Node $var_node
     * @param Node|int|float|string $expr
     * @return Context - Constant after inferring type from an expression such as `if ($x == 'literal')`
     */
    public function updateVariableToBeNotIdentical(
        Node $var_node,
        $expr,
        Context $context = null
    ) : Context;

    /**
     * @param Node $var_node
     * @param Node|int|float|string $expr
     * @param int $flags (e.g. \ast\flags\BINARY_IS_SMALLER)
     * @return Context - Constant after inferring type from an expression such as `if ($x === 'literal')`
     */
    public function updateVariableToBeCompared(
        Node $var_node,
        $expr,
        int $flags
    ) : Context;

    /**
     * @param Node $var_node
     * @param Node|int|float|string $expr
     * @return Context - Constant after inferring type from an expression such as `if ($x != 'literal')`
     */
    public function updateVariableToBeNotEqual(
        Node $var_node,
        $expr,
        Context $context = null
    ) : Context;

    /**
     * Returns a context where the variable for $object_node has the class found in $expr_node
     *
     * @param Node|string|int|float $object_node
     * @param Node|string|int|float $expr_node
     * @return ?Context
     */
    public function analyzeClassAssertion($object_node, $expr_node);
}
