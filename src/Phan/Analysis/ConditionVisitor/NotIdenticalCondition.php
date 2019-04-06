<?php declare(strict_types=1);

namespace Phan\Analysis\ConditionVisitor;

use ast\Node;
use Phan\Analysis\ConditionVisitor;
use Phan\Analysis\ConditionVisitorInterface;
use Phan\Analysis\NegatedConditionVisitor;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Context;

/**
 * This represents a not identical assertion implementation acting on two sides of a condition (!==)
 */
class NotIdenticalCondition implements BinaryCondition
{
    /**
     * Assert that this condition applies to the variable $var (i.e. $var !== $expr)
     *
     * @param Node $var
     * @param Node|int|string|float $expr
     * @return Context
     * @override
     */
    public function analyzeVar(ConditionVisitorInterface $visitor, Node $var, $expr) : Context
    {
        return $visitor->updateVariableToBeNotIdentical($var, $expr);
    }

    /**
     * Assert that this condition applies to the variable $object (i.e. get_class($object) !== $expr)
     *
     * @param Node $object
     * @param Node|int|string|float $expr
     * @return Context
     * @suppress PhanUnusedPublicMethodParameter
     */
    public function analyzeClassCheck(ConditionVisitorInterface $visitor, $object, $expr) : Context
    {
        return $visitor->getContext();
    }

    public function analyzeCall(ConditionVisitorInterface $visitor, $call_node, $expr)
    {
        if (!$expr instanceof Node) {
            return null;
        }
        $code_base = $visitor->getCodeBase();
        $context = $visitor->getContext();
        $value = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $expr)->asSingleScalarValueOrNullOrSelf();
        if (!\is_bool($value)) {
            return null;
        }
        if ($value) {
            // e.g. `if (!(is_string($x) === true))`
            return (new NegatedConditionVisitor($code_base, $context))->visitCall($call_node);
        } else {
            // e.g. `if (!(is_string($x) === false))`
            return (new ConditionVisitor($code_base, $context))->visitCall($call_node);
        }
    }
}
