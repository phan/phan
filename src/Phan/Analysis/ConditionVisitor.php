<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Type;
use Phan\Language\Context;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\StringType;
use Phan\Language\Element\Variable;
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
        $this->checkVariablesDefined($node);
        return $this->context;
    }

    /**
     * Check if variables from within a generic condition are defined.
     * @param Node $node
     * A node to parse
     * @return void
     */
    private function checkVariablesDefined(Node $node)
    {
        while ($node->kind === \ast\AST_UNARY_OP) {
            $node = $node->children['expr'];
            if (!($node instanceof Node)) {
                return;
            }
        }
        // Get the type just to make sure everything
        // is defined.
        UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node,
            true
        );
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
            $this->checkVariablesDefined($node);
            return $this->analyzeIsIdentical($node->children['left'], $node->children['right']);
        } else {
            $this->checkVariablesDefined($node);
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
     * @param Node $var_node
     * @param Node|int|float|string $expr
     * @return Context - Constant after inferring type from an expression such as `if ($x === 'literal')`
     */
    private function analyzeVarIsIdentical(Node $var_node, $expr) : Context
    {
        $name = $var_node->children['name'] ?? null;
        $context = $this->context;
        if (\is_string($name) && $name) {
            try {
                $exprType = UnionTypeVisitor::unionTypeFromLiteralOrConstant($this->code_base, $this->context, $expr);
                if ($exprType) {
                    // Get the variable we're operating on
                    $variable = $this->getVariableFromScope($var_node);
                    if (\is_null($variable)) {
                        return $context;
                    }
                    assert(!\is_null($variable));  // redundant annotation for phan.

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
            } catch (\Exception $e) {
                // ignore it.
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
        $expr_node = $node->children['expr'];
        if (($node->flags ?? 0) !== \ast\flags\UNARY_BOOL_NOT) {
            if ($expr_node instanceof Node) {
                $this->checkVariablesDefined($expr_node);
            }
            return $this->context;
        }
        // TODO: Emit dead code issue for non-nodes
        if ($expr_node instanceof Node) {
            return $this->updateContextWithNegation($expr_node, $this->context);
        }
        return $this->context;
    }

    private function updateContextWithNegation(Node $negatedNode, Context $context) : Context
    {
        $this->checkVariablesDefined($negatedNode);
        // Negation
        // TODO: negate instanceof, other checks
        // TODO: negation would also go in the else statement
        if (($negatedNode->kind ?? 0) === \ast\AST_CALL) {
            $args = $negatedNode->children;
            $this->checkVariablesDefinedInCallArgs($negatedNode);
            if (self::isCallStringWithSingleVariableArgument($negatedNode)) {
                // TODO: Make this generic to all type assertions? E.g. if (!\is_string($x)) removes 'string' from type, makes '?string' (nullable) into 'null'.
                // This may be redundant in some places if AST canonicalization is used, but still useful in some places
                // TODO: Make this generic so that it can be used in the 'else' branches?
                $function_name = $negatedNode->children['expr']->children['name'];
                if (\in_array($function_name, ['empty', 'is_null', 'is_scalar'], true)) {
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
        $this->checkVariablesDefined($node);
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
        $this->checkVariablesDefined($node);
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
        $this->checkVariablesDefined($node);
        return $this->removeNullFromVariable($node, $this->context);
    }

    private function removeNullFromVariable(Node $var_node, Context $context) : Context
    {
        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($var_node);
            if (\is_null($variable)) {
                return $context;
            }
            assert(!\is_null($variable));  // redundant annotation for phan.

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
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance($this->code_base, $context, $exception->getIssueInstance());
        } catch (\Exception $exception) {
            // Swallow it
        }
        return $context;
    }

    /**
     * @return ?Variable - Returns null if the variable is undeclared and ignore_undeclared_variables_in_global_scope applies.
     *                     or if assertions won't be applied?
     * @throws IssueException if variable is undeclared and not ignored.
     * @see UnionTypeVisitor->visitVar
     *
     * TODO: support assertions on superglobals, within the current file scope?
     */
    private function getVariableFromScope(Node $var_node) {
        if (!($var_node instanceof Node || $var_node->kind !== \ast\AST_VAR)) {
            return null;
        }
        $var_name_node = $var_node->children['name'] ?? null;

        if ($var_name_node instanceof Node) {
            // This is nonsense. Give up, but check if it's a type other than int/string.
            // (e.g. to catch typos such as $$this->foo = bar;)
            $name_node_type = (new UnionTypeVisitor($this->code_base, $this->context, true))($var_name_node);
            static $int_or_string_type;
            if ($int_or_string_type === null) {
                $int_or_string_type = new UnionType();
                $int_or_string_type->addType(StringType::instance(false));
                $int_or_string_type->addType(IntType::instance(false));
                $int_or_string_type->addType(NullType::instance(false));
            }
            if (!$name_node_type->canCastToUnionType($int_or_string_type)) {
                Issue::maybeEmit($this->code_base, $this->context, Issue::TypeSuspiciousIndirectVariable, $var_name_node->lineno ?? 0, (string)$name_node_type);
            }

            return null;
        }

        $var_name = (string)$var_name_node;

        if (!$this->context->getScope()->hasVariableWithName($var_name)) {
            if (Variable::isHardcodedVariableInScopeWithName($var_name, $this->context->isInGlobalScope())) {
                return null;
            }
            if (!Config::get()->ignore_undeclared_variables_in_global_scope
                || !$this->context->isInGlobalScope()
            ) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredVariable)(
                        $this->context->getFile(),
                        $var_node->lineno ?? 0,
                        [$var_name]
                    )
                );
            }
            return null;
        }
        return $this->context->getScope()->getVariableByName(
            $var_name
        );
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
        //$this->checkVariablesDefined($node);
        // Only look at things of the form
        // `$variable instanceof ClassName`
        $expr_node = $node->children['expr'];
        if (!($expr_node instanceof Node) || $expr_node->kind !== \ast\AST_VAR) {
            return $this->context;
        }

        $context = $this->context;

        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($expr_node);
            if (\is_null($variable)) {
                return $context;
            }
            assert(!\is_null($variable));  // redundant annotation for phan.

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
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance($this->code_base, $context, $exception->getIssueInstance());
        } catch (\Exception $exception) {
            // Swallow it
        }

        return $context;
    }

    private static function isCallStringWithSingleVariableArgument(Node $node) : bool
    {
        $args = $node->children['args']->children;
        if (count($args) === 1) {
            $arg = $args[0];
            if (($arg instanceof Node) && ($arg->kind === \ast\AST_VAR)) {
                $expr = $node->children['expr'];
                if ($expr instanceof Node) {
                    $name = $expr->children['name'] ?? null;
                    if (\is_string($name) && $name) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * This function is called once, and returns closures to modify the types of variables.
     *
     * This contains Phan's logic for inferring the resulting union types of variables, e.g. in \is_array($x).
     *
     * @return \Closure[] - The closures to call for a given
     */
    private static function initTypeModifyingClosuresForVisitCall() : array
    {
        $make_basic_assertion_callback = function(string $union_type_string) : \Closure
        {
            $type = UnionType::fromFullyQualifiedString(
                $union_type_string
            );

            /** @return void */
            return function(Variable $variable) use($type)
            {
                // Otherwise, overwrite the type for any simple
                // primitive types.
                $variable->setUnionType(clone($type));
            };
        };

        /** @return void */
        $array_callback = function(Variable $variable)
        {
            // Change the type to match the is_a relationship
            // If we already have generic array types, then keep those
            // (E.g. T[]|false becomes T[], ?array|null becomes array
            $newType = $variable->getUnionType()->genericArrayTypes();
            if ($newType->isEmpty()) {
                $newType->addType(ArrayType::instance(false));
            } else {
                // Convert inferred (?T)[] to T[], ?array to array
                if ($newType->containsNullable()) {
                    $newType = $newType->nonNullableClone();
                }
            }
            $variable->setUnionType($newType);
        };

        /** @return void */
        $object_callback = function(Variable $variable)
        {
            // Change the type to match the is_a relationship
            // If we already have the `object` type or generic object types, then keep those
            // (E.g. T|false becomes T, object|T[]|iterable|null becomes object)
            $newType = $variable->getUnionType()->objectTypes();
            if ($newType->isEmpty()) {
                $newType->addType(ObjectType::instance(false));
            } else {
                // Convert inferred ?MyClass to MyClass, ?object to object
                if ($newType->containsNullable()) {
                    $newType = $newType->nonNullableClone();
                }
            }
            $variable->setUnionType($newType);
        };
        /** @return void */
        $scalar_callback = function(Variable $variable)
        {
            // Change the type to match the is_a relationship
            // If we already have possible scalar types, then keep those
            // (E.g. T|false becomes bool, T becomes int|float|bool|string|null)
            $newType = $variable->getUnionType()->scalarTypes();
            if ($newType->isEmpty() || $newType->isType(NullType::instance(false))) {
                // If there are no inferred types, or the only type we saw was 'null',
                // assume there this can be any possible scalar.
                // (Excludes `resource`, which is technically a scalar)
                $newType = UnionType::fromFullyQualifiedString('int|float|bool|string|null');
            }
            $variable->setUnionType($newType);
        };

        $float_callback = $make_basic_assertion_callback('float');
        $int_callback = $make_basic_assertion_callback('int');
        $null_callback = $make_basic_assertion_callback('null');
        // Note: isset() is handled in visitIsset()

        return [
            'is_array' => $array_callback,
            'is_bool' => $make_basic_assertion_callback('bool'),
            'is_callable' => $make_basic_assertion_callback('callable'),
            'is_double' => $float_callback,
            'is_float' => $float_callback,
            'is_int' => $int_callback,
            'is_integer' => $int_callback,
            'is_iterable' => $make_basic_assertion_callback('iterable'),  // TODO: Could keep basic array types and classes extending iterable
            'is_long' => $int_callback,
            'is_null' => $null_callback,
            'is_numeric' => $make_basic_assertion_callback('string|int|float'),
            'is_object' => $object_callback,
            'is_real' => $float_callback,
            'is_resource' => $make_basic_assertion_callback('resource'),
            'is_scalar' => $scalar_callback,
            'is_string' => $make_basic_assertion_callback('string'),
            'empty' => $null_callback,
        ];
    }

    private function checkVariablesDefinedInCallArgs(Node $node) {
        $args = $node->children['args']->children;
        foreach ($args as $arg) {
            if ($arg instanceof Node) {
                $this->checkVariablesDefined($arg);
            }
        }
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
        static $map = null;
        if (empty($map)) {
             $map = self::initTypeModifyingClosuresForVisitCall();
        }

        $function_name = strtolower($node->children['expr']->children['name']);
        $type_modification_callback = $map[$function_name] ?? null;
        if ($type_modification_callback === null) {
            return $this->context;
        }

        $context = $this->context;

        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($node->children['args']->children[0]);

            if (\is_null($variable)) {
                return $context;
            }
            assert(!\is_null($variable));  // redundant annotation for phan.

            if ($variable->getUnionType()->isEmpty()) {
                $variable->getUnionType()->addType(
                    NullType::instance(false)
                );
            }

            // Make a copy of the variable
            $variable = clone($variable);

            // Modify the types of that variable.
            $type_modification_callback($variable);

            // Overwrite the variable with its new type in this
            // scope without overwriting other scopes
            $context = $context->withScopeVariable(
                $variable
            );
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance($this->code_base, $context, $exception->getIssueInstance());
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
        $this->checkVariablesDefined($node);
        // TODO: implement rest of empty check
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
    public function visitExprList(Node $node) : Context
    {
        $children = $node->children;
        $count = count($children);
        if ($count > 1) {
            foreach ($children as $sub_node) {
                --$count;
                if ($count > 0 && $sub_node instanceof Node) {
                    $this->checkVariablesDefined($sub_node);
                }
            }
        }
        // Only analyze the last expression in the expression list for conditions.
        return $this(end($node->children));
    }
}
