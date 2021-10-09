<?php

declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use ast;
use ast\flags;
use ast\Node;
use Closure;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\BlockAnalysisVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IntersectionType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ResourceType;
use Phan\Language\Type\ScalarType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;

/**
 * A visitor that takes a Context and a Node for a condition and returns a Context that has been updated with the negation of that condition.
 */
class NegatedConditionVisitor extends KindVisitorImplementation implements ConditionVisitorInterface
{
    // TODO: if (a || b || c || d) might get really slow, due to creating both ConditionVisitor and NegatedConditionVisitor
    use ConditionVisitorUtil;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exists.
     */
    protected $context;

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
    public function visit(Node $node): Context
    {
        $this->checkVariablesDefined($node);
        if (Config::getValue('redundant_condition_detection')) {
            $this->checkRedundantOrImpossibleTruthyCondition($node, $this->context, null, true);
        }
        return $this->context;
    }

    /**
     * Check if variables from within a generic condition are defined.
     * @param Node $node
     * A node to parse
     */
    private function checkVariablesDefined(Node $node): void
    {
        while ($node->kind === ast\AST_UNARY_OP) {
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
    public function visitBinaryOp(Node $node): Context
    {
        $flags = $node->flags ?? 0;
        switch ($flags) {
            case flags\BINARY_BOOL_OR:
                return $this->analyzeShortCircuitingOr($node->children['left'], $node->children['right']);
            case flags\BINARY_BOOL_AND:
                return $this->analyzeShortCircuitingAnd($node->children['left'], $node->children['right']);
            case flags\BINARY_IS_IDENTICAL:
                $this->checkVariablesDefined($node);
                return $this->analyzeAndUpdateToBeNotIdentical($node->children['left'], $node->children['right']);
            case flags\BINARY_IS_EQUAL:
                $this->checkVariablesDefined($node);
                return $this->analyzeAndUpdateToBeNotEqual($node->children['left'], $node->children['right']);
            case flags\BINARY_IS_NOT_IDENTICAL:
                $this->checkVariablesDefined($node);
                return $this->analyzeAndUpdateToBeIdentical($node->children['left'], $node->children['right']);
            case flags\BINARY_IS_NOT_EQUAL:
                $this->checkVariablesDefined($node);
                return $this->analyzeAndUpdateToBeEqual($node->children['left'], $node->children['right']);
            case flags\BINARY_IS_GREATER:
                $this->checkVariablesDefined($node);
                return $this->analyzeAndUpdateToBeCompared($node->children['left'], $node->children['right'], flags\BINARY_IS_SMALLER_OR_EQUAL);
            case flags\BINARY_IS_GREATER_OR_EQUAL:
                $this->checkVariablesDefined($node);
                return $this->analyzeAndUpdateToBeCompared($node->children['left'], $node->children['right'], flags\BINARY_IS_SMALLER);
            case flags\BINARY_IS_SMALLER:
                $this->checkVariablesDefined($node);
                return $this->analyzeAndUpdateToBeCompared($node->children['left'], $node->children['right'], flags\BINARY_IS_GREATER_OR_EQUAL);
            case flags\BINARY_IS_SMALLER_OR_EQUAL:
                $this->checkVariablesDefined($node);
                return $this->analyzeAndUpdateToBeCompared($node->children['left'], $node->children['right'], flags\BINARY_IS_GREATER);
            default:
                $this->checkVariablesDefined($node);
                return $this->context;
        }
    }

    /**
     * Helper method
     * @param Node|string|int|float $left
     * a Node or non-node to parse (possibly an AST literal)
     *
     * @param Node|string|int|float $right
     * a Node or non-node to parse (possibly an AST literal)
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the negation of the short-circuiting and.
     *
     * @suppress PhanSuspiciousTruthyString deliberate cast of literal to boolean
     */
    private function analyzeShortCircuitingAnd($left, $right): Context
    {
        // Analyze expressions such as if (!(is_string($x) || is_int($x)))
        // which would be equivalent to if (!is_string($x)) { if (!is_int($x)) { ... }}

        // Aside: If left/right is not a node, left/right is a literal such as a number/string, and is either always truthy or always falsey.
        // Inside of this conditional may be dead or redundant code.

        // Aside: If left/right is not a node, left/right is a literal such as a number/string, and is either always truthy or always falsey.
        // Inside of this conditional may be dead or redundant code.
        if (!($left instanceof Node)) {
            if (!$left) {
                return $this->context;
            }
            if (!$right instanceof Node) {
                return $this->context;
            }
            return $this($right);
        }
        if (!($right instanceof Node)) {
            if (!$right) {
                return $this->context;
            }
            return $this($left);
        }
        $code_base = $this->code_base;
        $context = $this->context;
        $left_false_context = (new NegatedConditionVisitor($code_base, $context))($left);
        $left_true_context = (new ConditionVisitor($code_base, $context))($left);
        // We analyze the right-hand side of `cond($x) && cond2($x)` as if `cond($x)` was true.
        $right_false_context = (new NegatedConditionVisitor($code_base, $left_true_context))($right);
        // When the NegatedConditionVisitor is false, at least one of the left or right contexts must be false.
        // (NegatedConditionVisitor returns a context for when the input Node's value was falsey)
        return (new ContextMergeVisitor($context, [$left_false_context, $right_false_context]))->combineChildContextList();
    }

    /**
     * @param Node|string|int|float $left
     * a Node or non-node to parse (possibly an AST literal)
     *
     * @param Node|string|int|float $right
     * a Node or non-node to parse (possibly an AST literal)
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the negation of the short-circuiting or.
     */
    private function analyzeShortCircuitingOr($left, $right): Context
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
    public function visitUnaryOp(Node $node): Context
    {
        $expr_node = $node->children['expr'];
        $flags = $node->flags;
        if ($flags !== flags\UNARY_BOOL_NOT) {
            if (Config::getValue('redundant_condition_detection')) {
                $this->checkRedundantOrImpossibleTruthyCondition($node, $this->context, null, true);
            }
            if ($expr_node instanceof Node) {
                if ($flags === flags\UNARY_SILENCE) {
                    return $this->__invoke($expr_node);
                }
                $this->checkVariablesDefined($expr_node);
            }
            return $this->context;
        }
        // TODO: Emit dead code issue for non-nodes
        if ($expr_node instanceof Node) {
            // The negated version of a NegatedConditionVisitor is a ConditionVisitor.
            return (new ConditionVisitor($this->code_base, $this->context))($expr_node);
        } elseif (Config::getValue('redundant_condition_detection')) {
            // Check `scalar` of `if (!scalar)`
            $this->checkRedundantOrImpossibleTruthyCondition($expr_node, $this->context, null, false);
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
    public function visitCall(Node $node): Context
    {
        $args_node = $node->children['args'];
        if ($args_node->kind === ast\AST_CALLABLE_CONVERT) {
            // Warn about `if (!strlen(...))` always being a truthy closure
            $this->checkRedundantOrImpossibleTruthyCondition($node, $this->context, null, false);
            return $this->context;
        }
        $raw_function_name = self::getFunctionName($node);
        if (!\is_string($raw_function_name)) {
            return $this->context;
        }
        $args = $args_node->children;

        $function_name = \strtolower(\ltrim($raw_function_name, '\\'));
        if ($function_name === 'array_key_exists') {
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument
            return $this->analyzeArrayKeyExistsNegation($args);
        }
        static $map;
        if ($map === null) {
            $map = self::createNegationCallbackMap();
        }
        $type_modification_callback = $map[$function_name] ?? null;
        if ($type_modification_callback === null) {
            return $this->context;
        }
        $first_arg = $args[0] ?? null;
        if (!($first_arg instanceof Node && $first_arg->kind === ast\AST_VAR)) {
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument, PhanTypeMismatchArgumentNullable
            return $this->modifyComplexExpression($first_arg, $type_modification_callback, $this->context, $args);
        }

        $context = $this->context;

        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($first_arg, $context);

            if (\is_null($variable)) {
                return $context;
            }

            // Make a copy of the variable
            $variable = clone($variable);

            // Modify the types of that variable.
            $type_modification_callback($this->code_base, $context, $variable, $args);

            // Overwrite the variable with its new type in this
            // scope without overwriting other scopes
            $context = $context->withScopeVariable(
                $variable
            );
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance($this->code_base, $context, $exception->getIssueInstance());
        } catch (\Exception $_) {
            // Swallow it (E.g. IssueException for undefined variable)
        }

        return $context;
    }

    public function visitVar(Node $node): Context
    {
        $this->checkVariablesDefined($node);
        return $this->removeTruthyFromVariable($node, $this->context, false, false);
    }

    /**
     * @param Node $node
     * A node to parse, with kind ast\AST_NULLABLE_PROP (e.g. `if (!$this?->prop_name)`)
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitNullsafeProp(Node $node): Context
    {
        // TODO: Adjust this for values other than $this, e.g. to imply the expression is null or an object
        return $this->visitProp($node);
    }

    /**
     * @param Node $node
     * A node to parse, with kind ast\AST_PROP (e.g. `if (!$this->prop_name)`)
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitProp(Node $node): Context
    {
        $expr_node = $node->children['expr'];
        if (!($expr_node instanceof Node)) {
            return $this->context;
        }
        if ($expr_node->kind !== ast\AST_VAR || $expr_node->children['name'] !== 'this') {
            return $this->context;
        }
        if (!\is_string($node->children['prop'])) {
            return $this->context;
        }
        return $this->modifyPropertyOfThisSimple(
            $node,
            function (UnionType $type) use ($node): UnionType {
                if (Config::getValue('error_prone_truthy_condition_detection')) {
                    $this->checkErrorProneTruthyCast($node, $this->context, $type);
                }
                return $type->nonTruthyClone();
            },
            $this->context
        );
    }

    /**
     * @param list<Node|string|int|float> $args
     */
    private function analyzeArrayKeyExistsNegation(array $args): Context
    {
        if (\count($args) !== 2) {
            return $this->context;
        }
        $var_node = $args[1];
        if (!($var_node instanceof Node)) {
            return $this->context;
        }
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $this->context,
            static function (UnionType $_): bool {
                return true;
            },
            function (UnionType $type) use ($args): UnionType {
                if ($type->hasTopLevelArrayShapeTypeInstances()) {
                    return $this->withNullOrUnsetArrayShapeTypes($type, $args[0], $this->context, true);
                }
                return $type;
            },
            true,
            false
        );
    }

    // TODO: empty, isset

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitInstanceof(Node $node): Context
    {
        //$this->checkVariablesDefined($node);
        // Only look at things of the form
        // `$variable instanceof ClassName`
        $expr_node = $node->children['expr'];
        $context = $this->context;
        if (!($expr_node instanceof Node)) {
            return $context;
        }
        $class_node = $node->children['class'];
        if (!($class_node instanceof Node)) {
            return $context;
        }
        if ($expr_node->kind !== ast\AST_VAR) {
            return $this->modifyComplexExpression(
                $expr_node,
                /**
                 * @param list<mixed> $args
                 * @suppress PhanUnusedClosureParameter
                 */
                function (CodeBase $code_base, Context $context, Variable $variable, array $args) use ($class_node): void {
                    $union_type = $this->computeNegatedInstanceofType($variable->getUnionType(), $class_node);
                    if ($union_type) {
                        $variable->setUnionType($union_type);
                    }
                },
                $context,
                []
            );
        }

        $code_base = $this->code_base;

        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($expr_node, $context);
            if (\is_null($variable)) {
                return $context;
            }

            // Get the type that we're checking it against
            $new_variable_type = $this->computeNegatedInstanceofType($variable->getUnionType(), $class_node);
            if (!$new_variable_type) {
                // We don't know what it asserted it wasn't.
                return $context;
            }

            // TODO: Assert that instanceof right-hand type is valid in NegatedConditionVisitor as well

            // Make a copy of the variable
            $variable = clone($variable);
            // See https://secure.php.net/instanceof -
            $variable->setUnionType($new_variable_type);

            // Overwrite the variable with its new type
            $context = $context->withScopeVariable(
                $variable
            );
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance($code_base, $context, $exception->getIssueInstance());
        } catch (\Exception $_) {
            // Swallow it
        }

        return $context;
    }

    /**
     * Compute the type of $union_type after asserting `!(expr instanceof $class_node)`
     * @param Node|string|int|float $class_node
     */
    private function computeNegatedInstanceofType(UnionType $union_type, $class_node): ?UnionType
    {
        $right_hand_union_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $class_node
        )->objectTypes();

        if ($right_hand_union_type->typeCount() !== 1) {
            return null;
        }
        $right_hand_type = $right_hand_union_type->getTypeSet()[0];
        if (!$right_hand_type->hasObjectWithKnownFQSEN()) {
            return null;
        }
        return $union_type->withoutSubclassesOf($this->code_base, $right_hand_type);
    }

