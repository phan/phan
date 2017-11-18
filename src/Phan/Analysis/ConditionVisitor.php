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
class ConditionVisitor extends KindVisitorImplementation
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
        if ($flags === \ast\flags\BINARY_BOOL_AND) {
            return $this->analyzeShortCircuitingAnd($node->children['left'], $node->children['right']);
        } elseif ($flags === \ast\flags\BINARY_IS_IDENTICAL) {
            $this->checkVariablesDefined($node);
            return $this->analyzeIsIdentical($node->children['left'], $node->children['right']);
        } elseif ($flags === \ast\flags\BINARY_IS_NOT_IDENTICAL || $flags === \ast\flags\BINARY_IS_NOT_EQUAL) {
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
            // e.g. if ($x === null)
            return $this->updateVariableToBeIdentical($left, $right, $this->context);
        } elseif (($right instanceof Node) && $right->kind === \ast\AST_VAR) {
            // e.g. if (null === $x)
            return $this->updateVariableToBeIdentical($right, $left, $this->context);
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
            // e.g. if ($x !== null)
            return $this->updateVariableToBeNotIdentical($left, $right, $this->context);
        } elseif (($right instanceof Node) && $right->kind === \ast\AST_VAR) {
            // e.g. if (null !== $x)
            return $this->updateVariableToBeNotIdentical($right, $left, $this->context);
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
    public function visitAnd(Node $node) : Context
    {
        return $this->analyzeShortCircuitingAnd($node->children['left'], $node->children['right']);
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
    private function analyzeShortCircuitingAnd($left, $right) : Context
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
            return (new NegatedConditionVisitor($this->code_base, $this->context))($expr_node);
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
        $context = $this->context;
        $var_node = $node->children['var'];
        if ($var_node->kind !== \ast\AST_VAR) {
            $this->checkVariablesDefined($var_node);
            return $context;
        }

        $var_name = $var_node->children['name'];
        if (!\is_string($var_name)) {
            $this->checkVariablesDefined($var_node);
            return $context;
        }
        if (!$context->getScope()->hasVariableWithName($var_name)) {
            // Support analyzing cases such as `if (isset($x)) { use($x); }`, or `assert(isset($x))`
            $context->setScope($context->getScope()->withVariable(new Variable(
                $context->withLineNumberStart($var_node->lineno ?? 0),
                $var_name,
                new UnionType(),
                $var_node->flags ?? 0
            )));
        }
        return $this->removeNullFromVariable($var_node, $context, true);
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
        return $this->removeFalseyFromVariable($node, $this->context, false);
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
            $variable = $this->getVariableFromScope($expr_node, $context);
            if (\is_null($variable)) {
                return $context;
            }

            // Get the type that we're checking it against
            $class_node = $node->children['class'];
            $type = UnionType::fromNode(
                $this->context,
                $this->code_base,
                $class_node
            );
            // Make a copy of the variable
            $variable = clone($variable);
            $object_types = $type->objectTypes();
            if (!$object_types->isEmpty()) {
                // See https://secure.php.net/instanceof -

                // Add the type to the variable
                // $variable->getUnionType()->addUnionType($type);
                $variable->setUnionType($object_types);
            } else {
                if ($class_node->kind !== \ast\AST_NAME &&
                        !$type->canCastToUnionType(StringType::instance(false)->asUnionType())) {
                    Issue::maybeEmit(
                        $this->code_base,
                        $context,
                        Issue::TypeInvalidInstanceof,
                        $context->getLineNumberStart(),
                        (string)$type
                    );
                }
                $this->analyzeIsObjectAssertion($variable);
            }
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
        $make_basic_assertion_callback = static function (string $union_type_string) : \Closure {
            $type = UnionType::fromFullyQualifiedString(
                $union_type_string
            );

            /** @return void */
            return static function (Variable $variable, array $args) use ($type) {
                // Otherwise, overwrite the type for any simple
                // primitive types.
                $variable->setUnionType(clone($type));
            };
        };

        /** @return void */
        $array_callback = static function (Variable $variable, array $args) {
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
        $object_callback = static function (Variable $variable, array $args) {
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
        $is_a_callback = function (Variable $variable, array $args) use ($object_callback) {
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
        $scalar_callback = static function (Variable $variable, array $args) {
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
        $callable_callback = static function (Variable $variable, array $args) {
            // Change the type to match the is_a relationship
            // If we already have possible callable types, then keep those
            // (E.g. Closure|false becomes Closure)
            $newType = $variable->getUnionType()->callableTypes();
            if ($newType->isEmpty()) {
                // If there are no inferred types, or the only type we saw was 'null',
                // assume there this can be any possible scalar.
                // (Excludes `resource`, which is technically a scalar)
                $newType->addType(CallableType::instance(false));
            } elseif ($newType->containsNullable()) {
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
            $variable = $this->getVariableFromScope($args[0], $context);

            if (\is_null($variable)) {
                return $context;
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
        $var_node = $node->children['expr'];
        if ($var_node->kind === \ast\AST_VAR) {
            // Don't emit notices for if (empty($x)) {}, etc.
            return $this->removeTruthyFromVariable($var_node, $this->context, true);
        }
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
