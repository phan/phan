<?php

declare(strict_types=1);

namespace Phan\Analysis\ConditionVisitor;

use ast\Node;
use Phan\Analysis\ConditionVisitor;
use Phan\Analysis\ConditionVisitorInterface;
use Phan\Analysis\NegatedConditionVisitor;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Context;

use const ast\AST_VAR;

/**
 * This represents an equals assertion implementation acting on two sides of a condition (==)
 */
class EqualsCondition implements BinaryCondition
{
    /**
     * Assert that this condition applies to the variable $var (i.e. $var === $expr)
     *
     * @param Node $var
     * @param Node|int|string|float $expr
     * @override
     */
    public function analyzeVar(ConditionVisitorInterface $visitor, Node $var, $expr): Context
    {
        return $visitor->updateVariableToBeEqual($var, $expr);
    }

    /**
     * Assert that this condition applies to the variable $object (i.e. get_class($object) === $expr)
     *
     * @param Node|int|string|float $object
     * @param Node|int|string|float $expr
     */
    public function analyzeClassCheck(ConditionVisitorInterface $visitor, $object, $expr): Context
    {
        return $visitor->analyzeClassAssertion($object, $expr) ?? $visitor->getContext();
    }

    public function analyzeCall(ConditionVisitorInterface $visitor, Node $call_node, $expr, bool $negate = false): ?Context
    {
        $code_base = $visitor->getCodeBase();
        $context = $visitor->getContext();
        $expr_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $expr);
        if ($expr_type->isEmpty()) {
            return null;
        }
        // Skip check for `if is_bool`, allow weaker comparisons such as `is_string($x) == 1`
        $function_name = ConditionVisitor::getFunctionName($call_node);
        if (\is_string($function_name) && \strcasecmp($function_name, 'count') === 0) {
            if ($negate && $expr_type->containsTruthy()) {
                // Currently can't infer anything from `if (count($x) != 2)`
                return null;
            }
            // Handle count($x) == N to narrow array shapes by making optional fields required
            $value = $expr_type->asSingleScalarValueOrNullOrSelf();
            if (\is_int($value) && $value > 0 && !$negate) {
                $narrowed = self::analyzeCountEqualsN($visitor, $call_node, $value);
                if ($narrowed !== null) {
                    return $narrowed;
                }
            }
            // Fall through
        } elseif (!$expr_type->isExclusivelyBoolTypes() && !UnionTypeVisitor::unionTypeFromNode($code_base, $context, $call_node)->isExclusivelyBoolTypes()) {
            return null;
        }
        if ($negate ? !$expr_type->containsTruthy() : !$expr_type->containsFalsey()) {
            // e.g. `if (is_string($x) == true)`, or negated equals check such as `if (is_string($x) != false)`
            return (new ConditionVisitor($code_base, $context))->visitCall($call_node);
        } elseif ($negate ? !$expr_type->containsFalsey() : !$expr_type->containsTruthy()) {
            // e.g. `if (is_string($x) == false)`
            return (new NegatedConditionVisitor($code_base, $context))->visitCall($call_node);
        }
        return null;
    }

    /**
     * Handle count($x) == N assertions to narrow array shape types
     * by making optional fields required when the count matches the total field count.
     *
     * @param int $expected_count The count value being asserted
     */
    private static function analyzeCountEqualsN(ConditionVisitorInterface $visitor, Node $call_node, int $expected_count): ?Context
    {
        $args = $call_node->children['args']->children ?? [];
        $first_arg = $args[0] ?? null;
        if (!($first_arg instanceof Node)) {
            return null;
        }

        // For now, only handle simple variable nodes
        if ($first_arg->kind !== AST_VAR) {
            return null;
        }

        $var_name = $first_arg->children['name'] ?? null;
        if (!\is_string($var_name)) {
            return null;
        }

        $code_base = $visitor->getCodeBase();
        $context = $visitor->getContext();

        if (!$context->getScope()->hasVariableWithName($var_name)) {
            return null;
        }

        $variable = $context->getScope()->getVariableByName($var_name);
        $union_type = $variable->getUnionType();

        // Narrow array shapes based on count
        $narrowed_type = $union_type->withArrayShapeFieldsRequiredForCount($expected_count);

        // Also apply existing count() truthiness narrowing (non-falsey, countable types)
        $narrowed_type = $narrowed_type
            ->withStaticResolvedInContext($context)
            ->countableTypesStrictCast($code_base, $context)
            ->nonFalseyClone();

        if ($narrowed_type->isEqualTo($union_type)) {
            // No change, fall through to default handling
            return null;
        }

        // Clone variable and update type
        $variable = clone $variable;
        $variable->setUnionType($narrowed_type);

        return $context->withScopeVariable($variable);
    }

    public function analyzeComplexCondition(ConditionVisitorInterface $visitor, Node $complex_node, $expr, bool $negate = false): ?Context
    {
        $code_base = $visitor->getCodeBase();
        $context = $visitor->getContext();
        $expr_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $expr);
        if (!$expr_type->isExclusivelyBoolTypes() && !UnionTypeVisitor::unionTypeFromNode($code_base, $context, $complex_node)->isExclusivelyBoolTypes()) {
            return null;
        }
        if ($negate ? !$expr_type->containsTruthy() : !$expr_type->containsFalsey()) {
            // e.g. `if (($x instanceof Xyz) == true)`
            return (new ConditionVisitor($code_base, $context))->__invoke($complex_node);
        } elseif ($negate ? !$expr_type->containsFalsey() : !$expr_type->containsTruthy()) {
            // e.g. `if (($x instanceof Xyz) == false)`
            return (new NegatedConditionVisitor($code_base, $context))->__invoke($complex_node);
        }
        return null;
    }

    /** @return static */
    public function withFlippedOperands(): BinaryCondition
    {
        return $this;
    }
}
