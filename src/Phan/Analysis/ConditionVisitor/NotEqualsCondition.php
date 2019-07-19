<?php declare(strict_types=1);

namespace Phan\Analysis\ConditionVisitor;

use ast\Node;
use Phan\Analysis\ConditionVisitor;
use Phan\Analysis\ConditionVisitorInterface;
use Phan\Analysis\NegatedConditionVisitor;
use Phan\AST\UnionTypeVisitor;
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
    public function analyzeVar(ConditionVisitorInterface $visitor, Node $var, $expr) : Context
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
    public function analyzeClassCheck(ConditionVisitorInterface $visitor, $object, $expr) : Context
    {
        return $visitor->getContext();
    }

    public function analyzeCall(ConditionVisitorInterface $visitor, Node $call_node, $expr) : ?Context
    {
        if (!$expr instanceof Node) {
            return null;
        }
        $code_base = $visitor->getCodeBase();
        $context = $visitor->getContext();
        $value = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $expr)->asSingleScalarValueOrNullOrSelf();
        if (\is_object($value)) {
            return null;
        }
        $is_true = $value == true;
        $is_false = $value == false;
        if ($is_true === $is_false) {
            return null;
        }
        if ($is_true) {
            // e.g. `if (is_string($x) != true)`
            return (new NegatedConditionVisitor($code_base, $context))->visitCall($call_node);
        } else {
            // e.g. `if (is_string($x) != false)`
            return (new ConditionVisitor($code_base, $context))->visitCall($call_node);
        }
    }
}
