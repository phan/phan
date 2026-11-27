<?php

declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use ast;
use ast\Node;
use Closure;
use Exception;
use Throwable;
use Phan\AST\AnalysisVisitor;
use Phan\AST\ASTReverter;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\RecursionDepthException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Flags;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Language\Element\Property;
use Phan\Language\Element\TypedElementInterface;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\AssociativeArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\CallableArrayType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\GenericArrayInterface;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\ListType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NonEmptyAssociativeArrayType;
use Phan\Language\Type\NonEmptyGenericArrayType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\StdClassShapeType;
use Phan\Language\UnionType;
use Phan\Library\StringUtil;

/**
 * Analyzes assignments.
 */
class AssignmentVisitor extends AnalysisVisitor
{
    /**
     * A flag of the assignment node that PostOrderAnalysisVisitor creates for the offset that a reference is taken to
     * (`$x = &$var['k']` is analyzed as `$var['k'] = $var['k']` first, to add the offset to the type of `$var`).
     * No value is assigned unless `$var` can be null, so there is nothing else to check against what `$var` is a reference to.
     */
    public const FLAG_CREATES_REFERENCE_TARGET = 1 << 26;

    /**
     * @var UnionType
     * The type of the element on the right side of the assignment
     */
    private $right_type;

    /**
     * @var int
     * Depth of array parameters in this assignment, e.g. this is
     * 1 for `$foo[3] = 42`, 0 for `$x = 2;`, etc.
     * We need to know this in order to decide
     * if we're replacing the union type
     * or if we're adding a type to the union type.
     * @phan-read-only
     */
    private $dim_depth;

    /**
     * @var ?UnionType
     * Non-null if this assignment is to an array parameter such as
     * in `$foo[3] = 42` (type would be int). We need to know this in order to decide
     * to type check the assignment (e.g. array keys are int|string, string offsets are int)
     * type to the union type.
     *
     * Null for `$foo[] = 42` or when dim_depth is 0
     * @phan-read-only
     */
    private $dim_type;

    /**
     * @var Node
     */
    private $assignment_node;

    /** @var bool suppress property mismatch warnings when refining nested fields */
    private $suppress_dim_property_mismatch;

    /**
     * @var bool true if this is a conditional check (isset/array_key_exists) rather than an actual assignment
     * When true, skip read-only property checks since we're only inferring that a field exists, not modifying it.
     */
    private $is_conditional_check;

    /**
     * @var bool true if this assignment comes from a ??= operator.
     * For non-nullable readonly properties, ??= is safe: if the property is uninitialized it assigns once,
     * and if already set (non-null) the coalescing short-circuits and no assignment occurs.
     */
    private $is_coalesce_assign;

    /**
     * @var bool
     * True if this is the assignment of a property by `clone($obj, ['prop' => $value])` (php 8.5+)
     */
    private $is_clone_with_properties;

    /**
     * @param CodeBase $code_base
     * The global code base we're operating within
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param Node $assignment_node
     * The AST node containing the assignment
     *
     * @param UnionType $right_type
     * The type of the element on the right side of the assignment
     *
     * @param int $dim_depth
     * Positive if this assignment is to an array parameter such as
     * in `$foo[3] = 42`. We need to know this in order to decide
     * if we're replacing the union type or if we're adding a
     * type to the union type.
     *
     * @param ?UnionType $dim_type
     * The type of the dimension.
     *
     * @param bool $is_conditional_check
     * True if this is being used for conditional type inference (isset/array_key_exists)
     * rather than an actual assignment. Skips read-only property checks.
     *
     * @param bool $is_coalesce_assign
     * True if this assignment comes from a ??= operator. Allows writes to non-nullable
     * native readonly properties within the declaring class scope, since ??= short-circuits
     * if the property is already set to a non-null value.
     *
     * @param bool $is_clone_with_properties
     * True if this is the assignment of a property by `clone($obj, ['prop' => $value])` (php 8.5+).
     * That initializes the property of the new object, so a readonly property can be assigned outside of the constructor.
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        Node $assignment_node,
        UnionType $right_type,
        int $dim_depth = 0,
        ?UnionType $dim_type = null,
        bool $suppress_dim_property_mismatch = false,
        bool $is_conditional_check = false,
        bool $is_coalesce_assign = false,
        bool $is_clone_with_properties = false
    ) {
        parent::__construct($code_base, $context);

        $this->right_type = $right_type->withSelfResolvedInContext($context)->convertUndefinedToNullable();
        $this->dim_depth = $dim_depth;
        $this->dim_type = $dim_type;  // null for `$x[] =` or when dim_depth is 0.
        $this->assignment_node = $assignment_node;
        $this->suppress_dim_property_mismatch = $suppress_dim_property_mismatch;
        $this->is_conditional_check = $is_conditional_check;
        $this->is_coalesce_assign = $is_coalesce_assign;
        $this->is_clone_with_properties = $is_clone_with_properties;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * A node to analyze as the target of an assignment
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the node
     *
     * @throws UnanalyzableException
     */
    public function visit(Node $node): Context
    {
        // TODO: Add more details.
        // This should only happen when the polyfill parser is used on invalid ASTs
        $this->emitIssue(
            Issue::Unanalyzable,
            $node->lineno
        );
        return $this->context;
    }

    // TODO visitNullsafeMethodCall should not be possible on the left hand side?

    /**
     * The following is an example of how this would happen.
     * (TODO: Check if the right-hand side is an object with offsetSet() or a reference?
     *
     * ```php
     * class C {
     *     function f() {
     *         return [ 24 ];
     *     }
     * }
     * (new C)->f()[1] = 42;
     * ```
     *
     * @param Node $node
     * A node to analyze as the target of an assignment
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the node
     *
     * @throws UnanalyzableException for first-class callable conversion
     */
    public function visitMethodCall(Node $node): Context
    {
        if ($node->children['args']->kind === ast\AST_CALLABLE_CONVERT) {
            // Warn about this being unanalyzable
            return $this->visit($node);
        }
        if ($this->dim_depth >= 2) {
            return $this->context;
        }
        $method_name = $node->children['method'];

        if (!\is_string($method_name)) {
            if ($method_name instanceof Node) {
                $method_name = UnionTypeVisitor::anyStringLiteralForNode($this->code_base, $this->context, $method_name);
            }
            if (!\is_string($method_name)) {
                return $this->context;
            }
        }

        try {
            $method = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethod($method_name, false, true);
            $this->checkAssignmentToFunctionResult($node, [$method]);
        } catch (Exception) {
            // ignore it
        }
        return $this->context;
    }

    /**
     * The following is an example of how this would happen.
     *
     * This checks if the left-hand side is a reference.
     *
     * PhanTypeArraySuspicious covers checking for offsetSet.
     *
     * ```php
     * function &f() {
     *     $x = [ 24 ]; return $x;
     * }
     * f()[1] = 42;
     * ```
     *
     * @param Node $node
     * A node to analyze as the target of an assignment
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the node
     *
     * @throws UnanalyzableException for first-class callable conversion
     */
    public function visitCall(Node $node): Context
    {
        if ($node->children['args']->kind === ast\AST_CALLABLE_CONVERT) {
            // Warn about this being unanalyzable
            return $this->visit($node);
        }
        // TODO: Warn about first-class callable conversion
        $expression = $node->children['expr'];
        if ($this->dim_depth < 2) {
            // Get the function.
            // If the function is undefined, always try to create a placeholder from Phan's type signatures for internal functions so they can still be type checked.
            $this->checkAssignmentToFunctionResult($node, (new ContextNode(
                $this->code_base,
                $this->context,
                $expression
            ))->getFunctionFromNode(true));
        }
        return $this->context;
    }

    /**
     * @param iterable<FunctionInterface> $function_list_generator
     */
    private function checkAssignmentToFunctionResult(Node $node, iterable $function_list_generator): void
    {
        try {
            foreach ($function_list_generator as $function) {
                if ($function->returnsRef()) {
                    return;
                }
                if ($this->dim_depth > 0) {
                    $return_type = $function->getUnionType();
                    if ($return_type->isEmpty()) {
                        return;
                    }
                    if ($return_type->hasPossiblyObjectTypes()) {
                        // PhanTypeArraySuspicious covers that, though
                        return;
                    }
                }
            }
            if (isset($function)) {
                $this->emitIssue(
                    Issue::TypeInvalidCallExpressionAssignment,
                    $node->lineno,
                    ASTReverter::toShortString($this->assignment_node->children['var'] ?? $node),
                    $function->getUnionType()
                );
            }
        } catch (CodeBaseException) {
            // ignore it.
        }
    }

    /**
     * The following is an example of how this would happen.
     *
     * ```php
     * class A{
     *     function &f() {
     *         $x = [ 24 ]; return $x;
     *     }
     * }
     * A::f()[1] = 42;
     * ```
     *
     * @param Node $node
     * A node to analyze as the target of an assignment
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the node
     *
     * @throws UnanalyzableException for first-class callable conversion
     */
    public function visitStaticCall(Node $node): Context
    {
        return $this->visitMethodCall($node);
    }

    /**
     * `$c?->m() = 1` is as invalid as `$c->m() = 1`
     *
     * @param Node $node a node of kind ast\AST_NULLSAFE_METHOD_CALL
     *
     * @throws UnanalyzableException for first-class callable conversion
     */
    public function visitNullsafeMethodCall(Node $node): Context
    {
        return $this->visitMethodCall($node);
    }

    /**
     * This happens for code like the following
     * ```
     * list($a) = [1, 2, 3];
     * ```
     *
     * @param Node $node
     * A node to analyze as the target of an assignment
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the node
     */
    public function visitArray(Node $node): Context
    {
        $this->checkValidArrayDestructuring($node);
        if ($this->right_type->hasTopLevelArrayShapeTypeInstances()) {
            $this->analyzeShapedArrayAssignment($node);
        } else {
            // common case
            $this->analyzeGenericArrayAssignment($node);
        }
        return $this->context;
    }

    private function checkValidArrayDestructuring(Node $node): void
    {
        if (!$node->children) {
            $this->emitIssue(
                Issue::SyntaxEmptyListArrayDestructuring,
                $node->lineno
            );
            return;
        }
        $bitmask = 0;
        foreach ($node->children as $c) {
            // When $c is null, it's the same as an array entry without a key for purposes of warning.
            $bitmask |= (isset($c->children['key']) ? 1 : 2);
            if ($bitmask === 3) {
                $this->emitIssue(
                    Issue::SyntaxMixedKeyNoKeyArrayDestructuring,
                    $c->lineno ?? $node->lineno,
                    ASTReverter::toShortString($node)
                );
                return;
            }
        }
    }

    /**
     * Analyzes code such as list($a) = [1, 2, 3];
     * @see self::visitArray()
     */
    private function analyzeShapedArrayAssignment(Node $node): void
    {
        // Figure out the type of elements in the list
        $fallback_element_type = null;
        $get_fallback_element_type = function () use (&$fallback_element_type): UnionType {
            return $fallback_element_type ?? ($fallback_element_type = (
                $this->right_type->genericArrayElementTypes(false, $this->code_base)
                                 ->withRealTypeSet(UnionType::computeRealElementTypesForDestructuringAccess($this->right_type->getRealTypeSet(), $this->code_base))));
        };

        $expect_string_keys_lineno = false;
        $expect_int_keys_lineno = false;

        $key_set = [];

        foreach ($node->children ?? [] as $child_node) {
            // Some times folks like to pass a null to
            // a list to throw the element away. I'm not
            // here to judge.
            if (!($child_node instanceof Node)) {
                // Track the element that was thrown away.
                $key_set[] = true;
                continue;
            }

            if ($child_node->kind !== ast\AST_ARRAY_ELEM) {
                $this->emitIssue(
                    Issue::InvalidNode,
                    $child_node->lineno,
                    "Spread operator is not supported in assignments"
                );
                continue;
            }
            // Get the key and value nodes for each
            // array element we're assigning to
            // TODO: Check key types are valid?
            $key_node = $child_node->children['key'];

            if ($key_node === null) {
                $key_set[] = true;
                \end($key_set);
                $key_value = \key($key_set);

                $expect_int_keys_lineno = $child_node->lineno;  // list($x, $y) = ... is equivalent to list(0 => $x, 1 => $y) = ...
            } else {
                if ($key_node instanceof Node) {
                    $key_value = (new ContextNode($this->code_base, $this->context, $key_node))->getEquivalentPHPScalarValue();
                } else {
                    $key_value = $key_node;
                }
                if (\is_scalar($key_value)) {
                    if (\is_float($key_value)) {
                        // PHP truncates float keys (with a deprecation notice that Phan should not emit itself)
                        $key_value = (int)$key_value;
                    }
                    $key_set[$key_value] = true;
                    if (\is_int($key_value)) {
                        $expect_int_keys_lineno = $child_node->lineno;
                    } elseif (\is_string($key_value)) {
                        $expect_string_keys_lineno = $child_node->lineno;
                    }
                } else {
                    $key_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $key_node);
                    $key_type_enum = GenericArrayType::keyTypeFromUnionTypeValues($key_type);
                    // TODO: Warn about types that can't cast to int|string
                    if ($key_type_enum === GenericArrayType::KEY_INT) {
                        $expect_int_keys_lineno = $child_node->lineno;
                    } elseif ($key_type_enum === GenericArrayType::KEY_STRING) {
                        $expect_string_keys_lineno = $child_node->lineno;
                    }
                }
            }

            if (\is_scalar($key_value)) {
                $element_type = UnionTypeVisitor::resolveArrayShapeElementTypesForOffset($this->right_type, $key_value, false, $this->code_base);
                if ($element_type === null) {
                    $element_type = $get_fallback_element_type();
                } elseif ($element_type === false) {
                    $this->emitIssue(
                        Issue::TypeInvalidDimOffsetArrayDestructuring,
                        $child_node->lineno,
                        StringUtil::jsonEncode($key_value),
                        ASTReverter::toShortString($child_node),
                        (string)$this->right_type
                    );
                    $element_type = $get_fallback_element_type();
                } else {
                    // At this point, $element_type must be UnionType (null and false cases handled above)
                    if (!($element_type instanceof UnionType)) {
                        // This should not happen, but handle it safely
                        $element_type = $get_fallback_element_type();
                    } elseif ($element_type->hasRealTypeSet()) {
                        $element_type = self::withComputedRealUnionType($element_type, $this->right_type, function (UnionType $new_right_type) use ($key_value): UnionType {
                            $result = UnionTypeVisitor::resolveArrayShapeElementTypesForOffset($new_right_type, $key_value, false, $this->code_base);
                            return $result instanceof UnionType ? $result : UnionType::empty();
                        });
                    }
                }
            } else {
                $element_type = $get_fallback_element_type();
            }

            '@phan-var UnionType $element_type';
            $this->analyzeValueNodeOfShapedArray($element_type, $child_node->children['value']);
        }

