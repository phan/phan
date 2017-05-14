<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\CodeBase;
use Phan\Langauge\Type;
use Phan\Language\Context;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use ast\Node;

class ConditionVisitor extends KindVisitorImplementation
{

    /**
     * @var CodeBase
     */
    private $code_base;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exits.
     */
    private $context;

    /**
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visit(Node $node) : Context
    {
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitBinaryOp(Node $node) : Context
    {
        $flags = ($node->flags ?? 0);
        if ($flags === \ast\flags\BINARY_BOOL_AND) {
            return $this->visitShortCircuitingAnd($node->children['left'], $node->children['right']);
        } else if ($flags === \ast\flags\BINARY_IS_IDENTICAL) {
            return $this->analyzeIsIdentical($node->children['left'], $node->children['right']);
        }
        return $this->context;
    }

    /**
     * @param Node|int|float|string $left
     * @param Node|int|float|string $right
     * @return Context - Constant after inferring type from an expression such as `if ($x === 'literal')`
     */
    private function analyzeIsIdentical($left, $right) : Context
    {
        if (($left instanceof Node) && $left->kind === \ast\AST_VAR) {
            return $this->analyzeVarIsIdentical($left, $right);
        } else if (($right instanceof Node) && $right->kind === \ast\AST_VAR) {
            return $this->analyzeVarIsIdentical($right, $left);
        }
        return $this->context;
    }

