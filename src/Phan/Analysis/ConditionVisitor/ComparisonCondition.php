<?php

declare(strict_types=1);

namespace Phan\Analysis\ConditionVisitor;

use ast;
use ast\Node;
use Phan\Analysis\ConditionVisitor;
use Phan\Analysis\ConditionVisitorInterface;
use Phan\Analysis\NegatedConditionVisitor;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Context;

/**
 * This represents a relative comparison assertion implementation acting on two sides of a condition (<, <=, >, >=)
 */
class ComparisonCondition implements BinaryCondition
{
    /** @var int the value of ast\Node->flags */
    private $flags;

    public function __construct(int $flags)
    {
        $this->flags = $flags;
    }

    /**
     * Assert that this condition applies to the variable $var (i.e. $var < $expr)
     *
     * @param Node $var
     * @param Node|int|string|float $expr
     * @override
     */
    public function analyzeVar(ConditionVisitorInterface $visitor, Node $var, $expr): Context
    {
        return $visitor->updateVariableToBeCompared($var, $expr, $this->flags);
    }

    /**
     * Assert that this condition applies to the variable $object (i.e. get_class($object) === $expr)
     *
     * @param Node|int|string|float $object
     * @param Node|int|string|float $expr
     * @suppress PhanUnusedPublicMethodParameter
     */
    public function analyzeClassCheck(ConditionVisitorInterface $visitor, $object, $expr): Context
    {
        return $visitor->getContext();
    }

    /**
     * @suppress PhanUnusedPublicMethodParameter
     */
    public function analyzeCall(ConditionVisitorInterface $visitor, Node $call_node, $expr): ?Context
    {
        $function_name = ConditionVisitor::getFunctionName($call_node);
        if (\is_string($function_name) && \strcasecmp($function_name, 'count') === 0) {
            $code_base = $visitor->getCodeBase();
            $context = $visitor->getContext();
            $value = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $expr)->asSingleScalarValueOrNullOrSelf();
            if (\is_object($value) || $value < 0) {
                return null;
            }
            if ($this->assertsPositiveNumber($value)) {
                // e.g. `if (is_string($x) === true)`
                return (new ConditionVisitor($code_base, $context))->visitCall($call_node);
            } elseif ($this->assertsZeroOrLess($value)) {
                return (new NegatedConditionVisitor($code_base, $context))->visitCall($call_node);
            }
        }
        return null;
    }

    /**
     * @param bool|int|float|string|null $value
     */
    private function assertsPositiveNumber($value): bool
    {
        if ($this->flags === ast\flags\BINARY_IS_GREATER) {
            return $value >= 0;
        } elseif ($this->flags === ast\flags\BINARY_IS_GREATER_OR_EQUAL) {
            return $value > 0;
        }
        return false;
    }

    /**
     * Returns true if, given a non-negative integer, an assertion of the comparison operation against this value
     * would only return true of that integer were 0.
     *
     * (e.g. to check if count($arr) implies $arr is the empty array)
     *
     * @param bool|int|float|string|null $value
     */
    private function assertsZeroOrLess($value): bool
    {
        if ($this->flags === ast\flags\BINARY_IS_SMALLER) {
            return $value > 0 && $value <= 1;
        } elseif ($this->flags === ast\flags\BINARY_IS_SMALLER_OR_EQUAL) {
            // @phan-suppress-next-line PhanPluginComparisonNotStrictForScalar, PhanSuspiciousTruthyString
            return $value == 0 && $value <= 0;
        }
        return false;
    }

    /**
     * @suppress PhanUnusedPublicMethodParameter
     */
    public function analyzeComplexCondition(ConditionVisitorInterface $visitor, Node $complex_node, $expr): ?Context
    {
        return null;
    }

    private const FLIP_OPERANDS = [
        ast\flags\BINARY_IS_GREATER          => ast\flags\BINARY_IS_SMALLER,
        ast\flags\BINARY_IS_GREATER_OR_EQUAL => ast\flags\BINARY_IS_SMALLER_OR_EQUAL,
        ast\flags\BINARY_IS_SMALLER          => ast\flags\BINARY_IS_GREATER,
        ast\flags\BINARY_IS_SMALLER_OR_EQUAL => ast\flags\BINARY_IS_GREATER_OR_EQUAL,
    ];

    /**
     * @return static
     */
    public function withFlippedOperands(): BinaryCondition
    {
        $flipped = self::FLIP_OPERANDS[$this->flags] ?? null;
        return isset($flipped) ? new self($flipped) : $this;
    }
}
