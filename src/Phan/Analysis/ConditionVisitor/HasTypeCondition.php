<?php declare(strict_types=1);

namespace Phan\Analysis\ConditionVisitor;

use ast\Node;
use Phan\Analysis\ConditionVisitorInterface;
use Phan\Language\Context;
use Phan\Language\UnionType;

/**
 * An expression with the side effect that the given node has type T
 */
class HasTypeCondition implements BinaryCondition
{
    /** @var UnionType the type that this is asserting it has */
    private $type;

    public function __construct(UnionType $type)
    {
        $this->type = $type;
    }
    /**
     * Assert that this condition applies to the variable $var (i.e. $var has type $union_type)
     *
     * @param Node $var
     * @param Node|int|string|float $unused_expr
     * @return Context
     * @override
     */
    public function analyzeVar(ConditionVisitorInterface $visitor, Node $var, $unused_expr) : Context
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

        $variable->setUnionType($this->type);

        // Overwrite the variable with its new type in this
        // scope without overwriting other scopes
        return $context->withScopeVariable(
            $variable
        );
    }

    /**
     * Assert that this condition applies to the variable $object (i.e. get_class($object) === $expr)
     *
     * @param Node|int|string|float $object
     * @param Node|int|string|float $unused_expr
     * @return Context
     */
    public function analyzeClassCheck(ConditionVisitorInterface $visitor, $object, $unused_expr) : Context
    {
        $class_string = $this->type->asSingleScalarValueOrNull();
        if ($class_string === null) {
            return $visitor->getContext();
        }
        return $visitor->analyzeClassAssertion($object, $class_string) ?? $visitor->getContext();
    }

    /**
     * @suppress PhanUnusedPublicMethodParameter
     */
    public function analyzeCall(ConditionVisitorInterface $visitor, $call_node, $expr)
    {
        return null;
    }
}