    /*
    private function analyzeNegationOfVariableIsA(array $args, Context $context) : Context
    {
        // TODO: implement
        return $context;
    }
     */

    /**
     * @return array<string,Closure> (NegatedConditionVisitor $cv, Node $var_node, Context $context) -> Context
     * @phan-return array<string,Closure(CodeBase, Context, Variable, array):void>
     */
    private static function createNegationCallbackMap(): array
    {
        /** @param list<Node|mixed> $unused_args */
        $remove_null_cb = static function (CodeBase $unused_code_base, Context $unused_context, Variable $variable, array $unused_args): void {
            $variable->setUnionType($variable->getUnionType()->nonNullableClone());
        };

        // Remove any Types from UnionType that are subclasses of $base_class_name
        $make_basic_negated_assertion_callback = static function (string $base_class_name): Closure {
            /**
             * @param list<Node|mixed> $unused_args
             */
            return static function (CodeBase $unused_code_base, Context $unused_context, Variable $variable, array $unused_args) use ($base_class_name): void {
                $variable->setUnionType($variable->getUnionType()->asMappedListUnionType(/** @return list<Type> */ static function (Type $type) use ($base_class_name): array {
                    if ($type instanceof $base_class_name) {
                        // This is the type we don't want
                        if ($type->isNullable()) {
                            static $null_type_set;
                            return $null_type_set ?? ($null_type_set = UnionType::typeSetFromString('null'));
                        }
                        return [];
                    }
                    return [$type];
                })->asNormalizedTypes());
            };
        };
        $remove_float_callback = $make_basic_negated_assertion_callback(FloatType::class);
        $remove_int_callback = $make_basic_negated_assertion_callback(IntType::class);
        /**
         * @param Closure(Type):bool $type_filter
         * @return Closure(CodeBase, Context, Variable, array):void
         */
        $remove_conditional_function_callback = static function (Closure $type_filter): Closure {
            /**
             * @param list<Node|mixed> $unused_args
             */
            return static function (CodeBase $unused_code_base, Context $unused_context, Variable $variable, array $unused_args) use ($type_filter): void {
                $union_type = $variable->getUnionType();
                if (!$union_type->hasTypeMatchingCallback($type_filter)) {
                    return;
                }
                $new_type_builder = new UnionTypeBuilder();
                $has_null = false;
                $has_other_nullable_types = false;
                // Add types which are not scalars
                foreach ($union_type->getTypeSet() as $type) {
                    if ($type_filter($type)) {
                        // e.g. mixed|SomeClass can be null because mixed can be null.
                        $has_null = $has_null || $type->isNullable();
                        continue;
                    }
                    $has_other_nullable_types = $has_other_nullable_types || $type->isNullable();
                    $new_type_builder->addType($type);
                }
                // Add Null if some of the rejected types were were nullable, and none of the accepted types were nullable
                if ($has_null && !$has_other_nullable_types) {
                    $new_type_builder->addType(NullType::instance(false));
                }
                // TODO: Infer real type sets as well?
                $variable->setUnionType($new_type_builder->getPHPDocUnionType());
            };
        };
        $remove_scalar_callback = $remove_conditional_function_callback(static function (Type $type): bool {
            return $type instanceof ScalarType && !($type instanceof NullType);
        });
        $remove_numeric_callback = $remove_conditional_function_callback(static function (Type $type): bool {
            return $type instanceof IntType || $type instanceof FloatType;
        });
        $remove_bool_callback = $remove_conditional_function_callback(static function (Type $type): bool {
            return $type->isInBoolFamily();
        });
        /** @param list<Node|mixed> $unused_args */
        $remove_callable_callback = static function (CodeBase $code_base, Context $unused_context, Variable $variable, array $unused_args): void {
            $variable->setUnionType($variable->getUnionType()->asMappedListUnionType(/** @return list<Type> */ static function (Type $type) use ($code_base): array {
                if ($type->isCallable($code_base)) {
                    if ($type->isNullable()) {
                        static $null_type_set;
                        return $null_type_set ?? ($null_type_set = UnionType::typeSetFromString('null'));
                    }
                    return [];
                }
                return [$type];
            })->asNormalizedTypes());
        };
        // TODO: Would withStaticResolvedInContext make sense for ruling out self in Countable?
        /** @param list<Node|mixed> $unused_args */
        $remove_countable_callback = static function (CodeBase $code_base, Context $unused_context, Variable $variable, array $unused_args): void {
            $variable->setUnionType($variable->getUnionType()->asMappedListUnionType(/** @return list<Type> */ static function (Type $type) use ($code_base): array {
                if ($type->isCountable($code_base)) {
                    if ($type->isNullable()) {
                        static $null_type_set;
                        return $null_type_set ?? ($null_type_set = UnionType::typeSetFromString('null'));
                    }
                    return [];
                }
                return [$type];
            })->asNormalizedTypes());
        };
        /** @param list<Node|mixed> $unused_args */
        $zero_count_callback = static function (CodeBase $code_base, Context $context, Variable $variable, array $unused_args): void {
            $variable->setUnionType($variable->getUnionType()->asMappedListUnionType(/** @return list<Type> */ static function (Type $type) use ($code_base, $context): array {
                if ($type->isPossiblyObject()) {
                    if ($type->isObject()) {
                        return [IntersectionType::createFromTypes([$type, Type::countableInstance()], $code_base, $context)];
                    }
                    return [$type];
                }
                if (!$type->isPossiblyFalsey()) {
                    return [];
                }
                return [$type->asNonTruthyType()];
            })->asNormalizedTypes());
        };
        /** @param list<Node|mixed> $unused_args */
        $remove_array_callback = static function (CodeBase $unused_code_base, Context $unused_context, Variable $variable, array $unused_args): void {
            $union_type = $variable->getUnionType();
            $variable->setUnionType(UnionType::of(
                self::filterNonArrayTypes($union_type->getTypeSet()),
                self::filterNonArrayTypes($union_type->getRealTypeSet())
            ));
        };
        /** @param list<Node|mixed> $unused_args */
        $remove_list_callback = static function (CodeBase $unused_code_base, Context $unused_context, Variable $variable, array $unused_args): void {
            $union_type = $variable->getUnionType();
            $variable->setUnionType($union_type->arrayTypesStrictCast()->asNonEmptyAssociativeArrayTypes(GenericArrayType::KEY_MIXED));
        };
        /** @param list<Node|mixed> $unused_args */
        $remove_object_callback = static function (CodeBase $unused_code_base, Context $unused_context, Variable $variable, array $unused_args): void {
            $variable->setUnionType($variable->getUnionType()->asMappedListUnionType(/** @return list<Type> */ static function (Type $type): array {
                if ($type->isObject()) {
                    if ($type->isNullable()) {
                        static $null_type_set;
                        return $null_type_set ?? ($null_type_set = UnionType::typeSetFromString('null'));
                    }
                    return [];
                }

                if (\get_class($type) === IterableType::class) {
                    // An iterable that is not an array must be a Traversable
                    return [ArrayType::instance($type->isNullable())];
                }
                return [$type];
            })->asNormalizedTypes());
        };

        return [
            'array_is_list' => $remove_list_callback,
            'count' => $zero_count_callback,
            'is_null' => $remove_null_cb,
            'is_array' => $remove_array_callback,
            'is_bool' => $remove_bool_callback,
            'is_callable' => $remove_callable_callback,
            'is_countable' => $remove_countable_callback,
            'is_double' => $remove_float_callback,
            'is_float' => $remove_float_callback,
            'is_int' => $remove_int_callback,
            'is_integer' => $remove_int_callback,
            'is_iterable' => $make_basic_negated_assertion_callback(IterableType::class),  // TODO: Could keep basic array types and classes extending iterable
            'is_long' => $remove_int_callback,
            'is_numeric' => $remove_numeric_callback,
            'is_object' => $remove_object_callback,
            'is_real' => $remove_float_callback,
            'is_resource' => $make_basic_negated_assertion_callback(ResourceType::class),
            'is_scalar' => $remove_scalar_callback,
            'is_string' => $make_basic_negated_assertion_callback(StringType::class),
        ];
    }

