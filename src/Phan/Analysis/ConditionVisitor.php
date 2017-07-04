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
use Phan\Language\Type\CallableType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\ResourceType;
use Phan\Language\Type\ScalarType;
use Phan\Language\Type\StringType;
use Phan\Language\Element\Variable;
use Phan\Language\UnionType;
use ast\Node;


// TODO: Make $x != null remove FalseType and NullType from $x
// TODO: Make $x > 0, $x < 0, $x >= 50, etc.  remove FalseType and NullType from $x
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
        } else if ($flags === \ast\flags\BINARY_IS_NOT_IDENTICAL || $flags === \ast\flags\BINARY_IS_NOT_EQUAL) {
            $this->checkVariablesDefined($node);
            // TODO: Add a different function for IS_NOT_EQUAL, e.g. analysis of != null should be different from !== null (First would remove FalseType)
            return $this->analyzeIsNotIdentical($node->children['left'], $node->children['right']);
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
     * @param Node|int|float|string $left
     * @param Node|int|float|string $right
     * @return Context - Constant after inferring type from an expression such as `if ($x !== false)`
     */
    private function analyzeIsNotIdentical($left, $right) : Context
    {
        if (($left instanceof Node) && $left->kind === \ast\AST_VAR) {
            return $this->analyzeVarIsNotIdentical($left, $right);
        } else if (($right instanceof Node) && $right->kind === \ast\AST_VAR) {
            return $this->analyzeVarIsNotIdentical($right, $left);
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
        $var_name = $var_node->children['name'] ?? null;
        $context = $this->context;
        // Don't analyze variables such as $$a
        if (\is_string($var_name) && $var_name) {
            try {
                $exprType = UnionTypeVisitor::unionTypeFromLiteralOrConstant($this->code_base, $this->context, $expr);
                if ($exprType) {
                    // Get the variable we're operating on
                    $variable = $this->getVariableFromScope($var_node);
                    if (\is_null($variable)) {
                        return $context;
                    }
                    \assert(!\is_null($variable));  // redundant annotation for phan.

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
                // Swallow it (E.g. IssueException for undefined variable)
            }
        }
        return $context;
    }

    /**
     * @param Node $var_node
     * @param Node|int|float|string $expr
     * @return Context - Constant after inferring type from an expression such as `if ($x === 'literal')`
     */
    private function analyzeVarIsNotIdentical(Node $var_node, $expr) : Context
    {
        $var_name = $var_node->children['name'] ?? null;
        $context = $this->context;
        if (\is_string($var_name)) {
            try {
                if ($expr instanceof Node && $expr->kind === \ast\AST_CONST) {
                    $exprNameNode = $expr->children['name'];
                    if ($exprNameNode->kind === \ast\AST_NAME) {
                        // Currently, only add this inference when we're absolutely sure this is a check rejecting null/false/true
                        $exprName = $exprNameNode->children['name'];
                        switch(\strtolower($exprName)) {
                        case 'null':
                            return $this->removeNullFromVariable($var_node, $context);
                        case 'false':
                            return $this->removeFalseFromVariable($var_node, $context);
                        case 'true':
                            return $this->removeTrueFromVariable($var_node, $context);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Swallow it (E.g. IssueException for undefined variable)
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
            $args = $negatedNode->children['args']->children;
            foreach ($args as $arg) {
                if ($arg instanceof Node) {
                    $this->checkVariablesDefined($arg);
                }
            }

            if (self::isArgumentListWithVarAsFirstArgument($args)) {
                $function_name = strtolower(ltrim($negatedNode->children['expr']->children['name'], '\\'));
                if (\count($args) !== 1) {
                    if (\strcasecmp($function_name, 'is_a') === 0) {
                        return $this->analyzeNegationOfVariableIsA($args, $context);
                    }
                    return $context;
                }
                static $map;
                if ($map === null) {
                    $map = self::createNegationCallbackMap();
                }
                // TODO: Make this generic to all type assertions? E.g. if (!is_string($x)) removes 'string' from type, makes '?string' (nullable) into 'null'.
                // This may be redundant in some places if AST canonicalization is used, but still useful in some places
                // TODO: Make this generic so that it can be used in the 'else' branches?
                $callback = $map[$function_name] ?? null;
                if ($callback === null) {
                    return $context;
                }
                return $callback($this, $args[0], $context);
            }
        }
        return $context;
    }

    private function analyzeNegationOfVariableIsA(array $args, Context $context) : Context
    {
        // TODO: implement
        return $context;
    }

    /**
     * @return \Closure[] (ConditionVisitor $cv, Node $var_node, Context $context) -> Context
     */
    private static function createNegationCallbackMap() : array {
        $remove_empty_cb = function(ConditionVisitor $cv, Node $var_node, Context $context) : Context {
            return $cv->removeFalseyFromVariable($var_node, $context);
        };
        $remove_null_cb = function(ConditionVisitor $cv, Node $var_node, Context $context) : Context {
            return $cv->removeNullFromVariable($var_node, $context);
        };

        // Remove any Types from UnionType that are subclasses of $base_class_name
        $make_basic_negated_assertion_callback = static function(string $base_class_name) : \Closure
        {
            return static function(ConditionVisitor $cv, Node $var_node, Context $context) use($base_class_name) : Context {
                return $cv->updateVariableWithConditionalFilter(
                    $var_node,
                    $context,
                    function(UnionType $union_type) use($base_class_name) : bool {
                        return $union_type->hasTypeMatchingCallback(function(Type $type) use($base_class_name) : bool {
                            return $type instanceof $base_class_name;
                        });
                    },
                    function(UnionType $union_type) use ($base_class_name) : UnionType {
                        $new_type = new UnionType();
                        $has_null = false;
                        $has_other_nullable_types = false;
                        // Add types which are not instances of $base_class_name
                        foreach ($union_type->getTypeSet() as $type) {
                            if ($type instanceof $base_class_name) {
                                $has_null = $has_null || $type->getIsNullable();
                                continue;
                            }
                            assert($type instanceof Type);
                            $has_other_nullable_types = $has_other_nullable_types || $type->getIsNullable();
                            $new_type->addType($type);
                        }
                        // Add Null if some of the rejected types were were nullable, and none of the accepted types were nullable
                        if ($has_null && !$has_other_nullable_types) {
                            $new_type->addType(NullType::instance(false));
                        }
                        return $new_type;
                    }
                );
            };
        };
        $remove_float_callback = $make_basic_negated_assertion_callback(FloatType::class);
        $remove_int_callback = $make_basic_negated_assertion_callback(IntType::class);
        $remove_scalar_callback = static function(ConditionVisitor $cv, Node $var_node, Context $context) : Context {
            return $cv->updateVariableWithConditionalFilter(
                $var_node,
                $context,
                // if (!is_scalar($x)) removes scalar types from $x, but $x can still be null.
                function(UnionType $union_type) : bool {
                    return $union_type->hasTypeMatchingCallback(function(Type $type) : bool {
                        return ($type instanceof ScalarType) && !($type instanceof NullType);
                    });
                },
                function(UnionType $union_type) : UnionType {
                    $new_type = new UnionType();
                    $has_null = false;
                    $has_other_nullable_types = false;
                    // Add types which are not scalars
                    foreach ($union_type->getTypeSet() as $type) {
                        if ($type instanceof ScalarType && !($type instanceof NullType)) {
                            $has_null = $has_null || $type->getIsNullable();
                            continue;
                        }
                        assert($type instanceof Type);
                        $has_other_nullable_types = $has_other_nullable_types || $type->getIsNullable();
                        $new_type->addType($type);
                    }
                    // Add Null if some of the rejected types were were nullable, and none of the accepted types were nullable
                    if ($has_null && !$has_other_nullable_types) {
                        $new_type->addType(NullType::instance(false));
                    }
                    return $new_type;
                }
            );
        };
        $remove_callable_callback = static function(ConditionVisitor $cv, Node $var_node, Context $context) : Context {
            return $cv->updateVariableWithConditionalFilter(
                $var_node,
                $context,
                // if (!is_callable($x)) removes non-callable/closure types from $x.
                // TODO: Could check for __invoke()
                function(UnionType $union_type) : bool {
                    return $union_type->hasTypeMatchingCallback(function(Type $type) : bool {
                        return $type->isCallable();
                    });
                },
                function(UnionType $union_type) : UnionType {
                    $new_type = new UnionType();
                    $has_null = false;
                    $has_other_nullable_types = false;
                    // Add types which are not callable
                    foreach ($union_type->getTypeSet() as $type) {
                        if ($type->isCallable()) {
                            $has_null = $has_null || $type->getIsNullable();
                            continue;
                        }
                        assert($type instanceof Type);
                        $has_other_nullable_types = $has_other_nullable_types || $type->getIsNullable();
                        $new_type->addType($type);
                    }
                    // Add Null if some of the rejected types were were nullable, and none of the accepted types were nullable
                    if ($has_null && !$has_other_nullable_types) {
                        $new_type->addType(NullType::instance(false));
                    }
                    return $new_type;
                }
            );
        };

        return [
            'empty' => $remove_empty_cb,
            'is_null' => $remove_null_cb,
            'is_array' => $make_basic_negated_assertion_callback(ArrayType::class),
            // 'is_bool' => $make_basic_assertion_callback(BoolType::class),
            'is_callable' => $remove_callable_callback,
            'is_double' => $remove_float_callback,
            'is_float' => $remove_float_callback,
            'is_int' => $remove_int_callback,
            'is_integer' => $remove_int_callback,
            'is_iterable' => $make_basic_negated_assertion_callback(IterableType::class),  // TODO: Could keep basic array types and classes extending iterable
            'is_long' => $remove_int_callback,
            'is_null' => $remove_null_cb,
            // 'is_numeric' => $make_basic_assertion_callback('string|int|float'),
            // TODO 'is_object' => $remove_object_callback,
            'is_real' => $remove_float_callback,
            'is_resource' => $make_basic_negated_assertion_callback(ResourceType::class),
            'is_scalar' => $remove_scalar_callback,
            'is_string' => $make_basic_negated_assertion_callback(StringType::class),
        ];
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
        return $this->removeFalseyFromVariable($node, $this->context);
    }

    // Remove any types which are definitely falsey from that variable (NullType, FalseType)
    private function removeFalseyFromVariable(Node $var_node, Context $context) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            function(UnionType $type) : bool {
                return $type->containsFalsey();
            },
            function(UnionType $type) : UnionType {
                return $type->nonFalseyClone();
            }
        );
    }

    /**
     * Remove any types which are definitely truthy from that variable (objects, TrueType, ResourceType, etc.)
     * E.g. if (empty($x)) {} would result in this.
     * Note that Phan can't know some scalars are not an int/string/float, since 0/""/"0"/0.0/[] are empty.
     * (Remove arrays anyway)
     */
    private function removeTruthyFromVariable(Node $var_node, Context $context) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            function(UnionType $type) : bool {
                return $type->containsTruthy();
            },
            function(UnionType $type) : UnionType {
                return $type->nonTruthyClone();
            }
        );
    }

    private function removeNullFromVariable(Node $var_node, Context $context) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            function(UnionType $type) : bool {
                return $type->containsNullable();
            },
            function(UnionType $type) : UnionType {
                return $type->nonNullableClone();
            }
        );
    }

    private function removeFalseFromVariable(Node $var_node, Context $context) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            function(UnionType $type) : bool {
                return $type->containsFalse();
            },
            function(UnionType $type) : UnionType {
                return $type->nonFalseClone();
            }
        );
    }

    private function removeTrueFromVariable(Node $var_node, Context $context) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            function(UnionType $type) : bool {
                return $type->containsTrue();
            },
            function(UnionType $type) : UnionType {
                return $type->nonTrueClone();
            }
        );
    }

    /**
     * If the inferred UnionType makes $should_filter_cb return true
     * (indicating there are Types to be removed from the UnionType or altered),
     * then replace the UnionType with the modified UnionType which $filter_union_type_cb returns,
     * and update the context.
     *
     * Note: It's expected that $should_filter_cb returns false on the new UnionType of that variable.
     */
    private function updateVariableWithConditionalFilter(
        Node $var_node,
        Context $context,
        \Closure $should_filter_cb,
        \Closure $filter_union_type_cb
    ) : Context {
        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($var_node);
            if (\is_null($variable)) {
                return $context;
            }
            \assert(!\is_null($variable));  // redundant annotation for phan.

            $union_type = $variable->getUnionType();
            if (!$should_filter_cb($union_type)) {
                return $context;
            }

            // Make a copy of the variable
            $variable = clone($variable);

            $variable->setUnionType(
                $filter_union_type_cb($union_type)
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
            if (!Config::getValue('ignore_undeclared_variables_in_global_scope')
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
            \assert(!\is_null($variable));  // redundant annotation for phan.

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

    private static function isArgumentListWithVarAsFirstArgument(array $args) : bool
    {
        if (\count($args) >= 1) {
            $arg = $args[0];
            return ($arg instanceof Node) && ($arg->kind === \ast\AST_VAR);
        }
        return false;
    }

    /**
     * @param Variable $variable (Node argument in a call to is_object)
     * @return void
     */
    private static function analyzeIsObjectAssertion(Variable $variable)
    {
        // Change the type to match is_object relationship
        // If we already have the `object` type or generic object types, then keep those
        // (E.g. T|false becomes T, object|T[]|iterable|null becomes object)
        // TODO: Convert `iterable` to `Traversable`?
        // TODO: move to UnionType?
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
        $make_basic_assertion_callback = static function(string $union_type_string) : \Closure
        {
            $type = UnionType::fromFullyQualifiedString(
                $union_type_string
            );

            /** @return void */
            return static function(Variable $variable, array $args) use($type)
            {
                // Otherwise, overwrite the type for any simple
                // primitive types.
                $variable->setUnionType(clone($type));
            };
        };

        /** @return void */
        $array_callback = static function(Variable $variable, array $args)
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
        $object_callback = static function(Variable $variable, array $args)
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
        $is_a_callback = function(Variable $variable, array $args) use($object_callback)
        {
            $class_name = $args[1] ?? null;
            if (!\is_string($class_name)) {
                // Limit the types of $variable to an object if we can't infer the class name.
                $object_callback($variable, $args);
                return;
            }
            $class_name = ltrim($class_name, '\\');
            if (empty($class_name)) {
                return;
            }
            // TODO: validate argument
            $class_name = '\\' . $class_name;
            $class_type = Type::fromStringInContext($class_name, new Context(), Type::FROM_NODE);
            $variable->setUnionType($class_type->asUnionType());
        };

        /** @return void */
        $scalar_callback = static function(Variable $variable, array $args)
        {
            // Change the type to match the is_a relationship
            // If we already have possible scalar types, then keep those
            // (E.g. T|false becomes bool, T becomes int|float|bool|string|null)
            $newType = $variable->getUnionType()->scalarTypes();
            if ($newType->isEmpty()) {
                // If there are no inferred types, or the only type we saw was 'null',
                // assume there this can be any possible scalar.
                // (Excludes `resource`, which is technically a scalar)
                $newType = UnionType::fromFullyQualifiedString('int|float|bool|string');
            }
            $variable->setUnionType($newType);
        };
        $callable_callback = static function(Variable $variable, array $args)
        {
            // Change the type to match the is_a relationship
            // If we already have possible callable types, then keep those
            // (E.g. Closure|false becomes Closure)
            $newType = $variable->getUnionType()->callableTypes();
            if ($newType->isEmpty()) {
                // If there are no inferred types, or the only type we saw was 'null',
                // assume there this can be any possible scalar.
                // (Excludes `resource`, which is technically a scalar)
                $newType->addType(CallableType::instance(false));
            } else if ($newType->containsNullable()) {
                $newType = $newType->nonNullableClone();
            }
            $variable->setUnionType($newType);
        };

        $float_callback = $make_basic_assertion_callback('float');
        $int_callback = $make_basic_assertion_callback('int');
        $null_callback = $make_basic_assertion_callback('null');
        // Note: isset() is handled in visitIsset()

        return [
            'is_a' => $is_a_callback,
            'is_array' => $array_callback,
            'is_bool' => $make_basic_assertion_callback('bool'),
            'is_callable' => $callable_callback,
            'is_double' => $float_callback,
            'is_float' => $float_callback,
            'is_int' => $int_callback,
            'is_integer' => $int_callback,
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

    /**
     * Fetches the function name. Does not check for function uses or namespaces.
     * @return ?string (null if function name could not be found)
     */
    private static function getFunctionName(Node $node) {
        $expr = $node->children['expr'];
        if (!($expr instanceof Node)) {
            return null;
        }
        $raw_function_name = $expr->children['name'] ?? null;
        if (!(\is_string($raw_function_name) && $raw_function_name)) {
            return null;
        }
        return $raw_function_name;
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
        $raw_function_name = self::getFunctionName($node);
        if (!\is_string($raw_function_name)) {
            return $this->context;
        }
        assert(\is_string($raw_function_name));
        $args = $node->children['args']->children;
        // Only look at things of the form
        // `\is_string($variable)`
        if (!self::isArgumentListWithVarAsFirstArgument($args)) {
            return $this->context;
        }

        if (\count($args) !== 1) {
            if (!(\strcasecmp($raw_function_name, 'is_a') === 0 && \count($args) === 2)) {
                return $this->context;
            }
        }
        // Translate the function name into the UnionType it asserts
        static $map = null;

        if ($map === null) {
             $map = self::initTypeModifyingClosuresForVisitCall();
        }

        $function_name = strtolower($raw_function_name);
        $type_modification_callback = $map[$function_name] ?? null;
        if ($type_modification_callback === null) {
            return $this->context;
        }

        $context = $this->context;

        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($args[0]);

            if (\is_null($variable)) {
                return $context;
            }
            \assert(!\is_null($variable));  // redundant annotation for phan.

            if ($variable->getUnionType()->isEmpty()) {
                $variable->getUnionType()->addType(
                    NullType::instance(false)
                );
            }

            // Make a copy of the variable
            $variable = clone($variable);

            // Modify the types of that variable.
            $type_modification_callback($variable, $args);

            // Overwrite the variable with its new type in this
            // scope without overwriting other scopes
            $context = $context->withScopeVariable(
                $variable
            );
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance($this->code_base, $context, $exception->getIssueInstance());
        } catch (\Exception $exception) {
            // Swallow it (E.g. IssueException for undefined variable)
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
        $var_node = $node->children['expr'];
        if ($var_node->kind === \ast\AST_VAR) {
            return $this->updateVariableWithConditionalFilter(
                $var_node,
                $this->context,
                function(UnionType $type) : bool {
                    return $type->containsTruthy();
                },
                function(UnionType $type) : UnionType {
                    return $type->nonTruthyClone();
                }
            );
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
    public function visitExprList(Node $node) : Context
    {
        $children = $node->children;
        $count = \count($children);
        if ($count > 1) {
            foreach ($children as $sub_node) {
                --$count;
                if ($count > 0 && $sub_node instanceof Node) {
                    $this->checkVariablesDefined($sub_node);
                }
            }
        }
        // Only analyze the last expression in the expression list for conditions.
        $last_expression = \end($node->children);
        if ($last_expression instanceof Node) {
            return $this($last_expression);
        } else {
            // TODO: emit no-op warning
            return $this->context;
        }
    }
}