    /**
     * @param Node $left
     * @param Node|int|float|string $right
     * @return Context - Constant after inferring type from an expression such as `if ($x === 'literal')`
     */
    private function analyzeVarIsIdentical(Node $varNode, $expr) : Context
    {
        $name = $varNode->children['name'] ?? null;
        $context = $this->context;
        if (is_string($name) && $name) {
            $exprType = UnionTypeVisitor::unionTypeFromLiteralOrConstant($this->code_base, $this->context, $expr);
            if ($exprType) {
                // Get the variable we're operating on
                $variable = (new ContextNode(
                    $this->code_base,
                    $context,
                    $varNode
                ))->getVariable();

                // Make a copy of the variable
                $variable = clone($variable);

                $variable->setUnionType($exprType);

                // Overwrite the variable with its new type in this
                // scope without overwriting other scopes
                $context = $context->withScopeVariable(
                    $variable
                );
                return $context;
            }
        }
        return $context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAnd(Node $node) : Context
    {
        return $this->visitShortCircuitingAnd($node->children['left'], $node->children['right']);
    }

    /**
     * Helper method
     * @param Node|mixed $left
     * a Node or non-node to parse (possibly an AST literal)
     *
     * @param Node|mixed $right
     * a Node or non-node to parse (possibly an AST literal)
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    private function visitShortCircuitingAnd($left, $right) : Context
    {
        // Aside: If left/right is not a node, left/right is a literal such as a number/string, and is either always truthy or always falsey.
        // Inside of this conditional may be dead or redundant code.
        if ($left instanceof Node) {
            $this->context = $this($left);
        }
        if ($right instanceof Node) {
            return $this($right);
        }
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitUnaryOp(Node $node) : Context
    {
        if (($node->flags ?? 0) !== \ast\flags\UNARY_BOOL_NOT) {
            return $this->context;
        }
        return $this->updateContextWithNegation($node->children['expr'], $this->context);
    }

    private function updateContextWithNegation(Node $negatedNode, Context $context) : Context
    {
        // Negation
        // TODO: negate instanceof, other checks
        // TODO: negation would also go in the else statement
        if (($negatedNode->kind ?? 0) === \ast\AST_CALL) {
            if (self::isCallStringWithSingleVariableArgument($negatedNode)) {
                // TODO: Make this generic to all type assertions? E.g. if (!is_string($x)) removes 'string' from type, makes '?string' (nullable) into 'null'.
                // This may be redundant in some places if AST canonicalization is used, but still useful in some places
                // TODO: Make this generic so that it can be used in the 'else' branches?
                $function_name = $negatedNode->children['expr']->children['name'];
                if (in_array($function_name, ['empty', 'is_null', 'is_scalar'], true)) {
                    return $this->removeNullFromVariable($negatedNode->children['args']->children[0], $context);
                }
            }
        }
        return $context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCoalesce(Node $node) : Context
    {
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIsset(Node $node) : Context
    {
        if ($node->children['var']->kind !== \ast\AST_VAR) {
            return $this->context;
        }

        return $this->removeNullFromVariable($node->children['var'], $this->context);
    }

    /**
     * @param Node $node
     * A node to parse, with kind \ast\AST_VAR
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitVar(Node $node) : Context
    {
        return $this->removeNullFromVariable($node, $this->context);
    }

    private function removeNullFromVariable(Node $varNode, Context $context) : Context
    {
        try {
            // Get the variable we're operating on
            $variable = (new ContextNode(
                $this->code_base,
                $context,
                $varNode
            ))->getVariable();

            if (!$variable->getUnionType()->containsNullable()) {
                return $context;
            }

            // Make a copy of the variable
            $variable = clone($variable);

            $variable->setUnionType(
                $variable->getUnionType()->nonNullableClone()
            );

            // Overwrite the variable with its new type in this
            // scope without overwriting other scopes
            $context = $context->withScopeVariable(
                $variable
            );
        } catch (\Exception $exception) {
            // Swallow it
        }
        return $context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitInstanceof(Node $node) : Context
    {
        // Only look at things of the form
        // `$variable instanceof ClassName`
        if ($node->children['expr']->kind !== \ast\AST_VAR) {
            return $this->context;
        }

        $context = $this->context;

        try {
            // Get the variable we're operating on
            $variable = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['expr']
            ))->getVariable();

            // Get the type that we're checking it against
            $type = UnionType::fromNode(
                $this->context,
                $this->code_base,
                $node->children['class']
            );

            // Make a copy of the variable
            $variable = clone($variable);

            // Add the type to the variable
            // $variable->getUnionType()->addUnionType($type);
            $variable->setUnionType($type);

            // Overwrite the variable with its new type
            $context = $context->withScopeVariable(
                $variable
            );

        } catch (\Exception $exception) {
            // Swallow it
        }

        return $context;
    }

    private static function isCallStringWithSingleVariableArgument(Node $node) : bool
    {
        $args = $node->children['args']->children;
        return count($args) === 1
            && $args[0] instanceof Node
            && $args[0]->kind === \ast\AST_VAR
            && $node->children['expr'] instanceof Node
            && !empty($node->children['expr']->children['name'] ?? null)
            && is_string($node->children['expr']->children['name']);
    }

    /**
     * Look at elements of the form `is_array($v)` and modify
     * the type of the variable.
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCall(Node $node) : Context
    {
        // Only look at things of the form
        // `is_string($variable)`
        if (!self::isCallStringWithSingleVariableArgument($node)) {
            return $this->context;
        }

        // Translate the function name into the UnionType it asserts
        $map = array(
            'is_array' => 'array',
            'is_bool' => 'bool',
            'is_callable' => 'callable',
            'is_double' => 'float',
            'is_float' => 'float',
            'is_int' => 'int',
            'is_integer' => 'int',
            'is_iterable' => 'iterable',
            'is_long' => 'int',
            'is_null' => 'null',
            'is_numeric' => 'string|int|float',
            'is_object' => 'object',
            'is_real' => 'float',
            'is_resource' => 'resource',
            'is_scalar' => 'int|float|bool|string|null',
            'is_string' => 'string',
            'empty' => 'null',
        );

        $function_name = $node->children['expr']->children['name'];
        if (!isset($map[$function_name])) {
            return $this->context;
        }

        $type = UnionType::fromFullyQualifiedString(
            $map[$function_name]
        );

        $context = $this->context;

        try {
            // Get the variable we're operating on
            $variable = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['args']->children[0]
            ))->getVariable();

            if ($variable->getUnionType()->isEmpty()) {
                $variable->getUnionType()->addType(
                    NullType::instance(false)
                );
            }

            // Make a copy of the variable
            $variable = clone($variable);

            $variable->setUnionType(
                clone($variable->getUnionType())
            );

            // Change the type to match the is_a relationship
            if ($type->isType(ArrayType::instance(false))
                && $variable->getUnionType()->hasGenericArray()
            ) {
                // If the variable is already a generic array,
                // note that it can be an arbitrary array without
                // erasing the existing generic type.
                $variable->getUnionType()->addUnionType($type);
            } else {
                // Otherwise, overwrite the type for any simple
                // primitive types.
                $variable->setUnionType($type);
            }

            // Overwrite the variable with its new type in this
            // scope without overwriting other scopes
            $context = $context->withScopeVariable(
                $variable
            );
        } catch (\Exception $exception) {
            // Swallow it
        }

        return $context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitEmpty(Node $node) : Context
    {
        return $this->context;
    }
}