    /**
     * @param list<Type> $type_set
     * @return list<Type> which may contain duplicates
     */
    private static function filterNonArrayTypes(array $type_set): array
    {
        $new_types = [];
        $has_null = false;
        $has_other_nullable_types = false;
        // Add types which are not arrays
        foreach ($type_set as $type) {
            if ($type instanceof ArrayType) {
                $has_null = $has_null || $type->isNullable();
                continue;
            }

            $has_other_nullable_types = $has_other_nullable_types || $type->isNullable();

            if ($type instanceof IterableType) {
                // An iterable that is not an object must be an array
                $has_null = $has_null || $type->isNullable();
                $new_type = $type->asObjectType();
                // should always be set
                if (!$new_type) {
                    throw new AssertionError("Expected non-array iterable to be able to cast to object");
                }
                $new_types[] = $new_type;
                continue;
            }
            $new_types[] = $type;
        }
        // Add Null if some of the rejected types were were nullable, and none of the accepted types were nullable
        if ($has_null && !$has_other_nullable_types) {
            $new_types[] = NullType::instance(false);
        }
        return $new_types;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIsset(Node $node): Context
    {
        $var_node = $node->children['var'];
        if (!($var_node instanceof Node)) {
            return $this->context;
        }
        if (($var_node->kind ?? null) !== ast\AST_VAR) {
            return $this->checkComplexIsset($var_node);
        }
        // if (!isset($x)) means that $x is definitely null
        return $this->updateVariableWithNewType($var_node, $this->context, NullType::instance(false)->asRealUnionType(), true, false);
    }

    /**
     * Analyze expressions such as $x['offset'] inside of a negated isset type check
     */
    public function checkComplexIsset(Node $var_node): Context
    {
        $context = $this->context;
        if ($var_node->kind === ast\AST_DIM) {
            $expr_node = $var_node;
            do {
                $parent_node = $expr_node;
                $expr_node = $expr_node->children['expr'];
                if (!($expr_node instanceof Node)) {
                    return $context;
                }
            } while ($expr_node->kind === ast\AST_DIM);

            if ($expr_node->kind === ast\AST_VAR) {
                $var_name = $expr_node->children['name'];
                if (!\is_string($var_name)) {
                    return $context;
                }
                if ($context->getScope()->hasVariableWithName($var_name)) {
                    $variable = $context->getScope()->getVariableByName($var_name);
                } else {
                    $new_type = Variable::getUnionTypeOfHardcodedVariableInScopeWithName($var_name, $context->isInGlobalScope());
                    if (!$new_type) {
                        // e.g. assert(!isset($x['key'])) - $x may still be undefined.
                        return $context;
                    }
                    $variable = new Variable(
                        $context->withLineNumberStart($var_node->lineno),
                        $var_name,
                        $new_type,
                        0
                    );
                    $context->getScope()->addVariable($variable);
                }
                $var_node_union_type = $variable->getUnionType();

                if ($var_node_union_type->hasTopLevelArrayShapeTypeInstances()) {
                    $new_union_type = $this->withNullOrUnsetArrayShapeTypes($var_node_union_type, $parent_node->children['dim'], $context, false);
                    if ($new_union_type !== $var_node_union_type) {
                        $variable = clone($variable);
                        $variable->setUnionType($new_union_type);
                        $context = $context->withScopeVariable($variable);
                    }
                    $this->context = $context;
                }
            }
        } elseif ($var_node->kind === ast\AST_PROP) {
            $context = $this->modifyPropertySimple($var_node, static function (UnionType $_): UnionType {
                return NullType::instance(false)->asPHPDocUnionType();
            }, $context);
        }
        return $context;
    }

    /**
     * @param UnionType $union_type the union type being modified by inferences from negated isset or array_key_exists
     * @param Node|string|float|int|bool $dim_node represents the dimension being accessed. (E.g. can be a literal or an AST_CONST, etc.
     * @param Context $context the context with inferences made prior to this condition
     */
    private function withNullOrUnsetArrayShapeTypes(UnionType $union_type, $dim_node, Context $context, bool $remove_offset): UnionType
    {
        $dim_value = $dim_node instanceof Node ? (new ContextNode($this->code_base, $context, $dim_node))->getEquivalentPHPScalarValue() : $dim_node;
        // TODO: detect and warn about null
        if (!\is_scalar($dim_value)) {
            return $union_type;
        }

        $dim_union_type = UnionTypeVisitor::resolveArrayShapeElementTypesForOffset($union_type, $dim_value, false, $this->code_base);
        if (!$dim_union_type) {
            // There are other types, this dimension does not exist yet.
            // Whether or not the union type already has array shape types, don't change the type
            return $union_type;
        }
        if ($remove_offset) {
            return $union_type->withoutArrayShapeField($dim_value);
        } else {
            static $null_and_possibly_undefined = null;
            if ($null_and_possibly_undefined === null) {
                $null_and_possibly_undefined = NullType::instance(false)->asPHPDocUnionType()->withIsPossiblyUndefined(true);
            }

            return ArrayType::combineArrayShapeTypesWithField($union_type, $dim_value, $null_and_possibly_undefined);
        }
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitEmpty(Node $node): Context
    {
        $context = $this->context;
        $var_node = $node->children['expr'];
        if (!($var_node instanceof Node)) {
            return $context;
        }
        // e.g. if (!empty($x))
        if ($var_node->kind === ast\AST_VAR) {
            // Don't check if variables are defined - don't emit notices for if (!empty($x)) {}, etc.
            $var_name = $var_node->children['name'];
            if (\is_string($var_name)) {
                if (!$context->getScope()->hasVariableWithName($var_name)) {
                    $new_type = Variable::getUnionTypeOfHardcodedVariableInScopeWithName($var_name, $context->isInGlobalScope());
                    if ($new_type) {
                        $new_type = $new_type->nonFalseyClone();
                    } else {
                        $new_type = UnionType::empty();
                    }
                    // Support analyzing cases such as `if (!empty($x)) { use($x); }`, or `assert(!empty($x))`
                    // (In the PHP language, empty($x) is equivalent to (!isset($x) || !$x))
                    $context->setScope($context->getScope()->withVariable(new Variable(
                        $context->withLineNumberStart($var_node->lineno ?? 0),
                        $var_name,
                        $new_type,
                        0
                    )));
                }
                return $this->removeFalseyFromVariable($var_node, $context, true);
            }
        } elseif ($var_node->kind === ast\AST_PROP) {
            // e.g. $var_node is the representation of $this->prop or $x->prop.
            $context = $this->removeFalseyFromVariable($var_node, $context, true);
            $expr = $var_node->children['expr'];
            if ($expr instanceof Node) {
                // Also imply $x is an object after !empty($x->prop)
                return $this->removeTypesNotSupportingAccessFromVariable($expr, $context, ConditionVisitor::ACCESS_IS_OBJECT);
            }
            return $context;
        } else {
            $context = $this->checkComplexNegatedEmpty($var_node);
        }
        $this->checkVariablesDefined($node);
        return $context;
    }

    private function checkComplexNegatedEmpty(Node $var_node): Context
    {
        $context = $this->context;
        // TODO: !empty($obj->prop['offset']) should imply $obj is not null (removeNullFromVariable)
        if ($var_node->kind === ast\AST_DIM) {
            $expr_node = $var_node;
            do {
                $parent_node = $expr_node;
                $expr_node = $expr_node->children['expr'];
                if (!($expr_node instanceof Node)) {
                    return $context;
                }
            } while ($expr_node->kind === ast\AST_DIM);

            if ($expr_node->kind === ast\AST_VAR) {
                $var_name = $expr_node->children['name'];
                if (!\is_string($var_name)) {
                    return $context;
                }
                if (!$context->getScope()->hasVariableWithName($var_name)) {
                    $new_type = Variable::getUnionTypeOfHardcodedVariableInScopeWithName($var_name, $context->isInGlobalScope());
                    if (!$new_type || !$new_type->hasArrayLike($this->code_base)) {
                        $new_type = ArrayType::instance(false)->asPHPDocUnionType();
                    }
                    $new_type = $new_type->nonFalseyClone();
                    // Support analyzing cases such as `if (!empty($x['key'])) { use($x); }`, or `assert(!empty($x['key']))`
                    // (Assume that this is an array, not ArrayAccess or a string, as a heuristic)
                    $context->setScope($context->getScope()->withVariable(new Variable(
                        $context->withLineNumberStart($expr_node->lineno ?? 0),
                        $var_name,
                        $new_type,
                        0
                    )));
                    return $context;
                }
                $context = $this->removeFalseyFromVariable($expr_node, $context, true);

                $variable = $context->getScope()->getVariableByName($var_name);
                $var_node_union_type = $variable->getUnionType();

                if ($var_node_union_type->hasTopLevelArrayShapeTypeInstances()) {
                    $context = $this->withNonFalseyArrayShapeTypes($variable, $parent_node->children['dim'], $context, true);
                }
                $this->context = $context;
            }
        }
        return $this->context;
    }

    /**
     * @param Variable $variable the variable being modified by inferences from !empty
     * @param Node|string|float|int|bool $dim_node represents the dimension being accessed. (E.g. can be a literal or an AST_CONST, etc.
     * @param Context $context the context with inferences made prior to this condition
     *
     * @param bool $non_nullable if an offset is created, will it be non-nullable?
     */
    private function withNonFalseyArrayShapeTypes(Variable $variable, $dim_node, Context $context, bool $non_nullable): Context
    {
        $dim_value = $dim_node instanceof Node ? (new ContextNode($this->code_base, $this->context, $dim_node))->getEquivalentPHPScalarValue() : $dim_node;
        // TODO: detect and warn about null
        if (!\is_scalar($dim_value)) {
            return $context;
        }

        $union_type = $variable->getUnionType();
        $dim_union_type = UnionTypeVisitor::resolveArrayShapeElementTypesForOffset($union_type, $dim_value, false, $this->code_base);
        if (!$dim_union_type) {
            // There are other types, this dimension does not exist yet
            if (!$union_type->hasTopLevelArrayShapeTypeInstances()) {
                return $context;
            }
            $new_union_type = ArrayType::combineArrayShapeTypesWithField($union_type, $dim_value, MixedType::instance(false)->asPHPDocUnionType());
            $variable = clone($variable);
            $variable->setUnionType($new_union_type);
            return $context->withScopeVariable(
                $variable
            );
            // TODO finish
        } elseif ($dim_union_type->containsNullableOrUndefined() || $dim_union_type->containsFalsey()) {
            if (!$non_nullable) {
                // The offset in question already exists in the array shape type, and we won't be changing it.
                // (E.g. array_key_exists('key', $x) where $x is array{key:?int,other:string})
                return $context;
            }

            $variable = clone($variable);

            $variable->setUnionType(
                ArrayType::combineArrayShapeTypesWithField($union_type, $dim_value, $dim_union_type->nonFalseyClone())
            );

            // Overwrite the variable with its new type in this
            // scope without overwriting other scopes
            return $context->withScopeVariable(
                $variable
            );
            // TODO finish
        }
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
    public function visitExprList(Node $node): Context
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

    /**
     * Useful for analyzing `if ($x = foo() && $x->method())`
     *
     * TODO: Convert $x to empty/false/null types
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAssign(Node $node): Context
    {
        $context = (new PostOrderAnalysisVisitor($this->code_base, $this->context, []))->visitAssign($node, true);
        $left = $node->children['var'];
        if (!($left instanceof Node)) {
            // Other code should warn about this invalid AST
            return $context;
        }
        if ($left->kind === ast\AST_ARRAY) {
            $expr_node = $node->children['expr'];
            if ($expr_node instanceof Node) {
                return (new self($this->code_base, $context))->__invoke($expr_node);
            }
            return $context;
        }
        return (new self($this->code_base, $context))->__invoke($left);
    }

    /**
     * Useful for analyzing `if ($x =& foo() && $x->method())`
     * TODO: Convert $x to empty/false/null types
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAssignRef(Node $node): Context
    {
        $context = (new BlockAnalysisVisitor($this->code_base, $this->context))->visitAssignRef($node);
        $left = $node->children['var'];
        if (!($left instanceof Node)) {
            // Other code should warn about this invalid AST
            return $context;
        }
        return (new self($this->code_base, $context))->__invoke($left);
    }
}
