<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\Type;
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
use Phan\Language\UnionType;
use ast\Node;

// TODO: Make $x != null remove FalseType and NullType from $x
// TODO: Make $x > 0, $x < 0, $x >= 50, etc.  remove FalseType and NullType from $x
class NegatedConditionVisitor extends KindVisitorImplementation
{
    use ConditionVisitorUtil;

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
        if ($flags === \ast\flags\BINARY_BOOL_OR) {
            return $this->analyzeShortCircuitingOr($node->children['left'], $node->children['right']);
        } elseif ($flags === \ast\flags\BINARY_IS_IDENTICAL) {
            $this->checkVariablesDefined($node);
            return $this->analyzeIsIdentical($node->children['left'], $node->children['right']);
        } elseif ($flags === \ast\flags\BINARY_IS_NOT_IDENTICAL || $flags === \ast\flags\BINARY_IS_NOT_EQUAL) {
            $this->checkVariablesDefined($node);
            // TODO: Add a different function for IS_NOT_EQUAL, e.g. analysis of != null should be different from !== null (First would remove FalseType)
            return $this->analyzeIsNotIdentical($node->children['left'], $node->children['right']);
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
            // e.g. if (!($x === null))
            return $this->updateVariableToBeNotIdentical($left, $right, $this->context);
        } elseif (($right instanceof Node) && $right->kind === \ast\AST_VAR) {
            // e.g. if (!(null === $x))
            return $this->updateVariableToBeNotIdentical($right, $left, $this->context);
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
            // e.g. if (!($x !== null))
            return $this->updateVariableToBeIdentical($left, $right, $this->context);
        } elseif (($right instanceof Node) && $right->kind === \ast\AST_VAR) {
            // e.g. if (!(null !== $x))
            return $this->updateVariableToBeIdentical($right, $left, $this->context);
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
    public function visitOr(Node $node) : Context
    {
        return $this->analyzeShortCircuitingOr($node->children['left'], $node->children['right']);
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
    private function analyzeShortCircuitingOr($left, $right) : Context
    {
        // Analyze expressions such as if (!(is_string($x) || is_int($x)))
        // which would be equivalent to if (!is_string($x)) { if (!is_int($x)) { ... }}

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
            // The negated version of a NegatedConditionVisitor is a ConditionVisitor.
            return (new ConditionVisitor($this->code_base, $this->context))($expr_node);
        }
        return $this->context;
    }

    /**
     * Look at elements of the form `is_array($v)` and modify
     * the type of the variable to negate that check.
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCall(Node $node)
    {
        $raw_function_name = self::getFunctionName($node);
        if (!\is_string($raw_function_name)) {
            return $this->context;
        }
        $args = $node->children['args']->children;
        foreach ($args as $arg) {
            if ($arg instanceof Node) {
                $this->checkVariablesDefined($arg);
            }
        }

        $context = $this->context;
        if (self::isArgumentListWithVarAsFirstArgument($args)) {
            $function_name = strtolower(ltrim($raw_function_name, '\\'));
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
        return $context;
    }

    public function visitVar(Node $node)
    {
        $this->checkVariablesDefined($node);
        return $this->removeTruthyFromVariable($node, $this->context, false);
    }

    // TODO: empty, isset

    // TODO: negate instanceof

    private function analyzeNegationOfVariableIsA(array $args, Context $context) : Context
    {
        // TODO: implement
        return $context;
    }

    /**
     * @return \Closure[] (ConditionVisitor $cv, Node $var_node, Context $context) -> Context
     */
    private static function createNegationCallbackMap() : array
    {
        $remove_null_cb = function (NegatedConditionVisitor $cv, Node $var_node, Context $context) : Context {
            return $cv->removeNullFromVariable($var_node, $context, false);
        };

        // Remove any Types from UnionType that are subclasses of $base_class_name
        $make_basic_negated_assertion_callback = static function (string $base_class_name) : \Closure {
            return static function (NegatedConditionVisitor $cv, Node $var_node, Context $context) use ($base_class_name) : Context {
                return $cv->updateVariableWithConditionalFilter(
                    $var_node,
                    $context,
                    function (UnionType $union_type) use ($base_class_name) : bool {
                        return $union_type->hasTypeMatchingCallback(function (Type $type) use ($base_class_name) : bool {
                            return $type instanceof $base_class_name;
                        });
                    },
                    function (UnionType $union_type) use ($base_class_name) : UnionType {
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
                    },
                    false
                );
            };
        };
        $remove_float_callback = $make_basic_negated_assertion_callback(FloatType::class);
        $remove_int_callback = $make_basic_negated_assertion_callback(IntType::class);
        $remove_scalar_callback = static function (NegatedConditionVisitor $cv, Node $var_node, Context $context) : Context {
            return $cv->updateVariableWithConditionalFilter(
                $var_node,
                $context,
                // if (!is_scalar($x)) removes scalar types from $x, but $x can still be null.
                function (UnionType $union_type) : bool {
                    return $union_type->hasTypeMatchingCallback(function (Type $type) : bool {
                        return ($type instanceof ScalarType) && !($type instanceof NullType);
                    });
                },
                function (UnionType $union_type) : UnionType {
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
                },
                false
            );
        };
        $remove_callable_callback = static function (NegatedConditionVisitor $cv, Node $var_node, Context $context) : Context {
            return $cv->updateVariableWithConditionalFilter(
                $var_node,
                $context,
                // if (!is_callable($x)) removes non-callable/closure types from $x.
                // TODO: Could check for __invoke()
                function (UnionType $union_type) : bool {
                    return $union_type->hasTypeMatchingCallback(function (Type $type) : bool {
                        return $type->isCallable();
                    });
                },
                function (UnionType $union_type) : UnionType {
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
                },
                false
            );
        };

        return [
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
        // if (!isset($x))
        return $this->updateVariableWithNewType($node->children['var'], $this->context, NullType::instance(false)->asUnionType(), true);
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
        $context = $this->context;
        $var_node = $node->children['expr'];
        // if (!empty($x))
        if ($var_node->kind === \ast\AST_VAR) {
            // Don't check if variables are defined - don't emit notices for if (!empty($x)) {}, etc.
            $var_name = $var_node->children['name'];
            if (is_string($var_name)) {
                if (!$context->getScope()->hasVariableWithName($var_name)) {
                    // Support analyzing cases such as `if (!empty($x)) { use($x); }`, or `assert(!empty($x))`
                    // (In the PHP language, empty($x) is equivalent to (!isset($x) || !$x))
                    $context->setScope($context->getScope()->withVariable(new Variable(
                        $context->withLineNumberStart($var_node->lineno ?? 0),
                        $var_name,
                        new UnionType(),
                        $var_node->flags ?? 0
                    )));
                }
                $context->setScope($context->getScope()->withVariable(new Variable(
                    $context->withLineNumberStart($var_node->lineno ?? 0),
                    $var_name,
                    new UnionType(),
                    $var_node->flags ?? 0
                )));
                return $this->removeFalseyFromVariable($var_node, $context, true);
            }
        }
        $this->checkVariablesDefined($node);
        return $context;
    }

    /**
     * @param Node $node
     * A node to parse
     * (Should be useful when analyzing for loops with no breaks (`for (; !is_string($x); ){...}, in the future))
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
        // Only analyze the last expression in the expression list for (negation of) conditions.
        $last_expression = \end($node->children);
        if ($last_expression instanceof Node) {
            return $this($last_expression);
        } else {
            // TODO: emit no-op warning
            return $this->context;
        }
    }
}