        if (!Config::getValue('scalar_array_key_cast')) {
            $this->checkMismatchArrayDestructuringKey($expect_int_keys_lineno, $expect_string_keys_lineno);
        }
    }

    /**
     * Utility function to compute accurate real union types
     *
     * TODO: Move this into a common class such as UnionType?
     * @param Closure(UnionType):UnionType $recompute_inferred_type
     */
    private static function withComputedRealUnionType(UnionType $inferred_type, UnionType $source_type, Closure $recompute_inferred_type): UnionType
    {
        if (!$inferred_type->hasRealTypeSet()) {
            return $inferred_type;
        }
        if ($source_type->getRealTypeSet() === $source_type->getTypeSet()) {
            return $inferred_type;
        }
        $real_inferred_type = $recompute_inferred_type($inferred_type->getRealUnionType());
        return $inferred_type->withRealTypeSet($real_inferred_type->getTypeSet());
    }

    /**
     * Returns true if $node is a nullsafe chain, i.e. a variable expression that PHP can short-circuit.
     * PHP refuses to compile a write (unset, assignment, foreach target, increment/decrement) to such a chain,
     * or a reference to it (`$x = &$c?->p`), so this mirrors zend_ast_is_short_circuited() in zend_compile.c.
     *
     * @param bool $is_write_target true for the target of a write, false for the source of a reference assignment.
     */
    public static function isShortCircuitedChain(Node $node, bool $is_write_target = true): bool
    {
        if ($is_write_target) {
            switch ($node->kind) {
                case \ast\AST_METHOD_CALL:
                case \ast\AST_NULLSAFE_METHOD_CALL:
                case \ast\AST_STATIC_CALL:
                    // `$c?->m() = 1` fails for a different reason ("Can't use method return value in write context"),
                    // and would still fail with `->`. Leave that to the existing call-target analysis.
                    return false;
            }
        }
        while (true) {
            switch ($node->kind) {
                case \ast\AST_NULLSAFE_PROP:
                case \ast\AST_NULLSAFE_METHOD_CALL:
                    return true;
                case \ast\AST_DIM:
                case \ast\AST_PROP:
                case \ast\AST_METHOD_CALL:
                    $node = $node->children['expr'];
                    break;
                case \ast\AST_STATIC_PROP:
                case \ast\AST_STATIC_CALL:
                    $node = $node->children['class'];
                    break;
                case \ast\AST_REF:
                    // foreach ($x as &$c?->p)
                    $node = $node->children['var'];
                    break;
                default:
                    return false;
            }
            if (!$node instanceof Node) {
                return false;
            }
        }
    }

    /**
     * Emits PhanInvalidWriteToNullsafeChain if $node (the target of a write) is a nullsafe chain,
     * which PHP refuses to compile ("Can't use nullsafe operator in write context").
     *
     * @param Node $node the target of unset(), an assignment, a foreach key/value, or an increment/decrement
     * @return bool true if the issue was emitted and the write should not be analyzed further
     */
    public static function checkWriteToNullsafeChain(CodeBase $code_base, Context $context, Node $node): bool
    {
        if (!self::isShortCircuitedChain($node)) {
            return false;
        }
        $target = $node->kind === \ast\AST_REF ? $node->children['var'] : $node;
        Issue::maybeEmit(
            $code_base,
            $context,
            Issue::InvalidWriteToNullsafeChain,
            $node->lineno,
            ASTReverter::toShortString($target)
        );
        return true;
    }

    private function analyzeValueNodeOfShapedArray(
        UnionType $element_type,
        Node|float|int|string $value_node
    ): void {
        if (!$value_node instanceof Node) {
            return;
        }
        $kind = $value_node->kind;
        if ($kind === \ast\AST_REF) {
            $value_node = $value_node->children['expr'];
            if (!$value_node instanceof Node) {
                return;
            }
            // TODO: Infer that this is creating or copying a reference [&$a] = [&$b]
        }
        if (self::checkWriteToNullsafeChain($this->code_base, $this->context, $value_node)) {
            return;
        }
        if ($kind === \ast\AST_VAR) {
            $variable = Variable::fromNodeInContext(
                $value_node,
                $this->context,
                $this->code_base,
                false
            );

            // Set the element type on each element of
            // the list
            $this->analyzeSetUnionType($variable, $element_type, $value_node);

            // Note that we're not creating a new scope, just
            // adding variables to the existing scope
            $this->context->addScopeVariable($variable);
        } elseif ($kind === \ast\AST_PROP) {
            try {
                $property = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $value_node
                ))->getProperty(false, true);

                $this->analyzeAssignmentToGetOnlyVirtualProperty($property, $value_node);

                // Set the element type on each element of
                // the list
                $this->analyzeSetUnionType($property, $element_type, $value_node);
            } catch (UnanalyzableException | NodeException) {
                // Ignore it. There's nothing we can do.
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
                return;
            }
        } else {
            $this->context = (new AssignmentVisitor(
                $this->code_base,
                $this->context,
                $value_node,
                $element_type,
                0
            ))->__invoke($value_node);
        }
    }  // TODO: Warn if $value_node is not a node. NativeSyntaxCheckPlugin already does this.

    /**
     * Set the element's union type.
     * This should be used for warning about assignments such as `$leftHandSide = $str`, but not `is_string($var)`,
     * when typed properties could be used.
     *
     * @param Node|string|int|float|null $node
     */
    private function analyzeSetUnionType(
        TypedElementInterface $element,
        UnionType $element_type,
        Node|float|int|null|string $node
    ): void {
        // Let the caller warn about possibly undefined offsets, e.g. ['field' => $value] = ...
        // TODO: Convert real types to nullable?
        $element_type = $element_type->withIsPossiblyUndefined(false);

        // If this variable is involved in a reference assignment, erase literal types
        // to avoid incorrect literal type tracking (issue #4354)
        if ($element instanceof Variable && $element->getPhanFlagsHasState(Flags::HAS_REFERENCE)) {
            $element_type = $element_type->asNonLiteralType();
        }

        if ($this->assignment_node->flags & self::FLAG_CREATES_REFERENCE_TARGET) {
            // The reference only assigns something if it turns null (or an undefined variable or offset) into an array:
            // $var of `&$var['k']`, and also $var['k'] of `&$var['k']['j']`.
            $node = $this->assignment_node->children['var'];
            $is_assignment = $element->getUnionType()->containsNullableOrUndefined() || !($node instanceof Node) || $this->hasNullableContainerOffset($node);
        } else {
            $is_assignment = true;
        }
        $element->setUnionType($element_type);
        if ($is_assignment && $element instanceof PassByReferenceVariable) {
            $assign_node = new Node(ast\AST_ASSIGN, 0, ['expr' => $node], $node->lineno ?? $this->assignment_node->lineno);
            self::analyzeSetUnionTypePassByRef($this->code_base, $this->context, $element, $element_type, $assign_node);
        }
    }

    /**
     * @param Node $node a node of kind ast\AST_DIM
     * @return bool whether one of the offsets that $node is an offset of can be null, undefined or of an unknown type:
     * $var['k'] for $var['k']['j']. (False for $var['k']: the variable is not checked here)
     */
    private function hasNullableContainerOffset(Node $node): bool
    {
        $was_discarding_issues = Issue::$is_discarding_issues;
        Issue::$is_discarding_issues = true;
        try {
            for ($container = $node->children['expr']; $container instanceof Node && $container->kind === ast\AST_DIM; $container = $container->children['expr']) {
                $type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $container);
                if ($type->isEmpty() || $type->containsNullableOrUndefined()) {
                    return true;
                }
            }
        } catch (\Exception) {
            return true;
        } finally {
            Issue::$is_discarding_issues = $was_discarding_issues;
        }
        return false;
    }

    /**
     * Set the element's union type.
     * This should be used for warning about assignments such as `$leftHandSide = $str`, but not `is_string($var)`,
     * when typed properties could be used.
     *
     * Static version of analyzeSetUnionType
     */
    public static function analyzeSetUnionTypeInContext(
        CodeBase $code_base,
        Context $context,
        TypedElementInterface $element,
        UnionType $element_type,
        Node|float|int|string $node
    ): void {
        // If this variable is involved in a reference assignment, erase literal types
        // to avoid incorrect literal type tracking (issue #4354)
        if ($element instanceof Variable && $element->getPhanFlagsHasState(Flags::HAS_REFERENCE)) {
            $element_type = $element_type->asNonLiteralType();
        }

        $element->setUnionType($element_type);
        if ($element instanceof PassByReferenceVariable) {
            self::analyzeSetUnionTypePassByRef(
                $code_base,
                $context,
                $element,
                $element_type,
                new Node(ast\AST_ASSIGN, 0, ['expr' => $node], $node->lineno ?? $context->getLineNumberStart())
            );
        }
    }

    /**
     * Set the reference element's union type.
     * This should be used for warning about assignments such as `$leftHandSideRef = $str`, but not `is_string($varRef)`,
     * when typed properties could be used.
     *
     * @param Node|string|int|float $node the assignment expression
     */
    private static function analyzeSetUnionTypePassByRef(
        CodeBase $code_base,
        Context $context,
        PassByReferenceVariable $reference_element,
        UnionType $new_type,
        Node|float|int|string $node
    ): void {
        $element = $reference_element->getElement();
        while ($element instanceof PassByReferenceVariable) {
            $reference_element = $element;
            $element = $element->getElement();
        }
        if ($element instanceof Property) {
            $real_union_type = $element->getRealUnionType();
            if (!$real_union_type->isEmpty() && !$new_type->getRealUnionType()->canCastToDeclaredType($code_base, $context, $real_union_type)) {
                $reference_context = $reference_element->getContextOfCreatedReference();
                if ($reference_context) {
                    // Here, we emit the issue at the place where the reference was created,
                    // since that's the code that can be changed or where issues should be suppressed.
                    Issue::maybeEmit(
                        $code_base,
                        $reference_context,
                        Issue::TypeMismatchPropertyRealByRef,
                        $reference_context->getLineNumberStart(),
                        isset($node->children['expr']) ? ASTReverter::toShortString($node->children['expr']) : '(unknown)',
                        $new_type,
                        $element->getRepresentationForIssue(),
                        $real_union_type,
                        $context->getFile(),
                        $node->lineno ?? $context->getLineNumberStart()
                    );
                }
                return;
            }
            if (!$new_type->canCastToUnionType($element->getPHPDocUnionType(), $code_base)) {
                $reference_context = $reference_element->getContextOfCreatedReference();
                if ($reference_context) {
                    Issue::maybeEmit(
                        $code_base,
                        $reference_context,
                        Issue::TypeMismatchPropertyByRef,
                        $reference_context->getLineNumberStart(),
                        isset($node->children['expr']) ? ASTReverter::toShortString($node->children['expr']) : '(unknown)',
                        $new_type,
                        $element->getRepresentationForIssue(),
                        $element->getPHPDocUnionType(),
                        $context->getFile(),
                        $node->lineno ?? $context->getLineNumberStart()
                    );
                }
            }
        }
    }

    /**
     * Analyzes code such as list($a) = function_returning_array();
     * @param Node $node the ast\AST_ARRAY node on the left hand side of the assignment
     * @see self::visitArray()
     */
    private function analyzeGenericArrayAssignment(Node $node): void
    {
        // Figure out the type of elements in the list
        $right_type = $this->right_type;
        if ($right_type->isEmpty()) {
            $element_type = UnionType::empty();
        } else {
            $array_access_types = $right_type->asArrayOrArrayAccessSubTypes($this->code_base);
            if ($array_access_types->isEmpty()) {
                $this->emitIssue(
                    Issue::TypeInvalidExpressionArrayDestructuring,
                    $node->lineno,
                    $this->getAssignedExpressionString(),
                    $right_type,
                    'array|ArrayAccess'
                );
            }
            $element_type =
                $array_access_types->genericArrayElementTypes(false, $this->code_base)
                                   ->withRealTypeSet(UnionType::computeRealElementTypesForDestructuringAccess($right_type->getRealTypeSet(), $this->code_base));
        }

        $expect_string_keys_lineno = false;
        $expect_int_keys_lineno = false;

        $scalar_array_key_cast = Config::getValue('scalar_array_key_cast');

        foreach ($node->children ?? [] as $child_node) {
            // Some times folks like to pass a null to
            // a list to throw the element away. I'm not
            // here to judge.
            if (!($child_node instanceof Node)) {
                continue;
            }
            if ($child_node->kind !== ast\AST_ARRAY_ELEM) {
                $this->emitIssue(
                    Issue::InvalidNode,
                    $child_node->lineno,
                    "Spread operator is not supported in assignments"
                );
                continue;
            }

            // Get the key and value nodes for each
            // array element we're assigning to
            // TODO: Check key types are valid?
            $key_node = $child_node->children['key'];
            if (!$scalar_array_key_cast) {
                if ($key_node === null) {
                    $expect_int_keys_lineno = $child_node->lineno;  // list($x, $y) = ... is equivalent to list(0 => $x, 1 => $y) = ...
                } else {
                    $key_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $key_node);
                    $key_type_enum = GenericArrayType::keyTypeFromUnionTypeValues($key_type);
                    // TODO: Warn about types that can't cast to int|string
                    if ($key_type_enum === GenericArrayType::KEY_INT) {
                        $expect_int_keys_lineno = $child_node->lineno;
                    } elseif ($key_type_enum === GenericArrayType::KEY_STRING) {
                        $expect_string_keys_lineno = $child_node->lineno;
                    }
                }
            }

            $value_node = $child_node->children['value'];
            if (!($value_node instanceof Node)) {
                // Skip non-nodes to avoid crash
                // TODO: Emit a new issue type for https://github.com/phan/phan/issues/1693
            } elseif (self::checkWriteToNullsafeChain($this->code_base, $this->context, $value_node)) {
                // Already warned, nothing to assign to.
            } elseif ($value_node->kind === \ast\AST_VAR) {
                $variable = Variable::fromNodeInContext(
                    $value_node,
                    $this->context,
                    $this->code_base,
                    false
                );

                // Set the element type on each element of
                // the list
                $this->analyzeSetUnionType($variable, $element_type, $value_node);

                // Note that we're not creating a new scope, just
                // adding variables to the existing scope
                $this->context->addScopeVariable($variable);
            } elseif ($value_node->kind === \ast\AST_PROP) {
                try {
                    $property = (new ContextNode(
                        $this->code_base,
                        $this->context,
                        $value_node
                    ))->getProperty(false, true);

                    $this->analyzeAssignmentToGetOnlyVirtualProperty($property, $value_node);

                    // Set the element type on each element of
                    // the list
                    $this->analyzeSetUnionType($property, $element_type, $value_node);
                } catch (UnanalyzableException | NodeException) {
                    // Ignore it. There's nothing we can do.
                } catch (IssueException $exception) {
                    Issue::maybeEmitInstance(
                        $this->code_base,
                        $this->context,
                        $exception->getIssueInstance()
                    );
                    continue;
                }
            } else {
                $this->context = (new AssignmentVisitor(
                    $this->code_base,
                    $this->context,
                    $value_node,
                    $element_type,
                    0
                ))->__invoke($value_node);
            }
        }

        $this->checkMismatchArrayDestructuringKey($expect_int_keys_lineno, $expect_string_keys_lineno);
    }

    /**
     * @param int|false $expect_int_keys_lineno
     * @param int|false $expect_string_keys_lineno
     */
    private function checkMismatchArrayDestructuringKey(bool|int $expect_int_keys_lineno, bool|int $expect_string_keys_lineno): void
    {
        if ($expect_int_keys_lineno !== false || $expect_string_keys_lineno !== false) {
            $right_hand_key_type = GenericArrayType::keyTypeFromUnionTypeKeys($this->right_type);
            if ($expect_int_keys_lineno !== false && ($right_hand_key_type & GenericArrayType::KEY_INT) === 0) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    Issue::TypeMismatchArrayDestructuringKey,
                    $expect_int_keys_lineno,
                    'int',
                    'string'
                );
            } elseif ($expect_string_keys_lineno !== false && ($right_hand_key_type & GenericArrayType::KEY_STRING) === 0) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    Issue::TypeMismatchArrayDestructuringKey,
                    $expect_string_keys_lineno,
                    'string',
                    'int'
                );
            }
        }
    }

    /**
     * @param Node $node
     * A node to analyze as the target of an assignment
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the node
     */
    public function visitDim(Node $node): Context
    {
        $expr_node = $node->children['expr'];
        if (!($expr_node instanceof Node)) {
            $this->emitIssue(
                Issue::InvalidWriteToTemporaryExpression,
                $node->lineno,
                ASTReverter::toShortString($node),
                Type::fromObject($expr_node)
            );
            return $this->context;
        }
        $loop_assignment_var_name = null;
        if ($expr_node->kind === \ast\AST_VAR) {
            $variable_name = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getVariableName();
            $loop_assignment_var_name = $variable_name;
            if (Variable::isHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope())) {
                if ($variable_name === 'GLOBALS') {
                    return $this->analyzeSuperglobalDim($node, $variable_name);
                }
                if (!$this->context->getScope()->hasVariableWithName($variable_name)) {
                    $this->context->addScopeVariable(new Variable(
                        $this->context->withLineNumberStart($expr_node->lineno),
                        $variable_name,
                        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable
                        Variable::getUnionTypeOfHardcodedGlobalVariableWithName($variable_name),
                        0
                    ));
                }
            }
        }

        // TODO: Check if the unionType is valid for the []
        // For most types, it should be int|string, but SplObjectStorage and a few user-defined types will be exceptions.
        // Infer it from offsetSet?
        $dim_node = $node->children['dim'];
        if ($dim_node instanceof Node) {
            // TODO: Use ContextNode to infer dim_value
            $dim_type = UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $dim_node
            );
            $dim_value = $dim_type->asSingleScalarValueOrNullOrSelf();
        } elseif (\is_scalar($dim_node)) {
            $dim_value = self::normalizeScalarArrayKey($dim_node);
            $dim_type = Type::fromObject($dim_value)->asRealUnionType();
        } else {
            // TODO: If the array shape has only one set of keys, then appending should add to that shape? Possibly not a common use case.
            $dim_type = null;
            $dim_value = null;
        }

        if ($dim_type !== null && !\is_object($dim_value)) {
            // TODO: This is probably why Phan has bugs with multi-dimensional assignment adding new union types instead of combining with existing ones.
            $right_type = ArrayShapeType::fromFieldTypes([
                $dim_value => $this->right_type,
            ], false)->asRealUnionType();
            if ($expr_node->kind === ast\AST_DIM) {
                if ($this->is_conditional_check) {
                    // A condition such as `if ($x['a']['b'])` proves only that $x['a'] has the key 'b'.
                    // If the other keys of $x['a'] are unknown, keep them possible, so that reading $x['a']['c'] is not an invalid offset.
                    $right_type = $this->withConditionalDimContainerFallback($expr_node, $right_type);
                } else {
                    // A write such as `$x['a']['b'] = 1` does not remove the other keys of $x['a'].
                    $right_type = $this->withWrittenDimContainerFallback($expr_node, $right_type);
                }
            }
        } else {
            // Make the right type a generic (i.e. int -> int[])
            if ($dim_node !== null) {
                if ($dim_type !== null) {
                    $key_type_enum = GenericArrayType::keyTypeFromUnionTypeValues($dim_type);
                } else {
                    $key_type_enum = GenericArrayType::KEY_MIXED;
                }
                $right_inner_type = $this->right_type;
                if ($right_inner_type->isEmpty()) {
                    $right_type = GenericArrayType::fromElementType(MixedType::instance(false), false, $key_type_enum)->asRealUnionType();
                } else {
                    $right_type = $right_inner_type->asGenericArrayTypes($key_type_enum);
                }
            } else {
                $right_type = $this->right_type->asListTypes();
                if ($right_type->isEmpty()) {
                    $right_type = ListType::fromElementType(MixedType::instance(false), false)->asPHPDocUnionType();
                }
                if (!$this->context->isInLoop() && !$right_type->hasRealTypeSet()) {
                    $real_type_set = $right_type->getTypeSet();
                    if (!$real_type_set) {
                        $real_type_set = ListType::fromElementType(MixedType::instance(false), false)->asRealUnionType()->getTypeSet();
                    }
                    $right_type = $right_type->withRealTypeSet($real_type_set);
                }
            }
            if ($dim_node !== null && !$right_type->hasRealTypeSet()) {
                $right_type = $right_type->withRealTypeSet(UnionType::typeSetFromString('non-empty-array'));
            }
        }

        // Recurse into whatever we're []'ing
        $context = (new AssignmentVisitor(
            $this->code_base,
            $this->context,
            $this->assignment_node,
            $right_type,
            $this->dim_depth + 1,
            $dim_type,
            $this->suppress_dim_property_mismatch,
            $this->is_conditional_check  // Propagate the flag for nested dimensions
        ))->__invoke($expr_node);

        if (!$this->is_conditional_check && $loop_assignment_var_name !== null && $this->context->isInLoop()) {
            foreach ($this->context->getLoopNodeList() as $loop_node) {
                if ($loop_node instanceof Node) {
                    $this->context->markLoopDimWrite($loop_node, $loop_assignment_var_name);
                }
            }
        }

        return $context;
    }

    /**
     * Normalize a scalar array key to the value PHP actually uses at runtime.
     * PHP converts floats and bools to ints when they are used as array offsets.
     * Converting ahead of time avoids triggering PHP 8.1+ native warnings about
     * implicit conversions inside Phan itself while still modeling the runtime behaviour.
     *
     * @param int|float|string|bool $key user-provided array index
     */
    private static function normalizeScalarArrayKey(int|float|string|bool $key): int|string
    {
        if (\is_int($key) || \is_string($key)) {
            return $key;
        }
        if (\is_float($key)) {
            // PHP truncates floats when used as array keys
            return (int)$key;
        }
        // bools are normalized to ints when used as array keys
        return $key ? 1 : 0;
    }

    /**
     * Analyze an assignment where $variable_name is a superglobal, and return the new context.
     * May create a new variable in $this->context.
     * TODO: Emit issues if the assignment is incompatible with the pre-existing type?
     */
    private function analyzeSuperglobalDim(Node $node, string $variable_name): Context
    {
        $dim = $node->children['dim'];
        if ('GLOBALS' === $variable_name) {
            if (!\is_string($dim)) {
                // You're not going to believe this, but I just
                // found a piece of code like $GLOBALS[mt_rand()].
                // Super weird, right?
                return $this->context;
            }

            if (Variable::isHardcodedVariableInScopeWithName($dim, $this->context->isInGlobalScope())) {
                // Don't override types of superglobals such as $_POST, $argv through $_GLOBALS['_POST'] = expr either. TODO: Warn.
                return $this->context;
            }

            $variable = new Variable(
                $this->context,
                $dim,
                $this->right_type,
                0
            );

            $this->context->addGlobalScopeVariable(
                $variable
            );
        }
        // TODO: Assignment sanity checks.
        return $this->context;
    }

    /**
     * Before PHP 8.6, writing to a property of an object referenced by a constant (`OBJ->prop = 1`, `A::OBJ->prop .= 'x'`)
     * is a compile error ("Cannot use temporary expression in write context").
     *
     * @param Node $node a node of kind AST_PROP that is written to
     * @return bool true if an issue was emitted
     * @internal
     */
    public static function checkWriteToPropertyOfConstant(CodeBase $code_base, Context $context, Node $node): bool
    {
        $expr_node = $node->children['expr'];
        if (!($expr_node instanceof Node) || ($expr_node->kind !== ast\AST_CONST && $expr_node->kind !== ast\AST_CLASS_CONST)) {
            return false;
        }
        if (Config::get_closest_minimum_target_php_version_id() >= 80600) {
            return false;
        }
        Issue::maybeEmit(
            $code_base,
            $context,
            Issue::InvalidWriteToTemporaryExpression,
            $node->lineno,
            ASTReverter::toShortString($node),
            UnionTypeVisitor::unionTypeFromNode($code_base, $context, $expr_node)
        );
        return true;
    }

    // TODO: visitNullsafeProp should not be possible on the left hand side? Emit Issue::InvalidNode

    /**
     * @param Node $node
     * A node to analyze as the target of an assignment.
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the node
     */
    public function visitProp(Node $node): Context
    {
        if (!$this->is_conditional_check) {
            self::checkWriteToPropertyOfConstant($this->code_base, $this->context, $node);
        }
        // Get class list first, warn if the class list is invalid.
        try {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['expr']
            ))->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT, Issue::TypeExpectedObjectPropAccess);
        } catch (\Exception) {
            // If we can't figure out what kind of a class
            // this is, don't worry about it.
            //
            // Note that CodeBaseException is one possible exception due to invalid code created by the fallback parser, etc.
            return $this->context;
        }

        $property_name = $node->children['prop'];
        if ($property_name instanceof Node) {
            $property_name = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $property_name)->asSingleScalarValueOrNull();
        }

        // Things like $foo->$bar
        if (!\is_string($property_name)) {
            return $this->context;
        }
        $expr_node = $node->children['expr'];
        if ($expr_node instanceof Node &&
                $expr_node->kind === \ast\AST_VAR &&
                $expr_node->children['name'] === 'this') {
            $this->handleThisPropertyAssignmentInLocalScopeByName($node, $property_name);
        }

        if (Config::get_strict_object_checking()) {
            ContextNode::checkPossiblyUndeclaredInstanceProperty($this->code_base, $this->context, $node, $property_name);
        }

        $property = null;
        $class_with_property = null;
        $class_without_property = null;
        $expr_union_type = null;
        $expr_has_static_type = false;
        if ($expr_node instanceof Node) {
            $expr_union_type = UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $expr_node
            );
            $expr_has_static_type = $expr_union_type->hasStaticType();
        }
        foreach ($class_list as $clazz) {
            if ($clazz->isPropertyImmutableFromContext($this->code_base, $this->context, $property_name) &&
                    !($this->is_clone_with_properties && $this->canCloneWithSetReadonlyProperty($clazz, $property_name))) {
                $this->emitTypeModifyImmutableObjectPropertyIssue($clazz, $property_name, $node);
                return $this->context;
            }
            // Check to see if this class has the property or
            // a setter
            if (!$clazz->hasPropertyWithName($this->code_base, $property_name)) {
                if (!$clazz->hasMethodWithName($this->code_base, '__set', true)) {
                    if (!($clazz->isInterface() && $expr_node instanceof Node && $expr_node->kind === \ast\AST_VAR && $expr_node->children['name'] === 'this' && $expr_has_static_type)) {
                        $class_without_property = $clazz;
                    }
                    continue;
                }
            }

            try {
                $property = $clazz->getPropertyByNameInContext(
                    $this->code_base,
                    $property_name,
                    $this->context,
                    false,
                    $node,
                    true
                );
                $class_with_property = $clazz;
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
                return $this->context;
            }
        }

        if ($property && $class_with_property) {
            if ($class_without_property && Config::get_strict_object_checking()) {
                if (!($class_without_property->isInterface() && $expr_node instanceof Node && $expr_node->kind === \ast\AST_VAR && $expr_node->children['name'] === 'this' && $expr_has_static_type)) {
                    $this->emitIssue(
                        Issue::PossiblyUndeclaredPropertyOfClass,
                        $node->lineno,
                        $property_name,
                        $expr_union_type ?? UnionTypeVisitor::unionTypeFromNode(
                            $this->code_base,
                            $this->context,
                            $node->children['expr'] ?? $node->children['class']
                        ),
                        $class_without_property->getFQSEN()
                    );
                }
            }
            try {
                return $this->analyzePropAssignment($class_with_property, $property, $node);
            } catch (RecursionDepthException) {
                return $this->context;
            }
        }

        // Check if it is a built in class with dynamic properties but (possibly) no __set, such as SimpleXMLElement or stdClass or V8Js
        $is_class_with_arbitrary_types = isset($class_list[0]) ? $class_list[0]->hasDynamicProperties($this->code_base) : false;

        if ($is_class_with_arbitrary_types || Config::getValue('allow_missing_properties')) {
            try {
                // Create the property
                $property = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getOrCreateProperty($property_name, false);

                $this->addTypesToProperty($property, $node);
                $this->refineStdClassShapeAfterDynamicPropertyAssignment($expr_node, $expr_union_type, $property_name);
            } catch (\Exception) {
                // swallow it
            }
        } elseif (\count($class_list) > 0) {
            foreach ($class_list as $clazz) {
                if ($clazz->hasDynamicProperties($this->code_base)) {
                    return $this->context;
                }
            }
            $first_class = $class_list[0];
            $this->emitIssueWithSuggestion(
                Issue::UndeclaredProperty,
                $node->lineno ?? 0,
                ["{$first_class->getFQSEN()}->$property_name"],
                IssueFixSuggester::suggestSimilarProperty(
                    $this->code_base,
                    $this->context,
                    $first_class,
                    $property_name,
                    false
                )
            );
        } else {
            // If we hit this part, we couldn't figure out
            // the class, so we ignore the issue
        }

        return $this->context;
    }

    /**
     * @return bool whether this assigns to a read-only property with `clone($obj, ['prop' => $value])` in a way that is an initialization
     * of the new object, which the check for assignments outside of the constructor should not report.
     * (For a native readonly property, the set visibility was checked before this.
     * A property with `@phan-read-only` can be initialized this way in its class and subclasses.
     * A magic `@property-read` cannot be written.)
     */
    private function isInitializationByCloneWithProperties(Property $property): bool
    {
        if (!$this->is_clone_with_properties) {
            return false;
        }
        if ($property->isReadOnlyReal()) {
            return true;
        }
        if ($property->isFromPHPDoc() || !$this->context->isInClassScope()) {
            return false;
        }
        $class_fqsen = $this->context->getClassFQSEN();
        $property_class_fqsen = $property->getClassFQSEN();
        return $class_fqsen === $property_class_fqsen || $class_fqsen->asType()->isSubtypeOf($property_class_fqsen->asType(), $this->code_base);
    }

    /**
     * `clone($obj, ['prop' => $value])` initializes the property of the new object, so a readonly property can be set
     * from every scope that its set visibility allows: readonly is implicitly `protected(set)` (the class, its subclasses and its base classes),
     * unless the property is declared `public(set)` or `private(set)`.
     *
     * @return bool whether the readonly property can be set by clone from the current scope
     */
    private function canCloneWithSetReadonlyProperty(Clazz $clazz, string $property_name): bool
    {
        if (!$clazz->hasPropertyWithName($this->code_base, $property_name)) {
            return false;
        }
        if ($clazz->isImmutableAtRuntime() && !$clazz->isReadonly()) {
            // An enum or an internal class with properties that cannot be modified
            return false;
        }
        $property = $clazz->getPropertyByName($this->code_base, $property_name);
        if (!$property->isReadOnlyReal()) {
            return false;
        }
        if ($property->isPublicSet()) {
            return true;
        }
        if (!$this->context->isInClassScope()) {
            return false;
        }
        $class_fqsen = $this->context->getClassFQSEN();
        $defining_fqsen = $property->getDefiningClassFQSEN();
        if ($class_fqsen === $defining_fqsen) {
            return true;
        }
        if ($property->isPrivateSet()) {
            return false;
        }
        // Subclasses and base classes of the declaring class
        return $property->checkCanAccessProtectedElement($this->code_base, $class_fqsen);
    }

    /**
     * @throws \InvalidArgumentException|\Phan\Exception\FQSENException if a new shaped type cannot be constructed
     */
    private function refineStdClassShapeAfterDynamicPropertyAssignment(mixed $expr_node, ?UnionType $expr_union_type, string $property_name): void
    {
        if (!($expr_node instanceof Node)) {
            return;
        }
        if ($expr_union_type === null || $expr_union_type->isEmpty()) {
            return;
        }
        if ($this->dim_depth !== 0) {
            return;
        }
        if ($this->assignment_node->kind !== ast\AST_ASSIGN) {
            return;
        }
        if ($expr_node->kind !== ast\AST_VAR) {
            return;
        }
        $variable_name = $expr_node->children['name'] ?? null;
        if (!\is_string($variable_name) || $variable_name === '') {
            return;
        }
        $scope = $this->context->getScope();
        if (!$scope->hasVariableWithName($variable_name)) {
            return;
        }
        $variable = clone($scope->getVariableByName($variable_name));
        $property_union = $this->right_type->withStaticResolvedInContext($this->context)->withIsPossiblyUndefined(false);
        $updated_union_type = self::computeStdClassShapeAssignment($variable->getUnionType(), $property_union, $property_name);
        if ($updated_union_type === null) {
            return;
        }
        $this->analyzeSetUnionType($variable, $updated_union_type, $this->assignment_node->children['expr'] ?? null);
        $this->context->addScopeVariable($variable);
    }

    /**
     * @throws \InvalidArgumentException|\Phan\Exception\FQSENException if a new shaped type cannot be constructed
     */
    private static function computeStdClassShapeAssignment(UnionType $union_type, UnionType $property_union, string $property_name): ?UnionType
    {
        $result = $union_type;
        $changed = false;
        foreach ($union_type->getTypeSet() as $type) {
            if ($type instanceof StdClassShapeType) {
                $updated_type = $type->withMergedField($property_name, $property_union, false);
                if ($updated_type !== $type) {
                    $result = $result->withoutType($type)->withType($updated_type);
                    $changed = true;
                }
                continue;
            }
            if ($type->getName() === StdClassShapeType::NAME && $type->getNamespace() === '\\') {
                $new_type = StdClassShapeType::fromFieldTypes([$property_name => $property_union], $type->isNullable());
                if ($new_type instanceof StdClassShapeType) {
                    $result = $result->withoutType($type)->withType($new_type);
                    $changed = true;
                }
            }
        }
        return $changed ? $result : null;
    }

    /**
     * This analyzes an assignment to an instance or static property.
     *
     * @param Node $node the left hand side of the assignment
     */
    private function analyzePropAssignment(Clazz $clazz, Property $property, Node $node): Context
    {
        $code_base = $this->code_base;
        // A virtual property without a set hook cannot be assigned to, whatever else is declared for it
        // (`@phan-read-only` allows the assignment in the constructor)
        if ($this->dim_depth === 0 && !$this->is_conditional_check && $this->analyzeAssignmentToGetOnlyVirtualProperty($property, $node)) {
            // Already reported
        } elseif ($property->isReadOnly()) {
            // Skip read-only checks if this is a conditional check (isset/array_key_exists)
            // We're only inferring that the field exists, not actually modifying it
            if (!$this->is_conditional_check && !$this->isInitializationByCloneWithProperties($property) && ($this->dim_depth === 0 || !self::shouldSkipReadOnlyDimAssignmentCheck($property))) {
                $this->analyzeAssignmentToReadOnlyProperty($property, $node);
            }
        }
        // TODO: Iterate over individual types, don't look at the whole type at once?

        // If we're assigning to an array element then we don't
        // know what the array structure of the parameter is
        // outside of the scope of this assignment, so we add to
        // its union type rather than replace it.
        // TODO: If the property is inherited, this will resolve `static` in the context of the parent class, and
        // thus yield a supertype of the intended type. However, we can't resolve `static` in the right context here,
        // and the PHPDoc type isn't meant to be replaced with concrete types as in Property::inheritStaticUnionType().
        $property_union_type = $property->getPHPDocUnionType()->withStaticResolvedInContext($property->getContext());

        // Map template types to concrete types
        if ($property_union_type->hasTemplateTypeRecursive()) {
            // Get the type of the object to which the property belongs
            $expression_type = UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $node->children['expr'] ?? null
            );

            $property = $property->cloneWithTemplateParameterTypeMap(
                $expression_type->getTemplateParameterTypeMap($this->code_base)
            );
            $property_union_type = $property->getPHPDocUnionType()->withStaticResolvedInContext($property->getContext());
        }

        // If the property has a set hook with an explicit parameter type, external
        // assignments are intercepted by the hook, so check against the hook's
        // parameter type instead of the property's declared storage type.
        // Skip this when we're inside the property's own hook body — those
        // assignments write directly to backing storage, bypassing the hook.
        $set_hook = $property->getSetHook();
        if ($set_hook !== null) {
            $is_inside_own_hook = false;
            // Walk the scope chain to find a PropertyScope for this property.
            // The hook body has a BranchScope whose parent is the PropertyScope.
            for ($scope = $this->context->getScope(); !($scope instanceof \Phan\Language\Scope\GlobalScope); $scope = $scope->getParentScope()) {
                if ($scope->isInPropertyScope()) {
                    $is_inside_own_hook = $scope->getPropertyFQSEN() === $property->getFQSEN();
                    break;
                }
            }
            if (!$is_inside_own_hook) {
                $params = $set_hook->getParameterList();
                if (!empty($params)) {
                    $hook_param_type = $params[0]->getNonVariadicUnionType();
                    if (!$hook_param_type->isEmpty()) {
                        $property_union_type = $hook_param_type;
                    }
                }
            }
        }

        $resolved_right_type = $this->right_type->withStaticResolvedInContext($this->context);
        if ($this->dim_depth > 0) {
            // Check compatibility without expanding property type to include parent classes.
            // Expanding would incorrectly allow sibling types to be considered compatible.
            // See https://github.com/phan/phan/issues/4727
            if ($resolved_right_type->canCastToUnionType(
                $property_union_type,
                $code_base
            )) {
                $this->addTypesToProperty($property, $node);
                if (Config::get_strict_property_checking() && $resolved_right_type->typeCount() > 1) {
                    $this->analyzePropertyAssignmentStrict($property, $resolved_right_type, $node);
                }
            } elseif ($property_union_type->hasArrayAccess($code_base)) {
                // Add any type if this is a subclass with array access.
                $this->addTypesToProperty($property, $node);
            } else {
                // Convert array shape types to generic arrays to reduce false positive PhanTypeMismatchProperty instances.

                // TODO: If the codebase explicitly sets a phpdoc array shape type on a property assignment,
                // then preserve the array shape type.
                $new_types = $this->typeCheckDimAssignment($property_union_type, $node)
                                  ->withFlattenedArrayShapeOrLiteralTypeInstances()
                                  ->withStaticResolvedInContext($this->context);

                if (!$new_types->canCastToUnionType(
                    $property_union_type,
                    $code_base
                )) {
                    // echo "Emitting warning for $new_types\n";
                    // TODO: Don't emit if array shape type is compatible with the original value of $property_union_type
                    $this->emitTypeMismatchPropertyIssue(
                        $node,
                        $property,
                        $resolved_right_type,
                        $new_types,
                        $property_union_type
                    );
                } else {
                    if (Config::get_strict_property_checking() && $resolved_right_type->typeCount() > 1) {
                        $this->analyzePropertyAssignmentStrict($property, $resolved_right_type, $node);
                    }
                    $this->right_type = $new_types;
                    $this->addTypesToProperty($property, $node);
                }
            }
            return $this->context;
        } elseif ($clazz->isPHPInternal() && $clazz->getFQSEN() !== FullyQualifiedClassName::getStdClassFQSEN()) {
            // We don't want to modify the types of internal classes such as \ast\Node even if they are compatible
            // This would result in unpredictable results, and types which are more specific than they really are.
            // stdClass is an exception to this, for issues such as https://github.com/phan/phan/pull/700
            return $this->context;
        } else {
            // This is a regular assignment, not an assignment to an offset
            if (!$resolved_right_type->canCastToUnionType(
                $property_union_type,
                $code_base
            )
                && !($resolved_right_type->hasTypeInBoolFamily() && $property_union_type->hasTypeInBoolFamily())
                && !$clazz->hasDynamicProperties($code_base)
                && !$property->isDynamicProperty()
            ) {
                if ($resolved_right_type->nonNullableClone()->canCastToUnionType($property_union_type, $code_base) &&
                        !$resolved_right_type->isType(NullType::instance(false))) {
                    if ($this->shouldSuppressIssue(Issue::TypeMismatchProperty, $node->lineno)) {
                        return $this->context;
                    }
                    $this->emitIssue(
                        Issue::PossiblyNullTypeMismatchProperty,
                        $node->lineno,
                        $this->getAssignedExpressionString(),
                        (string)$this->right_type->withUnionType($resolved_right_type),
                        $property->getRepresentationForIssue(),
                        (string)$property_union_type,
                        'null'
                    );
                } else {
                    // echo "Emitting warning for {$resolved_right_type->asExpandedTypes($code_base)} to {$property_union_type->asExpandedTypes($code_base)}\n";
                    $this->emitTypeMismatchPropertyIssue($node, $property, $resolved_right_type, $this->right_type->withUnionType($resolved_right_type), $property_union_type);
                }
                return $this->context;
            }

            if (Config::get_strict_property_checking() && $this->right_type->typeCount() > 1) {
                $this->analyzePropertyAssignmentStrict($property, $this->right_type, $node);
            }
        }

        // After having checked it, add this type to it
        $this->addTypesToProperty($property, $node);

        return $this->context;
    }

    /**
     * Skip readonly warnings for offset assignments when the property stays bound to an object.
     */
    private static function shouldSkipReadOnlyDimAssignmentCheck(Property $property): bool
    {
        if (!$property->isReadOnlyReal()) {
            return false;
        }
        $property_type = $property->getUnionType()->nonNullableClone();
        if ($property_type->isEmpty()) {
            return false;
        }
        return $property_type->allTypesMatchCallback(static function (Type $type): bool {
            return $type->isObject();
        });
    }

    /**
     * @param UnionType $resolved_right_type the type of the expression to use when checking for real type mismatches
     * @param UnionType $warn_type the type to use in issue messages
     */
    private function emitTypeMismatchPropertyIssue(
        Node $node,
        Property $property,
        UnionType $resolved_right_type,
        UnionType $warn_type,
        UnionType $property_union_type
    ): void {
        if ($this->context->hasSuppressIssue($this->code_base, Issue::TypeMismatchPropertyReal)) {
            return;
        }
        if (self::isRealMismatch($this->code_base, $property->getRealUnionType(), $resolved_right_type)) {
            if ($this->suppress_dim_property_mismatch && $this->dim_depth > 0) {
                return;
            }
            $this->emitIssue(
                Issue::TypeMismatchPropertyReal,
                $node->lineno,
                $this->getAssignedExpressionString(),
                $warn_type->toErrorMessageString(),
                PostOrderAnalysisVisitor::toDetailsForRealTypeMismatch($warn_type),
                $property->getRepresentationForIssue(),
                $property_union_type->toErrorMessageString(),
                PostOrderAnalysisVisitor::toDetailsForRealTypeMismatch($property_union_type)
            );
            return;
        }
        if ($this->context->hasSuppressIssue($this->code_base, Issue::TypeMismatchPropertyProbablyReal)) {
            return;
        }
        if ($resolved_right_type->hasRealTypeSet() &&
            !$resolved_right_type->getRealUnionType()->canCastToDeclaredType($this->code_base, $this->context, $property_union_type)) {
            $this->emitIssue(
                Issue::TypeMismatchPropertyProbablyReal,
                $node->lineno,
                $this->getAssignedExpressionString(),
                $warn_type->toErrorMessageString(),
                PostOrderAnalysisVisitor::toDetailsForRealTypeMismatch($warn_type),
                $property->getRepresentationForIssue(),
                $property_union_type->toErrorMessageString(),
                PostOrderAnalysisVisitor::toDetailsForRealTypeMismatch($property_union_type)
            );
            return;
        }
        if ($this->suppress_dim_property_mismatch && $this->dim_depth > 0) {
            return;
        }
        $this->emitIssue(
            Issue::TypeMismatchProperty,
            $node->lineno,
            $this->getAssignedExpressionString(),
            $warn_type->toErrorMessageString(),
            $property->getRepresentationForIssue(),
            $property_union_type->toErrorMessageString()
        );
    }

    private function getAssignedExpressionString(): string
    {
        $expr = $this->assignment_node->children['expr'] ?? null;
        if ($expr === null) {
            return '(unknown)';
        }
        $str = ASTReverter::toShortString($expr);
        if ($this->dim_depth > 0) {
            return "($str as a field)";
        }
        return $str;
    }

    /**
     * Returns true if Phan should emit a more severe issue type for real type mismatch
     */
    private static function isRealMismatch(CodeBase $code_base, UnionType $real_property_type, UnionType $real_actual_type): bool
    {
        if ($real_property_type->isEmpty()) {
            return false;
        }
        return !$real_actual_type->isStrictSubtypeOf($code_base, $real_property_type);
    }

    /**
     * Modifies $this->context (if needed) to track the assignment to a property of $this within a function-like.
     * This handles conditional branches.
     * @param string $prop_name
     * TODO: If $this->right_type is the empty union type and the property is declared, assume the phpdoc/real types instead of the empty union type.
     */
    private function handleThisPropertyAssignmentInLocalScopeByName(Node $node, string $prop_name): void
    {
        if ($this->dim_depth === 0) {
            $new_type = $this->right_type;
            $new_type = $this->narrowTypeToDeclaredPropertyType($new_type, $prop_name);
        } else {
            // Copied from visitVar
            $old_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node);
            $right_type = $this->typeCheckDimAssignment($old_type, $node);
            $container_may_be_absent = !$this->is_conditional_check && self::typeSetContainsNullOrFalse($old_type);
            $old_type = $old_type->nonNullableClone();
            if ($old_type->isEmpty()) {
                $old_type = ArrayType::instance(false)->asPHPDocUnionType();
            }

            if ($this->dim_depth > 1) {
                $new_type = $this->computeTypeOfMultiDimensionalAssignment($old_type, $right_type, $container_may_be_absent);
            } elseif ($old_type->hasTopLevelNonArrayShapeTypeInstances() || $right_type->hasTopLevelNonArrayShapeTypeInstances() || $right_type->isEmpty()) {
                $new_type = $old_type->withUnionType($right_type);
            } else {
                $new_type = ArrayType::combineArrayTypesOverriding($right_type, $old_type, true);
            }
        }
        $this->context = $this->context->withThisPropertySetToTypeByName($prop_name, $new_type);
    }

    /**
     * Narrows a union type to only include types compatible with the declared property type.
     * Only narrows based on native (runtime-enforced) property types, not PHPDoc annotations.
     * If the filter produces an empty result (complete type mismatch), returns the original type unchanged
     * since Phan reports the mismatch elsewhere via analyzePropAssignment.
     *
     * @param ?FullyQualifiedClassName $target_class_fqsen The class owning the property (for static properties
     *        this may differ from the current class, e.g. parent::$prop). Falls back to the current class context.
     */
    private function narrowTypeToDeclaredPropertyType(UnionType $type, string $prop_name, ?FullyQualifiedClassName $target_class_fqsen = null): UnionType
    {
        $class_fqsen = $target_class_fqsen ?? $this->context->getClassFQSENOrNull();
        if ($class_fqsen === null) {
            return $type;
        }
        if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
            return $type;
        }
        $clazz = $this->code_base->getClassByFQSEN($class_fqsen);
        if (!$clazz->hasPropertyWithName($this->code_base, $prop_name)) {
            return $type;
        }
        $property = $clazz->getPropertyByName($this->code_base, $prop_name);
        $declared_type = $property->getRealUnionType();
        if ($declared_type->isEmpty()) {
            return $type;
        }
        $code_base = $this->code_base;
        $narrowed = $type->makeFromFilter(static function (Type $single_type) use ($declared_type, $code_base): bool {
            // Strip nullability for the check — nullable-to-non-null mismatches are
            // already reported via analyzePropAssignment, and we want ?Foo to be
            // recognized as compatible with a non-null Foo declared type.
            return $single_type->withIsNullable(false)->asPHPDocUnionType()->canCastToUnionTypeWithoutConfig($declared_type, $code_base);
        });
        if ($narrowed->isEmpty()) {
            return $type;
        }
        // If the declared type is non-null, strip nullability from the result.
        // If the assignment succeeded at runtime, the property holds a non-null value.
        if (!$declared_type->containsNullableOrUndefined()) {
            $narrowed = $narrowed->nonNullableClone();
        }
        return $narrowed;
    }

    private function handleStaticPropertyAssignmentInLocalScopeByName(Node $node, string $prop_name): void
    {
        $target = $this->getStaticPropertyAssignmentTarget($node);
        if ($this->dim_depth === 0) {
            $new_type = $this->right_type;
            $new_type = $this->narrowTypeToDeclaredPropertyType($new_type, $prop_name, $target[0] ?? null);
        } else {
            // Copied from visitVar
            $old_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node);
            $right_type = $this->typeCheckDimAssignment($old_type, $node);
            $container_may_be_absent = !$this->is_conditional_check && self::typeSetContainsNullOrFalse($old_type);
            $old_type = $old_type->nonNullableClone();
            if ($old_type->isEmpty()) {
                $old_type = ArrayType::instance(false)->asPHPDocUnionType();
            }

            if ($this->dim_depth > 1) {
                $new_type = $this->computeTypeOfMultiDimensionalAssignment($old_type, $right_type, $container_may_be_absent);
            } elseif ($old_type->hasTopLevelNonArrayShapeTypeInstances() || $right_type->hasTopLevelNonArrayShapeTypeInstances() || $right_type->isEmpty()) {
                $new_type = $old_type->withUnionType($right_type);
            } else {
                $new_type = ArrayType::combineArrayTypesOverriding($right_type, $old_type, true);
            }
        }
        $this->context = $this->context->withStaticPropertySetToTypeByName($prop_name, $new_type);
        if ($target && $this->context->isInFunctionLikeScope()) {
            $function_like = $this->context->getFunctionLikeInScope($this->code_base);
            if ($function_like instanceof Method) {
                [$target_class, $is_late_static] = $target;
                $function_like->recordStaticPropertyModification($target_class, $prop_name, $new_type, $is_late_static);
            }
        }
    }

    /**
     * @return array{0:FullyQualifiedClassName,1:bool}|null
     */
    private function getStaticPropertyAssignmentTarget(Node $node): ?array
    {
        $class_node = $node->children['class'] ?? null;
        if (!($class_node instanceof Node) || $class_node->kind !== ast\AST_NAME) {
            return null;
        }
        $name = $class_node->children['name'] ?? null;
        if (!\is_string($name)) {
            return null;
        }
        $context_class_fqsen = $this->context->getClassFQSENOrNull();
        if (!$context_class_fqsen) {
            return null;
        }
        $normalized = \strtolower($name);
        if ($normalized === 'self') {
            $is_trait = false;
            if ($this->code_base->hasClassWithFQSEN($context_class_fqsen)) {
                $class = $this->code_base->getClassByFQSEN($context_class_fqsen);
                $is_trait = $class->isTrait();
            }
            return [$context_class_fqsen, $is_trait];
        }
        if ($normalized === 'static') {
            return [$context_class_fqsen, true];
        }
        if ($normalized === 'parent') {
            try {
                $clazz = $this->context->getClassInScope($this->code_base);
            } catch (CodeBaseException) {
                return null;
            }
            if (!$clazz->hasParentType()) {
                return null;
            }
            return [$clazz->getParentClassFQSEN(), false];
        }
        return null;
    }

    /**
     * Warns about an assignment to a virtual property without a set hook, which always throws,
     * also in the class itself and for `clone($o, ['p' => $x])`.
     *
     * @return bool whether the issue was emitted
     */
    private function analyzeAssignmentToGetOnlyVirtualProperty(Property $property, Node $node): bool
    {
        if ($property->getGetHook() === null || !Clazz::isAssignmentToGetOnlyVirtualProperty($this->code_base, $property)) {
            return false;
        }
        if ($this->is_coalesce_assign) {
            // `$o->p ??= $x` only assigns when the get hook returns null, which it may never do
            // (also when the type is nullable: a subclass can declare a type that is not)
            return false;
        }
        if ($this->mayOtherReceiverAllowWrite($property, $node)) {
            return false;
        }
        $property_context = $property->getContext();
        $this->emitIssue(
            Issue::AccessGetOnlyVirtualProperty,
            $node->lineno,
            $property->asPropertyFQSENString(),
            $property_context->getFile(),
            $property_context->getLineNumberStart()
        );
        return true;
    }

    /**
     * @return bool whether the object can be an instance of another class for which the assignment may succeed:
     * for `Plain|VirtualOnly`, only the property of one of the classes was checked.
     */
    private function mayOtherReceiverAllowWrite(Property $property, Node $node): bool
    {
        $expr_node = $node->kind === ast\AST_PROP ? $node->children['expr'] : null;
        if (!($expr_node instanceof Node)) {
            return false;
        }
        try {
            $class_list = (new ContextNode($this->code_base, $this->context, $expr_node))->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT);
        } catch (\Exception) {
            return false;
        }
        // An object of a class that is not known: `VirtualOnly|object`, `mixed`
        foreach (UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $expr_node)->getTypeSet() as $type) {
            if (!$type->isObjectWithKnownFQSEN() && $type->isPossiblyObject()) {
                return true;
            }
        }
        $name = $property->getName();
        foreach ($class_list as $clazz) {
            if (!$clazz->hasPropertyWithName($this->code_base, $name)) {
                // A dynamic property, `__set`, or an undeclared property that is reported elsewhere
                return true;
            }
            if (!Clazz::isAssignmentToGetOnlyVirtualProperty($this->code_base, $clazz->getPropertyByName($this->code_base, $name))) {
                return true;
            }
        }
        return false;
    }

    private function analyzeAssignmentToReadOnlyProperty(Property $property, Node $node): void
    {
        $class_fqsen = $property->getClassFQSEN();
        $context = $property->getContext();

        $is_from_phpdoc = $property->isFromPHPDoc();

        // Magic properties (@property-read) should always warn, even in __construct
        // They are handled by __get/__set methods, not direct assignment
        if ($is_from_phpdoc) {
            $this->emitIssue(
                Issue::AccessReadOnlyMagicProperty,
                $node->lineno,
                $property->asPropertyFQSENString(),
                $context->getFile(),
                $context->getLineNumberStart()
            );
            return;
        }

        // Distinguish between native readonly and @phan-read-only
        $is_native_readonly = $property->isReadOnlyReal();

        if ($this->context->isInFunctionLikeScope() && $this->context->isInClassScope()) {
            $method = $this->context->getFunctionLikeInScope($this->code_base);
            $method_class_fqsen = $this->context->getClassFQSEN();

            $property_class_type = $class_fqsen->asType();
            $method_class_type = $method_class_fqsen->asType();
            $is_same_or_subclass = $method_class_type->isSubtypeOf($property_class_type, $this->code_base) || $method_class_fqsen === $class_fqsen;

            // Both native readonly and @phan-read-only only allow setting in __construct
            // (and __clone for PHP 8.3+ native readonly)
            $allowed_methods = $is_native_readonly && Config::get_closest_minimum_target_php_version_id() >= 80300
                ? [ '__construct', '__clone' ]
                : [ '__construct' ];

            if ($method instanceof Method && in_array(strtolower($method->getName()), $allowed_methods, true) && $is_same_or_subclass) {
                // For native readonly, also check for multiple assignments in the same method
                if ($is_native_readonly) {
                    if ($property->hasDefaultValue() && strtolower($method->getName()) === '__construct') {
                        // PHP 8.6+: the default value of a readonly property is its initialization, so the constructor cannot assign to it.
                        // (__clone() may still reinitialize it once)
                        if ($this->is_coalesce_assign && self::mayReadonlyDefaultBeNonNull($property)) {
                            // `$this->prop ??= $x` does not assign when the default value is not null.
                            return;
                        }
                        $this->emitIssue(
                            Issue::AccessReadOnlyPropertyMultipleTimes,
                            $node->lineno,
                            $property->asPropertyFQSENString()
                        );
                        return;
                    }
                    $this->checkMultipleReadOnlyPropertyAssignments($property, $node, $method);
                }
                return;
            }

            // For non-nullable native readonly properties, ??= within the declaring class scope is safe:
            // - if uninitialized, PHP treats it as null and assigns once (valid first write from same class)
            // - if already initialized to a non-null value, ??= short-circuits (no-op, no write attempt)
            // This exemption is scoped to the declaring class: outside the class, PHP would throw
            // "Cannot initialize readonly property from global scope / outside scope".
            // Nullable readonly properties are excluded: null is a valid initialized state,
            // so ??= could keep attempting to re-assign.
            // @phan-read-only (non-native) properties are excluded: not PHP-enforced.
            if ($this->is_coalesce_assign && $is_native_readonly && $is_same_or_subclass) {
                $declared_type = $property->getRealUnionType();
                if (!$declared_type->containsNullableOrUndefined()) {
                    return;
                }
            }
        }

        // If we reach here, it's a real property (not magic) that's readonly
        $this->emitIssue(
            Issue::AccessReadOnlyProperty,
            $node->lineno,
            $property->asPropertyFQSENString(),
            $context->getFile(),
            $context->getLineNumberStart()
        );
    }

    /**
     * @return bool whether the default value of this property may be something other than null:
     * its declared type is not nullable, or the default value is not known to be null.
     * (The type of a default that is a constant expression such as `self::X` is not known when the property is declared)
     */
    private static function mayReadonlyDefaultBeNonNull(Property $property): bool
    {
        if (!$property->getRealUnionType()->containsNullableOrUndefined()) {
            return true;
        }
        $default_type = $property->getDefaultType();
        return $default_type === null || $default_type->isEmpty() || !$default_type->isNull();
    }

    /**
     * Check for multiple assignments to a native readonly property within the same method.
     * This is a simplified check that detects obvious cases in the same method body.
     */
    private function checkMultipleReadOnlyPropertyAssignments(Property $property, Node $node, FunctionInterface $method): void
    {
        // Track property assignments in the method's AST and check if multiple assignments
        // occur on the same execution path (not in mutually exclusive branches)

        // Check if we can detect multiple assignments in the method
        $property_name = $property->getName();
        $method_node = $method->getNode();

        if (!$method_node instanceof Node) {
            return;
        }

        // Find all assignments for this property in the method
        $assignments = $this->findPropertyAssignmentLines($method_node, $property_name);

        // If there's only one assignment, no problem
        if (count($assignments) <= 1) {
            return;
        }

        // Find the current assignment in the list
        $current_assignment = null;
        foreach ($assignments as $assignment) {
            if ($assignment['line'] === $node->lineno) {
                $current_assignment = $assignment;
                break;
            }
        }

        if (!$current_assignment) {
            return;
        }

        // Check if this assignment conflicts with any earlier assignment
        // (i.e., they're NOT in mutually exclusive branches)
        foreach ($assignments as $other_assignment) {
            // Skip comparing with itself
            if ($other_assignment['line'] === $current_assignment['line']) {
                continue;
            }

            // Only warn about later assignments
            if ($other_assignment['line'] > $current_assignment['line']) {
                continue;
            }

            // Check if these assignments are in mutually exclusive branches
            if (!self::areAssignmentsMutuallyExclusive($current_assignment, $other_assignment)) {
                // They're not mutually exclusive, so this is a potential error
                $this->emitIssue(
                    Issue::AccessReadOnlyPropertyMultipleTimes,
                    $node->lineno,
                    $property->asPropertyFQSENString()
                );
                return;
            }
        }
    }

    /**
     * Find all assignments where a property is assigned in an AST node tree
     * @param list<Node> $ancestors
     * @return list<array{line:int,node:Node,ancestors:list<Node>}>
     */
    private function findPropertyAssignmentLines(Node $node, string $property_name, array $ancestors = []): array
    {
        $assignments = [];

        // Add current node to ancestors for children
        $current_ancestors = $ancestors;
        $current_ancestors[] = $node;

        // Check if this node is an assignment to the property
        if ($node->kind === \ast\AST_ASSIGN) {
            $var_node = $node->children['var'];
            if ($var_node instanceof Node && $var_node->kind === \ast\AST_PROP) {
                $prop_name_node = $var_node->children['prop'];
                $expr_node = $var_node->children['expr'];

                // Check if it's $this->propertyName
                if ($prop_name_node === $property_name &&
                    $expr_node instanceof Node &&
                    $expr_node->kind === \ast\AST_VAR &&
                    $expr_node->children['name'] === 'this') {
                    $assignments[] = ['line' => $node->lineno, 'node' => $node, 'ancestors' => $ancestors];
                }
            }
        }

        // Recursively check child nodes
        foreach ($node->children as $child) {
            if ($child instanceof Node) {
                $assignments = \array_merge($assignments, $this->findPropertyAssignmentLines($child, $property_name, $current_ancestors));
            }
        }

        return $assignments;
    }

    /**
     * Check if two assignment nodes are in mutually exclusive code branches
     *
     * @param array{line:int,node:Node,ancestors:list<Node>} $assignment1
     * @param array{line:int,node:Node,ancestors:list<Node>} $assignment2
     */
    private static function areAssignmentsMutuallyExclusive(array $assignment1, array $assignment2): bool
    {
        $node1_ancestors = $assignment1['ancestors'];
        $node2_ancestors = $assignment2['ancestors'];

        // If both assignments share a loop ancestor, they can execute in different iterations
        // even if they're in mutually exclusive branches within the loop
        if (self::shareLoopAncestor($node1_ancestors, $node2_ancestors)) {
            return false;
        }

        // Check for if/else mutual exclusion
        if (self::areInSiblingIfElseBranches($node1_ancestors, $node2_ancestors)) {
            return true;
        }

        // Check for switch/case mutual exclusion
        if (self::areInDifferentSwitchCases($node1_ancestors, $node2_ancestors)) {
            return true;
        }

        return false;
    }

    /**
     * Check if two assignments share a common loop ancestor
     * Loops allow branches to execute in different iterations
     *
     * @param list<Node> $ancestors1
     * @param list<Node> $ancestors2
     */
    private static function shareLoopAncestor(array $ancestors1, array $ancestors2): bool
    {
        foreach ($ancestors1 as $ancestor1) {
            if (\in_array($ancestor1->kind, [
                \ast\AST_WHILE,
                \ast\AST_DO_WHILE,
                \ast\AST_FOR,
                \ast\AST_FOREACH,
            ], true)) {
                // Check if this loop is also an ancestor of the second assignment
                foreach ($ancestors2 as $ancestor2) {
                    if ($ancestor1 === $ancestor2) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Check if two assignments are in sibling if/else branches
     * @param list<Node> $ancestors1
     * @param list<Node> $ancestors2
     */
    private static function areInSiblingIfElseBranches(array $ancestors1, array $ancestors2): bool
    {
        // Find all AST_IF nodes that contain AST_IF_ELEM ancestors
        $if_elems1 = [];
        for ($i = 0; $i < count($ancestors1) - 1; $i++) {
            if ($ancestors1[$i]->kind === \ast\AST_IF) {
                // The next node should be an IF_ELEM
                if ($ancestors1[$i + 1]->kind === \ast\AST_IF_ELEM) {
                    $if_elems1[] = ['if' => $ancestors1[$i], 'if_elem' => $ancestors1[$i + 1]];
                }
            }
        }

        $if_elems2 = [];
        for ($i = 0; $i < count($ancestors2) - 1; $i++) {
            if ($ancestors2[$i]->kind === \ast\AST_IF) {
                // The next node should be an IF_ELEM
                if ($ancestors2[$i + 1]->kind === \ast\AST_IF_ELEM) {
                    $if_elems2[] = ['if' => $ancestors2[$i], 'if_elem' => $ancestors2[$i + 1]];
                }
            }
        }

        // Check if any IF node is shared but the IF_ELEM is different
        foreach ($if_elems1 as $elem1) {
            foreach ($if_elems2 as $elem2) {
                if ($elem1['if'] === $elem2['if'] && $elem1['if_elem'] !== $elem2['if_elem']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if two assignments are in different switch cases
     * @param list<Node> $ancestors1
     * @param list<Node> $ancestors2
     */
    private static function areInDifferentSwitchCases(array $ancestors1, array $ancestors2): bool
    {
        // Find all SWITCH_LIST nodes that contain SWITCH_CASE ancestors
        $switch_cases1 = [];
        for ($i = 0; $i < count($ancestors1) - 1; $i++) {
            if ($ancestors1[$i]->kind === \ast\AST_SWITCH_LIST) {
                // The next node should be a SWITCH_CASE
                if ($ancestors1[$i + 1]->kind === \ast\AST_SWITCH_CASE) {
                    $switch_cases1[] = ['list' => $ancestors1[$i], 'case' => $ancestors1[$i + 1]];
                }
            }
        }

        $switch_cases2 = [];
        for ($i = 0; $i < count($ancestors2) - 1; $i++) {
            if ($ancestors2[$i]->kind === \ast\AST_SWITCH_LIST) {
                // The next node should be a SWITCH_CASE
                if ($ancestors2[$i + 1]->kind === \ast\AST_SWITCH_CASE) {
                    $switch_cases2[] = ['list' => $ancestors2[$i], 'case' => $ancestors2[$i + 1]];
                }
            }
        }

        // Check if any SWITCH_LIST node is shared but the SWITCH_CASE is different
        foreach ($switch_cases1 as $case1) {
            foreach ($switch_cases2 as $case2) {
                if ($case1['list'] === $case2['list'] && $case1['case'] !== $case2['case']) {
                    // Same switch statement, different cases
                    // Only consider them mutually exclusive if both cases have unconditional terminators
                    // (break, return, throw) to prevent false negatives on fallthrough cases
                    if (self::switchCaseHasUnconditionalTerminator($case1['case']) &&
                        self::switchCaseHasUnconditionalTerminator($case2['case'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if a switch case has an unconditional terminator (break, return, throw)
     * to prevent fallthrough to the next case.
     */
    private static function switchCaseHasUnconditionalTerminator(Node $case_node): bool
    {
        $stmts = $case_node->children['stmts'];
        if (!($stmts instanceof Node)) {
            // Empty case - will fall through
            return false;
        }

        // Get the last statement in the case
        $last_stmt = null;
        foreach ($stmts->children as $stmt) {
            if ($stmt instanceof Node) {
                $last_stmt = $stmt;
            }
        }

        if (!$last_stmt) {
            return false;
        }

        // Check if the last statement is a terminator
        return \in_array($last_stmt->kind, [
            \ast\AST_BREAK,
            \ast\AST_RETURN,
            \ast\AST_THROW,
            \ast\AST_CONTINUE,  // Less common but also prevents fallthrough in some contexts
        ], true);
    }

    private function analyzePropertyAssignmentStrict(Property $property, UnionType $assignment_type, Node $node): void
    {
        $type_set = $assignment_type->getTypeSet();
        if (\count($type_set) < 2) {
            throw new AssertionError('Expected to have at least two types when checking if types match in strict mode');
        }

        $property_union_type = $property->getUnionType();

        $mismatch_type_set = UnionType::empty();
        $mismatch_expanded_types = null;

        // For the strict
        foreach ($type_set as $type) {
            // Expand it to include all parent types up the chain
            $individual_type_expanded = $type->asExpandedTypes($this->code_base);

            // See if the argument can be cast to the
            // property
            if (!$individual_type_expanded->canCastToUnionType(
                $property_union_type,
                $this->code_base
            )) {
                $mismatch_type_set = $mismatch_type_set->withType($type);
                if ($mismatch_expanded_types === null) {
                    // Warn about the first type
                    $mismatch_expanded_types = $individual_type_expanded;
                }
            }
        }


        if ($mismatch_expanded_types === null) {
            // No mismatches
            return;
        }
        if ($this->shouldSuppressIssue(Issue::TypeMismatchPropertyReal, $node->lineno) ||
            $this->shouldSuppressIssue(Issue::TypeMismatchPropertyProbablyReal, $node->lineno) ||
            $this->shouldSuppressIssue(Issue::TypeMismatchProperty, $node->lineno)
        ) {
            // TypeMismatchProperty also suppresses PhanPossiblyNullTypeMismatchProperty, etc.
            return;
        }

        $this->emitIssue(
            self::getStrictPropertyMismatchIssueType($mismatch_type_set),
            $node->lineno,
            $this->getAssignedExpressionString(),
            (string)$this->right_type,
            $property->getRepresentationForIssue(),
            (string)$property_union_type,
            (string)$mismatch_expanded_types
        );
    }

    private static function getStrictPropertyMismatchIssueType(UnionType $union_type): string
    {
        if ($union_type->typeCount() === 1) {
            $type = $union_type->getTypeSet()[0];
            if ($type instanceof NullType) {
                return Issue::PossiblyNullTypeMismatchProperty;
            }
            if ($type instanceof FalseType) {
                return Issue::PossiblyFalseTypeMismatchProperty;
            }
        }
        return Issue::PartialTypeMismatchProperty;
    }

    /**
     * Based on AssignmentVisitor->addTypesToProperty
     * Used for analyzing reference parameters' possible effects on properties.
     * @internal the API will likely change
     */
    public static function addTypesToPropertyStandalone(
        CodeBase $code_base,
        Context $context,
        Property $property,
        UnionType $new_types
    ): void {
        $original_property_types = $property->getUnionType();
        if ($property->getRealUnionType()->isEmpty() && $property->getPHPDocUnionType()->isEmpty()) {
            $property->setUnionType(
                $new_types
                     ->eraseRealTypeSetRecursively()
                     ->withUnionType($property->getUnionType()->eraseRealTypeSetRecursively())
                     ->withStaticResolvedInContext($context)
                     ->withFlattenedArrayShapeOrLiteralTypeInstances()
            );
            return;
        }
        if ($original_property_types->isEmpty()) {
            // TODO: Be more precise?
            $property->setUnionType(
                $new_types
                     ->withStaticResolvedInContext($context)
                     ->withFlattenedArrayShapeOrLiteralTypeInstances()
                     ->withRealTypeSet($property->getRealUnionType()->getTypeSet())
            );
            return;
        }

        $has_literals = $original_property_types->hasLiterals();
        $new_types = $new_types->withStaticResolvedInContext($context)->withFlattenedArrayShapeTypeInstances();

        $updated_property_types = $original_property_types;
        foreach ($new_types->getTypeSet() as $new_type) {
            if ($new_type instanceof MixedType) {
                // Don't add MixedType to a non-empty property - It makes inferences on that property useless.
                continue;
            }

            // Only allow compatible types to be added to declared properties.
            // Allow anything to be added to dynamic properties.
            // TODO: Be more permissive about declared properties without phpdoc types.
            if (!$new_type->asPHPDocUnionType()->canCastToUnionType($original_property_types, $code_base) && !$property->isDynamicProperty()) {
                continue;
            }

            // Check for adding a specific array to as generic array as a workaround for #1783
            if (\get_class($new_type) === ArrayType::class && $original_property_types->hasGenericArray()) {
                continue;
            }
            if (!$has_literals) {
                $new_type = $new_type->asNonLiteralType();
            }
            $updated_property_types = $updated_property_types->withType($new_type);
        }

        // TODO: Add an option to check individual types, not just the whole union type?
        //       If that is implemented, verify that generic arrays will properly cast to regular arrays (public $x = [];)
        $property->setUnionType($updated_property_types->withRealTypeSet($property->getRealUnionType()->getTypeSet()));
    }

    /**
     * @param Property $property - The property which should have types added to it
     */
    private function addTypesToProperty(Property $property, Node $node): void
    {
        if ($property->getRealUnionType()->isEmpty() && $property->getPHPDocUnionType()->isEmpty()) {
            $property->setUnionType(
                $this->right_type
                     ->withUnionType($property->getUnionType())
                     ->withStaticResolvedInContext($this->context)
                     ->withFlattenedArrayShapeOrLiteralTypeInstances()
                     ->eraseRealTypeSetRecursively()
            );
            return;
        }
        $original_property_types = $property->getUnionType();
        if ($original_property_types->isEmpty()) {
            // TODO: Be more precise?
            $property->setUnionType(
                $this->right_type
                     ->withStaticResolvedInContext($this->context)
                     ->withFlattenedArrayShapeOrLiteralTypeInstances()
                     ->eraseRealTypeSetRecursively()
                     ->withRealTypeSet($property->getRealUnionType()->getTypeSet())
            );
            return;
        }

        if ($this->dim_depth > 0) {
            $new_types = $this->typeCheckDimAssignment($original_property_types, $node);
        } else {
            $new_types = $this->right_type;
        }
        $has_literals = $original_property_types->hasLiterals();
        $new_types = $new_types->withStaticResolvedInContext($this->context)->withFlattenedArrayShapeTypeInstances();

        $updated_property_types = $original_property_types;

        // For interface-typed properties, don't accumulate inferred types unless
        // track_all_inferred_types is enabled. Keep the declared type as-is to ensure
        // method calls and other operations are validated against the declared contract,
        // not runtime assignments. This prevents false negatives where a property type
        // is expanded beyond its declared interface type based on assignments, causing
        // methods to be validated incorrectly. Interface-typed properties should only
        // allow methods from the interface, not from potential implementations.
        if (!Config::get_track_all_inferred_types()) {
            $declared_type = $property->getPHPDocUnionType();
            foreach ($declared_type->getTypeSet() as $type) {
                try {
                    $type_fqsen = $type->asFQSEN();
                    if ($type_fqsen instanceof FullyQualifiedClassName && $this->code_base->hasClassWithFQSEN($type_fqsen)) {
                        $class = $this->code_base->getClassByFQSEN($type_fqsen);
                        if ($class->isInterface()) {
                            // Don't modify interface-typed properties - keep the declared type as-is
                            return;
                        }
                    }
                } catch (Throwable) {
                    // Ignore types that don't have valid FQSENs
                }
            }
        }

        foreach ($new_types->getTypeSet() as $new_type) {
            if ($new_type instanceof MixedType) {
                // Don't add MixedType to a non-empty property - It makes inferences on that property useless.
                continue;
            }

            // Only allow compatible types to be added to declared properties.
            // Allow anything to be added to dynamic properties.
            // TODO: Be more permissive about declared properties without phpdoc types.
            if (!$new_type->asPHPDocUnionType()->canCastToUnionType($original_property_types, $this->code_base) && !$property->isDynamicProperty()) {
                continue;
            }

            // Check for adding a specific array to as generic array as a workaround for #1783
            if (\get_class($new_type) === ArrayType::class && $original_property_types->hasGenericArray()) {
                continue;
            }
            if (!$has_literals) {
                $new_type = $new_type->asNonLiteralType();
            }
            $updated_property_types = $updated_property_types->withType($new_type);
        }

        // TODO: Add an option to check individual types, not just the whole union type?
        //       If that is implemented, verify that generic arrays will properly cast to regular arrays (public $x = [];)
        $property->setUnionType($updated_property_types->withRealTypeSet($property->getRealUnionType()->getTypeSet()));
    }

    /**
     * @param Node $node
     * A node to analyze as the target of an assignment.
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the node
     *
     * @see self::visitProp()
     */
    public function visitStaticProp(Node $node): Context
    {
        $property_name = $node->children['prop'];

        // Things like self::${$x}
        if (!\is_string($property_name)) {
            return $this->context;
        }

        $class_node = $node->children['class'];
        // Check if this is a self/static/parent reference for context tracking
        if ($class_node instanceof Node && $class_node->kind === \ast\AST_NAME) {
            $name = $class_node->children['name'] ?? null;
            if (\is_string($name) && \in_array(\strtolower($name), ['self', 'static', 'parent'], true)) {
                $this->handleStaticPropertyAssignmentInLocalScopeByName($node, $property_name);
            }
        }

        $class_node_context = new ContextNode(
            $this->code_base,
            $this->context,
            $class_node
        );
        try {
            $class_list = $class_node_context->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME, Issue::TypeExpectedObjectStaticPropAccess);
        } catch (\Exception) {
            // If we can't figure out what kind of a class
            // this is, don't worry about it
            //
            // Note that CodeBaseException is one possible exception due to invalid code created by the fallback parser, etc.
            return $this->context;
        }

        foreach ($class_list as $clazz) {
            // Check to see if this class has the property
            if (!$clazz->hasPropertyWithName($this->code_base, $property_name)) {
                continue;
            }

            try {
                // Look for static properties with that $property_name
                $property = $clazz->getPropertyByNameInContext(
                    $this->code_base,
                    $property_name,
                    $this->context,
                    true,
                    null,
                    true
                );
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
                return $this->context;
            }

            try {
                return $this->analyzePropAssignment($clazz, $property, $node);
            } catch (RecursionDepthException) {
                return $this->context;
            }
        }

        foreach ($class_list as $clazz) {
            // Don't report the property as undeclared on an interface named only by a
            // `class-string<I>`, whose implementing class may declare it.
            if ($class_node_context->isInterfaceWithUnknownStaticMembers($clazz)) {
                continue;
            }
            $this->emitIssue(
                Issue::UndeclaredStaticProperty,
                $node->lineno ?? 0,
                $property_name,
                (string)$clazz->getFQSEN()
            );
            break;
        }
        // If we hit this part without emitting, we couldn't figure out
        // the class (or whether it declares the property), so we ignore the issue

        return $this->context;
    }

    private function emitTypeModifyImmutableObjectPropertyIssue(Clazz $clazz, string $property_name, Node $node): void
    {
        if (!$clazz->isPHPInternal() && $clazz->hasPropertyWithName($this->code_base, $property_name)) {
            try {
                // Look for static properties with that $property_name
                $property = $clazz->getPropertyByNameInContext(
                    $this->code_base,
                    $property_name,
                    $this->context,
                    false, // is_static
                    null,
                    true
                );
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
                return;
            }
            $property_context = $property->getContext();
        } else {
            // TODO: Should emit a different issue when property doesn't exist.
            // TypeModifyUndeclaredPropertyOfImmutableClass
            $property_context = $clazz->getContext();
        }
        $this->emitIssue(
            Issue::TypeModifyImmutableObjectProperty,
            $node->lineno,
            $clazz->getClasslikeType(),
            $clazz->getFQSEN(),
            $property_name,
            $property_context->getFile(),
            $property_context->getLineNumberStart()
        );
    }

    /**
     * @param Node $node
     * A node of type ast\AST_VAR to analyze as the target of an assignment
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the node
     */
    public function visitVar(Node $node): Context
    {
        try {
            $variable_name = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getVariableName();
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
            return $this->context;
        }
        // Don't analyze variables when we can't determine their names.
        if ($variable_name === '') {
            return $this->context;
        }

        if ($this->context->getScope()->hasVariableWithName($variable_name)) {
            $variable = $this->context->getScope()->getVariableByName($variable_name);
        } else {
            $variable_type = Variable::getUnionTypeOfHardcodedVariableInScopeWithName(
                $variable_name,
                $this->context->isInGlobalScope()
            );
            if ($variable_type) {
                $variable = new Variable(
                    $this->context,
                    $variable_name,
                    $variable_type,
                    0
                );
            } else {
                $variable = null;
            }
        }
        // Check to see if the variable already exists
        if ($variable) {
            // We clone the variable so as to not disturb its previous types
            // as we replace it.
            $variable = clone($variable);

            // Invalidate any stale condition expressions that reference this variable (issue #5301)
            $this->invalidateStaleConditionExpressions($variable_name);

            // If we're assigning to an array element then we don't
            // know what the array structure of the parameter is
            // outside of the scope of this assignment, so we add to
            // its union type rather than replace it.
            if ($this->dim_depth > 0) {
                $old_variable_union_type = $variable->getUnionType();
                if ($this->dim_depth === 1 && $old_variable_union_type->getRealUnionType()->isExclusivelyArray()) {
                    // We're certain of the types of $values, but not of $values[0], so check that the depth is exactly 1.
                    // @phan-suppress-next-line PhanUndeclaredProperty used in unused variable detection - array access to an object might have a side effect
                    $node->phan_is_assignment_to_real_array = true;
                }
                if ($old_variable_union_type->isNull() && $variable->getPhanFlagsHasState(Flags::HAS_REFERENCE)) {
                    // E.g. `$b[0] = &$b;` or `$a = &$b; $b[] = 1;`: a reference defined the variable as null
                    // (or it may have been modified through a reference), and PHP creates the array without any warning.
                    $right_type = $this->typeCheckDimAssignment(UnionType::empty(), $node);
                } else {
                    $right_type = $this->typeCheckDimAssignment($old_variable_union_type, $node);
                }
                $container_may_be_absent = !$this->is_conditional_check && self::typeSetContainsNullOrFalse($old_variable_union_type);
                if ($old_variable_union_type->isEmpty()) {
                    $old_variable_union_type = ArrayType::instance(false)->asPHPDocUnionType();
                }
                // Note: Trying to assign dim offsets to a scalar such as `$x = 2` does not modify the variable.
                $old_variable_union_type = $old_variable_union_type->nonNullableClone();
                // TODO: Make the behavior more precise for $x['a']['b'] = ...; when $x is an array shape.
                if ($this->dim_depth > 1) {
                    $new_union_type = $this->computeTypeOfMultiDimensionalAssignment($old_variable_union_type, $right_type, $container_may_be_absent);
                } elseif ($this->is_conditional_check && !$right_type->isEmpty() && !$right_type->hasTopLevelNonArrayShapeTypeInstances() && $old_variable_union_type->hasPossiblyObjectTypes() && $old_variable_union_type->hasArrayShapeTypeInstances()) {
                    // When a conditional check adds a new array shape field, merge it into
                    // existing shapes rather than adding as a separate union member.
                    // This prevents hasPossiblyObjectTypes() (true for mixed) from causing
                    // shapes to be separated. (fixes #5444)
                    $combined = ArrayType::combineArrayTypesOverriding($right_type, $old_variable_union_type, false);
                    // combineArrayTypesOverriding drops non-array types (e.g. mixed), re-add them
                    $non_array_types = $old_variable_union_type->nonArrayTypes();
                    $phpdoc_types = \array_merge($combined->getTypeSet(), $non_array_types->getTypeSet());
                    // Merge real type sets following the same pattern as withUnionType
                    $old_real = $old_variable_union_type->getRealTypeSet();
                    $new_real = $right_type->getRealTypeSet();
                    if ($old_real && $new_real) {
                        $real_types = $old_real;
                        foreach ($new_real as $type) {
                            foreach ($old_real as $existing) {
                                if ($existing === $type) {
                                    continue 2;
                                }
                            }
                            $real_types[] = $type;
                        }
                    } else {
                        $real_types = [];
                    }
                    $new_union_type = UnionType::of($phpdoc_types, $real_types);
                } elseif ($old_variable_union_type->isEmpty() || $old_variable_union_type->hasPossiblyObjectTypes() || $right_type->hasTopLevelNonArrayShapeTypeInstances() || $right_type->isEmpty()) {
                    $new_union_type = $old_variable_union_type->withUnionType(
                        $right_type
                    );
                    // echo "Combining array shape types $right_type $old_variable_union_type $new_union_type\n";
                } else {
                    $new_union_type = ArrayType::combineArrayTypesOverriding(
                        $right_type,
                        $old_variable_union_type,
                        true
                    );
                }
                // Note that after $x[anything] = anything, $x is guaranteed not to be the empty array.
                // TODO: Handle `$x = 'x'; $s[0] = '0';`
                $this->analyzeSetUnionType($variable, $new_union_type->nonFalseyClone(), $this->assignment_node->children['expr'] ?? null);
            } else {
                // Handle variable-to-variable references (issue #4354)
                // Mark with HAS_REFERENCE flag and get type AFTER marking to ensure literals are erased
                if ($this->assignment_node->kind === ast\AST_ASSIGN_REF && $this->dim_depth === 0) {
                    $this->markVariablesAsReferences($variable, $this->assignment_node->children['expr']);
                    // Get the type of the source variable after marking
                    $source_type = $this->getRefSourceType($this->assignment_node->children['expr']);
                    $this->analyzeSetUnionType($variable, $source_type, $this->assignment_node->children['expr'] ?? null);
                } else {
                    $this->analyzeSetUnionType($variable, $this->right_type, $this->assignment_node->children['expr'] ?? null);
                }
            }

            $this->context->addScopeVariable(
                $variable
            );

            return $this->context;
        }

        // no such variable exists, check for invalid array Dim access
        if ($this->dim_depth > 0) {
            $this->emitIssue(
                Issue::UndeclaredVariableDim,
                $node->lineno ?? 0,
                $variable_name
            );
        }

        $variable = new Variable(
            $this->context,
            $variable_name,
            UnionType::empty(),
            0
        );
        if ($this->dim_depth > 0) {
            // Reduce false positives: If $variable did not already exist, assume it may already have other array fields
            // (e.g. in a loop, or in the global scope)
            // TODO: Don't if this isn't in a loop or the global scope.
            $variable->setUnionType($this->right_type->withType(ArrayType::instance(false)));
        } else {
            // Set that type on the variable
            $variable->setUnionType(
                $this->right_type
            );
            if ($this->assignment_node->kind === ast\AST_ASSIGN_REF) {
                $expr = $this->assignment_node->children['expr'];
                if ($expr instanceof Node && \in_array($expr->kind, [ast\AST_STATIC_PROP, ast\AST_PROP], true)) {
                    try {
                        $property = (new ContextNode(
                            $this->code_base,
                            $this->context,
                            $expr
                        ))->getProperty($expr->kind === ast\AST_STATIC_PROP);
                        $variable = new PassByReferenceVariable(
                            $variable,
                            $property,
                            $this->code_base,
                            $this->context
                        );
                    } catch (IssueException | NodeException) {
                        // Hopefully caught elsewhere
                    }
                } else {
                    // Handle variable-to-variable references (issue #4354)
                    // Mark with HAS_REFERENCE flag so subsequent type operations erase literals
                    $this->markVariablesAsReferences($variable, $expr);
                }
            }
        }

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
        $this->context->addScopeVariable($variable);

        return $this->context;
    }

    /**
     * A condition such as `if ($x['a']['b'])` narrows the type of $x['a'] to array{b:non-empty-mixed}.
     * Where $x['a'] was not known to be an array shape (e.g. it was mixed, array or array<string,mixed>),
     * its other keys are unknown and may exist, so add array types without a shape next to the new shape,
     * as is done when narrowing a variable with `if ($x['b'])`:
     * the typed arrays of $x['a'] (e.g. array<string,mixed>), or `array` if its values are unknown (array, mixed).
     * Without this, reading $x['a']['c'] afterwards would emit PhanTypeInvalidDimOffset.
     * As for a variable, this means that passing $x['a'] where e.g. array{b:string} is expected is not reported.
     *
     * Array shapes of the container are not kept here: they are merged with the new shape by the caller,
     * so that invalid offsets of declared array shapes are still detected.
     *
     * @param Node $container_node the node of the array element being narrowed (a node of kind ast\AST_DIM)
     * @param UnionType $right_type the narrowed type of that array element, an array shape
     */
    private function withConditionalDimContainerFallback(Node $container_node, UnionType $right_type): UnionType
    {
        try {
            $old_container_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $container_node, false);
        } catch (IssueException) {
            return $right_type;
        }
        if ($old_container_type->isEmpty()) {
            // The type of the array element is unknown.
            $old_container_type = MixedType::instance(false)->asPHPDocUnionType();
        }
        $typed_fallback_types = [];
        $has_untyped_fallback = false;
        foreach ($old_container_type->getTypeSet() as $type) {
            if ($type instanceof ArrayShapeType) {
                continue;
            }
            if ($type instanceof GenericArrayInterface) {
                // array<string,int>, list<string>, callable-array, etc.: the keys are unknown, but the types of the values are known.
                $typed_fallback_types[] = $type;
            } elseif ($type instanceof ArrayType || $type instanceof MixedType) {
                // array, mixed, non-empty-mixed, etc.: neither the keys nor the types of the values are known.
                $has_untyped_fallback = true;
            }
        }
        if ($typed_fallback_types) {
            // Not empty, like the shape they are added to, which has the key that was checked
            // (also for conditions such as is_null($x['a']['b']), as for variables).
            $right_type = $right_type->withUnionType(UnionType::of($typed_fallback_types)->nonFalseyClone());
        }
        if ($has_untyped_fallback) {
            // Use the plain array type, not non-empty-array<mixed,mixed>: reading a key that is in no array shape of
            // array{b:...}|array is mixed, while for array{b:...}|non-empty-array<mixed,mixed>
            // the types of the fields of the shape would be used (e.g. the type of $x['a']['d']['c'] after is_string($x['a']['b']['c'])).
            $right_type = $right_type->withType(ArrayType::instance(false));
        }
        return $right_type;
    }

    /**
     * A write such as `$x['a']['b'] = 1` sets the type of $x['a'] to array{b:1}.
     * Where $x['a'] already existed and was not known to be an array shape (e.g. it was mixed, array or array<string,mixed>),
     * it keeps its other keys, so add its typed arrays, or `array` if its values are unknown, next to the new shape.
     * Without this, reading $x['a']['c'] afterwards would emit PhanTypeInvalidDimOffset.
     *
     * The old type of $x['a'] is taken from the type of the variable $x and the keys of the path,
     * not by analyzing the expression $x['a'], which could emit issues on the line of the write.
     * Nothing is added if $x['a'] did not exist, or was null or false: the write then creates a new array.
     * Array shapes of the old type are merged with the new shape by the caller,
     * so that invalid offsets of declared array shapes are still detected.
     *
     * @param Node $container_node the node of the array element that is written to (a node of kind ast\AST_DIM)
     * @param UnionType $right_type the type of that array element after the write, an array shape
     */
    private function withWrittenDimContainerFallback(Node $container_node, UnionType $right_type): UnionType
    {
        // Whether the write may create the element: the element or an array on the path to it may be null, false or undefined.
        $element_may_be_created = false;
        $old_container_types = $this->getOldTypeSetOfDimWithLiteralKeys($container_node, $element_may_be_created);
        if (!$old_container_types) {
            return $right_type;
        }
        $phpdoc_fallback_types = [];
        $has_untyped_fallback = false;
        $has_callable_array = false;
        $old_shapes = [];
        foreach ($old_container_types as $type) {
            if ($type instanceof ArrayShapeType) {
                $element_may_be_created = true;
                $old_shapes[] = $type;
                continue;
            }
            if ($type instanceof CallableArrayType) {
                // A callable-array has exactly the keys 0 and 1, so it is merged with the write below.
                $has_callable_array = true;
            } elseif ($type instanceof GenericArrayInterface) {
                // array<string,int>, list<string>, etc.: the keys are unknown, but the types of the values are known.
                $phpdoc_fallback_types[] = $type;
            } elseif ($type instanceof ArrayType || $type instanceof MixedType) {
                // array, mixed, non-empty-mixed, etc.: neither the keys nor the types of the values are known.
                $has_untyped_fallback = true;
            }
        }
        if (!$phpdoc_fallback_types && !$has_untyped_fallback && !$has_callable_array) {
            return $right_type;
        }
        $has_other_fallback = $phpdoc_fallback_types || $has_untyped_fallback;
        if ($phpdoc_fallback_types) {
            // After the write, the array element has the key that was written, so it is not empty.
            // (Not for an empty list: an empty union type is unknown, and would become non-empty-mixed.)
            $phpdoc_fallback_types = UnionType::of($phpdoc_fallback_types)->nonFalseyClone()->getTypeSet();
        }
        if ($has_untyped_fallback) {
            // As for conditions, use the plain array type: reading a key that is in no array shape of array{b:...}|array is mixed.
            $phpdoc_fallback_types[] = ArrayType::instance(false);
        }
        if ($has_callable_array) {
            // Writing to a callable-array (an object or class name and a method name) gives an array shape with those two keys,
            // which is no longer callable if a key other than 0 and 1 was written.
            $callable_array_fields = [
                0 => UnionType::of([ObjectType::instance(false), StringType::instance(false)]),
                1 => StringType::instance(false)->asPHPDocUnionType(),
            ];
            $merged_shapes = [];
            foreach ($right_type->getTypeSet() as $type) {
                if ($type instanceof ArrayShapeType) {
                    $merged_shapes[] = ArrayShapeType::fromFieldTypes(\array_replace($callable_array_fields, $type->getFieldTypes()), false);
                }
            }
            if ($merged_shapes && !$element_may_be_created && !$has_other_fallback) {
                // The element was a non-null callable-array, so it keeps the keys 0 and 1:
                // the shape with only the written key is not possible.
                return UnionType::of($merged_shapes, \array_merge($right_type->getRealTypeSet(), [ArrayType::instance(false)]));
            }
            \array_push($phpdoc_fallback_types, ...$merged_shapes);
        }
        // The array shapes of the old element (e.g. array{a:int,b:string} of array<array{a:int,b:string}|array<string,\stdClass>>)
        // keep their other keys too. When they are mixed with other array types, the caller does not merge them with the write,
        // so merge them here: $x[0]['a'] = 1 gives array{a:1,b:string}.
        foreach ($old_shapes as $old_shape) {
            foreach ($right_type->getTypeSet() as $type) {
                if ($type instanceof ArrayShapeType) {
                    $phpdoc_fallback_types[] = ArrayShapeType::fromFieldTypes(\array_replace($old_shape->getFieldTypes(), $type->getFieldTypes()), false);
                }
            }
        }
        // Only `array` is known at runtime, keep the real types of the result.
        return $right_type->withUnionType(UnionType::of($phpdoc_fallback_types, [ArrayType::instance(false)]));
    }

    /**
     * Returns the PHPDoc types that the array element $node (e.g. $x['a']['b']) had before an assignment to one of its keys,
     * taken from the type of the variable $x, or null if they are not known:
     * the keys on the path are not all literals, or the root of the path is not a variable.
     * Returns an empty list if the element did not exist or was null or false (the write then creates a new array).
     *
     * @param Node $node a node of kind ast\AST_DIM
     * @param bool $may_be_created set to whether the variable, an array on the path or the element may be undefined, null or false:
     * the write then creates the element (and not only when the returned list is empty)
     * @return ?list<Type> the types of the element, with mixed for an element of unknown type
     */
    private function getOldTypeSetOfDimWithLiteralKeys(Node $node, bool &$may_be_created = false): ?array
    {
        $keys = [];
        while ($node->kind === ast\AST_DIM) {
            $dim = $node->children['dim'];
            if (!\is_int($dim) && !\is_string($dim)) {
                return null;
            }
            $keys[] = self::normalizeScalarArrayKey($dim);
            $node = $node->children['expr'];
            if (!$node instanceof Node) {
                return null;
            }
        }
        if ($node->kind !== ast\AST_VAR) {
            return null;
        }
        $variable_name = $node->children['name'];
        if (!\is_string($variable_name)) {
            return null;
        }
        $keys = \array_reverse($keys);
        $scope = $this->context->getScope();
        $global_name = $variable_name === 'GLOBALS' ? ($keys[0] ?? null) : null;
        if (\is_string($global_name) && $scope->hasGlobalVariableWithName($global_name)) {
            // $GLOBALS['cache'] is the global variable $cache: use its type, not the element type of $GLOBALS (mixed),
            // so that after `$GLOBALS['cache'] = []`, a write to $GLOBALS['cache']['ok'] keeps $cache a closed array shape.
            $union_type = $scope->getGlobalVariableByName($global_name)->getUnionType();
            \array_shift($keys);
        } elseif ($scope->hasVariableWithName($variable_name)) {
            $union_type = $scope->getVariableByName($variable_name)->getUnionType();
        } else {
            $union_type = Variable::getUnionTypeOfHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope());
            if (!$union_type) {
                // The variable is undefined, the write creates it.
                return [];
            }
        }
        $types = $union_type->isEmpty() ? [MixedType::instance(false)] : $union_type->getTypeSet();
        $may_be_created = $union_type->isPossiblyUndefined() || self::hasTypeReplacedByWrite($types);
        foreach ($keys as $key) {
            $may_be_absent = false;
            $types = self::getTypeSetOfOldElement($types, $key, $may_be_absent);
            if (!$types) {
                return [];
            }
            $may_be_created = $may_be_created || $may_be_absent || self::hasTypeReplacedByWrite($types);
        }
        return $types;
    }

    /**
     * @param list<Type> $types
     * @return bool whether a value of one of the types is replaced by a new array when one of its keys is written: null or false.
     * (`$x[0][1] = 'm'` on a null $x gives array{0:array{1:'m'}}, without the other keys that an element of a non-null $x has)
     */
    private static function hasTypeReplacedByWrite(array $types): bool
    {
        foreach ($types as $type) {
            if ($type instanceof NullType || $type instanceof FalseType || $type instanceof BoolType) {
                return true;
            }
            // (mixed is not counted: its keys are unknown anyway)
            if ($type->isNullable() && !($type instanceof MixedType)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the PHPDoc types of the element $key of an array of a type in $types, with mixed for an element of unknown type.
     * The types that cannot contain an array with other keys (null, false, scalars and objects) contribute nothing.
     *
     * @param list<Type> $types
     * @param bool $may_be_absent set to true if the element may be absent from an array shape that is used: the field is optional, or only some of the array shapes have it
     * @return list<Type>
     */
    private static function getTypeSetOfOldElement(array $types, int|string $key, bool &$may_be_absent = false): array
    {
        // As when reading $x['a'] (UnionTypeVisitor::resolveArrayShapeElementTypesForOffset()), if an array shape has the key,
        // the types of that field are used and the other array types are ignored:
        // after `$x['a'] = []` on an `array $x`, $x is array{a:array{}}|non-empty-array<mixed,mixed>, and $x['a'] is array{}.
        $result = [];
        $has_shape_without_key = false;
        foreach ($types as $type) {
            if ($type instanceof ArrayShapeType) {
                $field_type = $type->getFieldTypes()[$key] ?? null;
                if ($field_type !== null) {
                    if ($field_type->isPossiblyUndefined()) {
                        // An optional field, e.g. array{callbacks?:array<callable-array>}
                        $may_be_absent = true;
                    }
                    \array_push($result, ...($field_type->isEmpty() ? [MixedType::instance(false)] : $field_type->getTypeSet()));
                } else {
                    $has_shape_without_key = true;
                }
            }
        }
        if ($result) {
            if ($has_shape_without_key) {
                // e.g. array{a:int}|array{callbacks:array<callable-array>}
                $may_be_absent = true;
            }
            return $result;
        }
        // PHP converts a string key such as '5' to the integer 5, but not '05', '-0', '+5' or a number too large for an int.
        $is_int_key = \is_int($key) || (string)(int)$key === $key;
        foreach ($types as $type) {
            if ($type instanceof ArrayShapeType) {
                continue;
            }
            if ($type instanceof GenericArrayInterface) {
                if ($type instanceof ListType) {
                    if (!$is_int_key || (int)$key < 0) {
                        continue;
                    }
                } elseif (($type->getKeyType() & ($is_int_key ? GenericArrayType::KEY_INT : GenericArrayType::KEY_STRING)) === 0) {
                    // e.g. a string key of an array<int,T>.
                    continue;
                }
                $element_type = $type->genericArrayElementUnionType();
                \array_push($result, ...($element_type->isEmpty() ? [MixedType::instance(false)] : $element_type->getTypeSet()));
            } elseif ($type instanceof ArrayType || $type instanceof MixedType) {
                $result[] = MixedType::instance(false);
            }
        }
        return $result;
    }

    private function computeTypeOfMultiDimensionalAssignment(UnionType $old_union_type, UnionType $right_type, bool $outer_may_be_absent = false): UnionType
    {
        if ($this->dim_depth <= 1) {
            throw new AssertionError("Expected dim_depth > 1, got $this->dim_depth");
        }
        if (!$right_type->hasTopLevelArrayShapeTypeInstances() || !$old_union_type->hasGenericArray()) {
            return $old_union_type->withUnionType($right_type);
        }

        return UnionType::of(
            $this->computeTypeSetOfMergedArrayShapeTypes($old_union_type->getTypeSet(), $right_type->getTypeSet(), $this->dim_depth, false, !$outer_may_be_absent, false, false),
            $this->computeTypeSetOfMergedArrayShapeTypes($old_union_type->getRealTypeSet(), $right_type->getRealTypeSet(), $this->dim_depth, true, false, false, false)
        );
    }

    /**
     * @param list<Type> $old_type_set may contain ArrayShapeType or GenericArrayType instances
     * @param list<Type> $new_type_set may contain ArrayShapeType instances
     * @return list<Type> possibly containing duplicates.
     */
    private function computeTypeSetOfMergedArrayShapeTypes(
        array $old_type_set,
        array $new_type_set,
        int $dim_depth,
        bool $is_real,
        bool $allow_generic_fallback = true,
        bool $container_may_be_absent = false,
        bool $from_generic_fallback = false
    ): array {
        if ($is_real) {
            if (!$old_type_set || !$new_type_set) {
                return [];
            }
        }
        $result = [];
        $new_array_shape_types = [];
        foreach ($new_type_set as $type) {
            if ($type instanceof ArrayShapeType) {
                $new_array_shape_types[] = $type;
            } else {
                $result[] = $type;
            }
        }
        if (!$new_array_shape_types) {
            return \array_merge($old_type_set, $new_type_set);
        }
        $old_array_shape_types = [];
        $old_generic_array_types = [];
        foreach ($old_type_set as $type) {
            if ($type instanceof ArrayShapeType) {
                $old_array_shape_types[] = $type;
                continue;
            }
            if ($type instanceof GenericArrayType) {
                $old_generic_array_types[] = $type;
            } else {
                // The container may also be an object (e.g. an ArrayAccess), a plain array, mixed or a scalar,
                // whose elements need not have the fields of the element shape of a generic array alternative:
                // do not take the old fields of an element from that shape.
                $allow_generic_fallback = false;
            }
            $result[] = $type;
        }
        if ($is_real || !$allow_generic_fallback) {
            $old_generic_array_types = [];
        }
        if (!$old_array_shape_types && !$old_generic_array_types) {
            return \array_merge($old_type_set, $new_type_set);
        }
        // Postcondition: $new_array_shape_types is non-empty, and at least one of $old_array_shape_types or $old_generic_array_types is non-empty.
        $new_array_shape_type = ArrayShapeType::union($new_array_shape_types);
        $combined_fields = $old_array_shape_types
            ? ArrayShapeType::union($old_array_shape_types)->getFieldTypes()
            : [];
        if (!$is_real && $container_may_be_absent) {
            foreach ($combined_fields as $k => $v) {
                $combined_fields[$k] = $v->withIsPossiblyUndefined(true);
            }
        }
        foreach ($new_array_shape_type->getFieldTypes() as $field => $field_type) {
            $old_field_type = $combined_fields[$field] ?? null;
            $field_from_generic_fallback = $from_generic_fallback;
            if ($old_field_type === null && $old_generic_array_types && $field_type->hasTopLevelArrayShapeTypeInstances()) {
                $old_field_type = self::genericArrayElementTypeForField($old_generic_array_types, $field, $dim_depth);
                if ($old_field_type !== null) {
                    $field_from_generic_fallback = true;
                }
            } elseif (
                $old_field_type !== null
                && $field_from_generic_fallback
                && !$field_type->hasTopLevelArrayShapeTypeInstances()
                && $old_field_type->hasTopLevelArrayShapeTypeInstances()
            ) {
                // The write to this field is an append or uses a key that is not a literal ($x[0]['field'][] = ...).
                // Merging the assigned type with an array shape taken from the element type of the generic array
                // would turn that shape into a generic array of all its field types: use the assigned type.
                $old_field_type = null;
            }
            if ($old_field_type) {
                $field_may_be_absent = false;
                if (!$is_real) {
                    // A condition such as isset($x['field']['key']) or $x['field']['key'] !== null proves that the field exists
                    // and is not null or false, so its other keys keep the types they had. A condition such as
                    // is_null($x['field']['key']) does not prove it, but is treated the same way.
                    $field_may_be_absent = !$this->is_conditional_check
                        && ($container_may_be_absent || self::fieldMayBeAbsentNullOrFalse($old_field_type));
                    // In a condition, the declared shapes of a field are merged with all their keys kept as they are:
                    // the condition may rule out the shapes without a key (array{a:null,c:int} for $x['field']['a'] !== null),
                    // and the merge cannot tell. Shapes taken from the element type of a generic array are still normalized.
                    if (!$this->is_conditional_check || $field_from_generic_fallback) {
                        $adjusted_types = self::normalizePreservedShapes(
                            $old_field_type->getTypeSet(),
                            $dim_depth < 3 && $field_may_be_absent,
                            $field_from_generic_fallback
                        );
                        if ($adjusted_types !== null) {
                            $old_field_type = $adjusted_types ? UnionType::of($adjusted_types, $old_field_type->getRealTypeSet()) : null;
                        }
                    }
                }
                // After a write, the containers on the path to the written key are neither null nor false.
                // After a condition, that holds only if the condition is false when the key is read through a null
                // or false container (isset(), !== null), not for empty() or is_null().
                $containers_exist = !$this->is_conditional_check
                    || ($old_field_type !== null && self::conditionRejectsNullKey($old_field_type, $field_type, $dim_depth - 1));
                if ($old_field_type === null) {
                    $combined_fields[$field] = $field_type;
                } elseif ($dim_depth >= 3) {
                    $kept_old_field = $is_real || !$containers_exist ? $old_field_type : self::typesAfterDimWrite($old_field_type);
                    $combined_fields[$field] = UnionType::of(
                        $this->computeTypeSetOfMergedArrayShapeTypes(
                            $kept_old_field->getTypeSet(),
                            $field_type->getTypeSet(),
                            $dim_depth - 1,
                            $is_real,
                            !$is_real && $allow_generic_fallback && !$field_may_be_absent,
                            $field_may_be_absent,
                            $field_from_generic_fallback
                        ),
                        $is_real ? [] : $this->computeTypeSetOfMergedArrayShapeTypes(
                            $old_field_type->getRealTypeSet(),
                            $field_type->getRealTypeSet(),
                            $dim_depth - 1,
                            true,
                            false,
                            false,
                            false
                        )
                    );
                } elseif ($old_field_type->hasPossiblyObjectTypes() || $old_field_type->isEmpty()) {
                    // The field may be an object (e.g. an ArrayAccess), mixed or a template type, which can still be
                    // an object after the assignment: keep those types, and override the fields of its array types.
                    $kept_non_array_types = $old_field_type->nonArrayTypes();
                    if ($containers_exist) {
                        $kept_non_array_types = self::typesAfterDimWrite($kept_non_array_types);
                    }
                    $combined_fields[$field] = $kept_non_array_types->withUnionType(
                        ArrayType::combineArrayTypesOverriding($field_type, $old_field_type->arrayTypes(), true)
                    );
                } else {
                    $combined_fields[$field] = ArrayType::combineArrayTypesOverriding($field_type, $old_field_type, true);
                }
            } else {
                $combined_fields[$field] = $field_type;
            }
        }
        $result[] = ArrayShapeType::fromFieldTypes($combined_fields, false);
        return $result;
    }

    /**
     * Returns the combined PHPDoc element type of compatible generic array types in $generic_array_types
     * when every compatible element is a clean non-nullable array shape (or, for $dim_depth >= 3, a
     * non-nullable generic array). Bails out when any compatible element is nullable, scalar, generic
     * array (at depth 2), or object so a synthesized shape never shadows non-shape alternatives.
     *
     * @param non-empty-list<GenericArrayType> $generic_array_types
     */
    private static function genericArrayElementTypeForField(array $generic_array_types, int|string $field, int $dim_depth): ?UnionType
    {
        $field_key_type = \is_int($field) ? GenericArrayType::KEY_INT : GenericArrayType::KEY_STRING;
        $element_types = [];
        $has_shape = false;
        $has_generic = false;
        foreach ($generic_array_types as $generic_type) {
            if ($generic_type instanceof ListType) {
                if (!\is_int($field) || $field < 0) {
                    continue;
                }
            } elseif (($generic_type->getKeyType() & $field_key_type) === 0) {
                continue;
            }
            $element_type = $generic_type->genericArrayElementType();
            if ($element_type->isNullable()) {
                return null;
            }
            if ($element_type instanceof ArrayShapeType) {
                $has_shape = true;
                $element_types[] = $element_type;
            } elseif ($dim_depth >= 3 && $element_type instanceof GenericArrayType) {
                $has_generic = true;
                $element_types[] = $element_type;
            } else {
                return null;
            }
        }
        if (!$element_types || ($has_shape && $has_generic)) {
            return null;
        }
        return UnionType::of($element_types);
    }

    /**
     * Returns the PHPDoc types that a container of type $union_type can have after a successful write to one of its keys.
     * PHP replaces null and false (deprecated since PHP 8.1) with a new array, and throws an Error for true,
     * so the result contains none of null, false, true or bool.
     * The result is empty if $union_type only contains those types, and non-null-mixed if $union_type is empty.
     */
    private static function typesAfterDimWrite(UnionType $union_type): UnionType
    {
        if ($union_type->isEmpty()) {
            return $union_type->nonNullableClone();
        }
        $result = $union_type->nonFalseClone()->nonTrueClone();
        if ($result->isEmpty() || $result->isNull()) {
            return UnionType::empty();
        }
        return $result->nonNullableClone();
    }

    /**
     * Returns true if a condition on the key $depth levels below a field is false when that key is read through
     * a container that is null, false or true (PHP reads null then), so that the containers on the path exist.
     * $new_field_type is the type of the field after the condition, with one array shape per level down to the
     * narrowed type of the key; $old_field_type is the type of the field before it.
     * This is known when the condition removed null from the type of the key in the old array shapes. The narrowed
     * type alone does not show it: empty() narrows an int key to 0, and !is_array() narrows an array|int key to int.
     * Returns false for a key that is not a literal, and if a container on the path may be another type than an
     * array shape, null or a bool (an object, a generic array or mixed).
     */
    private static function conditionRejectsNullKey(UnionType $old_field_type, UnionType $new_field_type, int $depth): bool
    {
        $old_key_type = $old_field_type;
        $new_key_type = $new_field_type;
        for (; $depth > 0; $depth--) {
            $new_types = $new_key_type->getTypeSet();
            $new_shape = \count($new_types) === 1 ? \reset($new_types) : null;
            if (!$new_shape instanceof ArrayShapeType) {
                return false;
            }
            $new_fields = $new_shape->getFieldTypes();
            if (\count($new_fields) !== 1) {
                return false;
            }
            $key = \array_key_first($new_fields);
            $new_key_type = $new_fields[$key];
            $old_types = [];
            foreach ($old_key_type->getTypeSet() as $type) {
                if ($type instanceof ArrayShapeType) {
                    $old_field = $type->getFieldTypes()[$key] ?? null;
                    if ($old_field !== null) {
                        \array_push($old_types, ...$old_field->getTypeSet());
                    }
                } elseif (!($type instanceof NullType) && !$type->isInBoolFamily()) {
                    return false;
                }
            }
            $old_key_type = UnionType::of($old_types);
        }
        return $old_key_type->containsNullable() && !$new_key_type->isEmpty() && !$new_key_type->containsNullable();
    }

    private static function fieldMayBeAbsentNullOrFalse(UnionType $union_type): bool
    {
        return $union_type->isPossiblyUndefined() || self::typeSetContainsNullOrFalse($union_type);
    }

    /**
     * Returns true if the PHPDoc type set contains null, false, bool or a nullable array type.
     * PHP replaces a container that is null or false with a new array on a write to one of its keys.
     */
    private static function typeSetContainsNullOrFalse(UnionType $union_type): bool
    {
        foreach ($union_type->getTypeSet() as $type) {
            if ($type instanceof NullType || $type instanceof FalseType || \get_class($type) === BoolType::class || ($type instanceof ArrayType && $type->isNullable())) {
                return true;
            }
        }
        return false;
    }

    /**
     * Adjusts preserved ArrayShapeType instances in $types before a nested assignment merge:
     * - When $from_generic_fallback is true and $types contains a shape and a type that is neither a shape
     *   nor null, returns an empty list. The caller then drops all old types of the field and uses only
     *   the assigned type, so that a shape merged with the assigned keys does not hide those other types.
     * - If $all_fields_optional is true (the container may have been absent or null), marks all
     *   preserved shape fields possibly undefined.
     * - If $types contains multiple shapes, marks any field not required in every shape as possibly
     *   undefined so merging shapes does not promote branch-specific keys to required.
     *
     * @param list<Type> $types
     * @return ?list<Type> null if no adjustment was needed, an empty list if the old types of the field are not used
     */
    private static function normalizePreservedShapes(array $types, bool $all_fields_optional, bool $from_generic_fallback): ?array
    {
        $shapes = [];
        $has_non_shape_alternative = false;
        foreach ($types as $i => $type) {
            if ($type instanceof ArrayShapeType) {
                $shapes[$i] = $type;
            } elseif (!($type instanceof NullType)) {
                $has_non_shape_alternative = true;
            }
        }
        if (!$shapes) {
            return null;
        }
        if ($from_generic_fallback && $has_non_shape_alternative) {
            return [];
        }
        $shape_count = \count($shapes);
        if (!$all_fields_optional && $shape_count === 1) {
            return null;
        }
        // A field stays required only if it is required in every shape:
        // count, per key, the shapes in which the key is required.
        $required_count = [];
        if (!$all_fields_optional) {
            foreach ($shapes as $shape) {
                foreach ($shape->getFieldTypes() as $key => $field_type) {
                    if (!$field_type->isPossiblyUndefined()) {
                        $required_count[$key] = ($required_count[$key] ?? 0) + 1;
                    }
                }
            }
        }
        foreach ($shapes as $i => $shape) {
            $field_types = [];
            foreach ($shape->getFieldTypes() as $key => $field_type) {
                $is_optional = $all_fields_optional || $field_type->isPossiblyUndefined() || ($required_count[$key] ?? 0) < $shape_count;
                $field_types[$key] = $is_optional ? $field_type->withIsPossiblyUndefined(true) : $field_type;
            }
            $types[$i] = ArrayShapeType::fromFieldTypes($field_types, false);
        }
        return $types;
    }

    /**
     * @param UnionType $assign_type - The type which is being added to
     * @return UnionType - Usually the unmodified UnionType. Sometimes, the adjusted type, e.g. for string modification.
     */
    public function typeCheckDimAssignment(UnionType $assign_type, Node $node): UnionType
    {
        static $int_or_string_type = null;
        static $string_array_type = null;
        static $simple_xml_element_type = null;

        if ($int_or_string_type === null) {
            $int_or_string_type = UnionType::fromFullyQualifiedPHPDocString('int|string');
            $string_array_type = UnionType::fromFullyQualifiedPHPDocString('string[]');
            $simple_xml_element_type =
                Type::fromNamespaceAndName('\\', 'SimpleXMLElement', false);
        }
        $dim_type = $this->dim_type;
        $right_type = $this->right_type;

        // Sanity check: Don't add list<T> to a property that isn't list<T>
        // unless it has 1 or more array types and all are list<T>
        $right_type = self::normalizeListTypesInDimAssignment($assign_type, $right_type);

        if ($assign_type->isEmpty() || ($assign_type->hasGenericArray() && !$assign_type->hasArrayAccess($this->code_base))) {
            // For empty union types or 'array', expect the provided dimension to be able to cast to int|string
            if ($dim_type && !$dim_type->isEmpty() && !$dim_type->canCastToUnionType($int_or_string_type, $this->code_base)) {
                $this->emitIssue(
                    Issue::TypeMismatchDimAssignment,
                    $node->lineno,
                    (string)$assign_type,
                    (string)$dim_type,
                    (string)$int_or_string_type
                );
            }
            return $right_type;
        }
        $assign_type_resolved = $assign_type->withStaticResolvedInContext($this->context);
        //echo "$assign_type_expanded : " . json_encode($assign_type_expanded->hasArrayLike()) . "\n";

        // TODO: Better heuristic to deal with false positives on ArrayAccess subclasses
        if ($assign_type_resolved->hasArrayAccess($this->code_base) && !$assign_type_resolved->hasGenericArray()) {
            return UnionType::empty();
        }

        if (!$assign_type_resolved->hasArrayLike($this->code_base)) {
            if ($assign_type->hasNonNullStringType()) {
                // Are we assigning to a variable/property of type 'string' (with no ArrayAccess or array types)?
                if (\is_null($dim_type)) {
                    $this->emitIssue(
                        Issue::TypeMismatchDimEmpty,
                        $node->lineno ?? 0,
                        (string)$assign_type,
                        'int'
                    );
                } elseif (!$dim_type->isEmpty() && !$dim_type->hasNonNullIntType()) {
                    $this->emitIssue(
                        Issue::TypeMismatchDimAssignment,
                        $node->lineno,
                        (string)$assign_type,
                        (string)$dim_type,
                        'int'
                    );
                } else {
                    if ($right_type->canCastToUnionType($string_array_type, $this->code_base)) {
                        // e.g. $a = 'aaa'; $a[0] = 'x';
                        // (Currently special casing this, not handling deeper dimensions)
                        return StringType::instance(false)->asPHPDocUnionType();
                    }
                }
            } elseif (!$assign_type->hasTypeMatchingCallback(static function (Type $type) use ($simple_xml_element_type): bool {
                return !$type->isNullableLabeled() && ($type instanceof MixedType || $type === $simple_xml_element_type);
            })) {
                // Imitate the check in UnionTypeVisitor, don't warn for mixed (but warn for `?mixed`), etc.
                $this->emitIssue(
                    Issue::TypeArraySuspicious,
                    $node->lineno,
                    ASTReverter::toShortString($node),
                    (string)$assign_type
                );
            }
        }
        return $right_type;
    }

    private static function normalizeListTypesInDimAssignment(UnionType $assign_type, UnionType $right_type): UnionType
    {
        // Offsets of $can_cast:
        // 0. lazily computed: True if list types should be kept as-is.
        // 1. lazily computed: Should this cast from a regular array to an associative array?
        $can_cast = [];
        /**
         * @param list<Type> $type_set
         * @return list<Type> with top level list converted to non-empty-array. May contain duplicates.
         */
        $map_type_set = static function (array $type_set) use ($assign_type, &$can_cast): array {
            foreach ($type_set as $i => $type) {
                if ($type instanceof ListType) {
                    $can_cast[0] ??= $assign_type->hasTypeMatchingCallback(static function (Type $other_type): bool {
                        if (!$other_type instanceof ArrayType) {
                            return false;
                        }
                        if ($other_type instanceof ListType) {
                            return true;
                        }
                        if ($other_type instanceof ArrayShapeType && $other_type->canCastToList()) {
                            return true;
                        }
                        return false;
                    });
                    $result = $can_cast[0];
                    if ($result) {
                        continue;
                    }
                    $type_set[$i] = NonEmptyGenericArrayType::fromElementType($type->genericArrayElementType(), $type->isNullable(), $type->getKeyType());
                } elseif ($type instanceof GenericArrayType) {
                    $can_cast[1] ??= $assign_type->hasTypeMatchingCallback(static function (Type $other_type): bool {
                        if (!$other_type instanceof ArrayType) {
                            return false;
                        }
                        if ($other_type instanceof AssociativeArrayType) {
                            return true;
                        }
                        if ($other_type instanceof ArrayShapeType && $other_type->canCastToList()) {
                            return true;
                        }
                        return false;
                    });
                    $result = $can_cast[1];
                    if (!$result) {
                        continue;
                    }
                    $type_set[$i] = NonEmptyAssociativeArrayType::fromElementType($type->genericArrayElementType(), $type->isNullable(), $type->getKeyType());
                }
            }
            return $type_set;
        };
        $new_type_set = $map_type_set($right_type->getTypeSet());
        $new_real_type_set = $map_type_set($right_type->getRealTypeSet());
        if (\count($can_cast) === 0) {
            return $right_type;
        }
        return UnionType::of($new_type_set, $new_real_type_set);
        // echo "Converting $right_type to $assign_type: $result\n";
    }

    /**
     * @param Node $node
     * A node to analyze as the target of an assignment of type AST_REF (found only in foreach)
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the node
     */
    public function visitRef(Node $node): Context
    {
        // Note: AST_REF is only ever generated in AST_FOREACH, so this should be fine.
        $var = $node->children['var'];
        if ($var instanceof Node) {
            return $this->__invoke($var);
        }
        $this->emitIssue(
            Issue::Unanalyzable,
            $node->lineno
        );
        return $this->context;
    }

    /**
     * Get the type of the source variable in a reference assignment after marking (issue #4354)
     *
     * @param Node|string|int|float|null $expr The right-hand side expression
     * @return UnionType The type of the source variable
     */
    private function getRefSourceType(Node|string|int|float|null $expr): UnionType
    {
        if (!($expr instanceof Node && $expr->kind === ast\AST_VAR)) {
            return UnionType::empty();
        }

        $source_var_name = $expr->children['name'];
        if (!\is_string($source_var_name)) {
            return UnionType::empty();
        }

        $scope = $this->context->getScope();
        if ($scope->hasVariableWithName($source_var_name)) {
            return $scope->getVariableByName($source_var_name)->getUnionType();
        }

        return UnionType::empty();
    }

    /**
     * Invalidate any stored conditional expressions (phan_condition_expr) that reference the given variable.
     * This is necessary because when a variable is reassigned, any cached condition expressions that
     * reference it become stale and would cause incorrect type narrowing.
     * See issue #5301 for details.
     *
     * @param string $assigned_var_name The name of the variable that was just assigned
     */
    private function invalidateStaleConditionExpressions(string $assigned_var_name): void
    {
        $scope = $this->context->getScope();
        foreach ($scope->getVariableMap() as $var) {
            // Check if this variable has a cached condition expression
            if (!isset($var->phan_condition_expr)) {
                continue;
            }
            $expr_node = $var->phan_condition_expr;
            if (!($expr_node instanceof Node)) {
                continue;
            }

            // If the stored expression references the variable being assigned, clear it
            if (PostOrderAnalysisVisitor::exprReferencesVariable($expr_node, $assigned_var_name)) {
                unset($var->phan_condition_expr);
            }
        }
    }

    /**
     * Mark both variables in a reference assignment with HAS_REFERENCE flag (issue #4354)
     * This ensures that analyzeSetUnionType will erase literal types automatically.
     * Also erase existing literal types on both variables.
     *
     * @param Variable $variable The left-hand side variable
     * @param Node|string|int|float|null $expr The right-hand side expression
     */
    private function markVariablesAsReferences(Variable $variable, Node|string|int|float|null $expr): void
    {
        if (!($expr instanceof Node && $expr->kind === ast\AST_VAR)) {
            return;
        }

        // Mark the left-hand variable and erase any existing literal types
        $variable->enablePhanFlagBits(Flags::HAS_REFERENCE);
        $variable->setUnionType($variable->getUnionType()->asNonLiteralType());

        // Also mark the source variable
        // Create the variable if it doesn't exist yet to ensure the flag is set
        $source_var_name = $expr->children['name'];
        if (!\is_string($source_var_name)) {
            return;
        }

        $scope = $this->context->getScope();
        if ($scope->hasVariableWithName($source_var_name)) {
            $source_variable = $scope->getVariableByName($source_var_name);
        } else {
            // Variable doesn't exist yet (e.g., $ref =& $x; $x = 42;)
            // Create it now so we can mark it with HAS_REFERENCE
            $source_variable = new Variable(
                $this->context->withLineNumberStart($expr->lineno ?? 0),
                $source_var_name,
                UnionType::empty(),
                0
            );
            $scope->addVariable($source_variable);
        }
        $source_variable->enablePhanFlagBits(Flags::HAS_REFERENCE);
        $source_variable->setUnionType($source_variable->getUnionType()->asNonLiteralType());
    }
}
