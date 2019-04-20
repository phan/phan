<?php declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use ast;
use ast\Node;
use Closure;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\Element;
use Phan\AST\Visitor\FlagVisitorImplementation;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\LiteralIntType;
use Phan\Language\Type\LiteralStringType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;

use function is_int;

/**
 * This implements Phan's analysis of the type of binary operators (Node->kind=ast\AST_BINARY_OP).
 * The visit* method invoked is based on Node->flags.
 *
 * This returns the union type of the binary operator.
 * It emits issues as a side effect, usually based on $should_catch_issue_exception
 *
 * TODO: Improve analysis of bitwise operations, warn if non-int is provided and consistently return int if it's guaranteed
 */
final class BinaryOperatorFlagVisitor extends FlagVisitorImplementation
{

    /**
     * @var CodeBase The code base within which we're operating
     */
    private $code_base;

    /**
     * @var Context The context in which we are determining the union type of the result of a binary operator
     */
    private $context;

    /**
     * @var bool should we catch issue exceptions while analyzing and proceed with the best guess at the resulting union type?
     * If false, exceptions will be propagated to the caller.
     */
    private $should_catch_issue_exception;

    /**
     * Create a new BinaryOperatorFlagVisitor
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        bool $should_catch_issue_exception = false
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
        $this->should_catch_issue_exception = $should_catch_issue_exception;
    }

    /**
     * @param Node $node
     * A node to visit
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function __invoke(Node $node)
    {
        return $this->{Element::VISIT_BINARY_LOOKUP_TABLE[$node->flags] ?? 'handleMissing'}($node);
    }

    /**
     * @throws AssertionError
     * @suppress PhanUnreferencedPrivateMethod this is referenced by __invoke
     */
    private function handleMissing(Node $node)
    {
        throw new AssertionError("All flags must match. Found " . Element::flagDescription($node));
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visit(Node $node) : UnionType
    {
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['left'],
            $this->should_catch_issue_exception
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right'],
            $this->should_catch_issue_exception
        );
        static $int_or_float = null;

        if ($left->isExclusivelyArray()
            || $right->isExclusivelyArray()
        ) {
            return UnionType::empty();
        } elseif ($left->hasType(FloatType::instance(false))
            || $right->hasType(FloatType::instance(false))
        ) {
            if ($left->hasTypeMatchingCallback(
                static function (Type $type) : bool {
                    return !($type instanceof FloatType);
                }
            ) && $right->hasTypeMatchingCallback(
                static function (Type $type) : bool {
                    return !($type instanceof FloatType);
                }
            )) {
                return $int_or_float ?? ($int_or_float = new UnionType([
                    IntType::instance(false),
                    FloatType::instance(false)
                ]));
            }

            return FloatType::instance(false)->asUnionType();
        } elseif ($left->hasNonNullIntType()
            && $right->hasNonNullIntType()
        ) {
            return IntType::instance(false)->asUnionType();
        }

        return $int_or_float ?? ($int_or_float = new UnionType([
            IntType::instance(false),
            FloatType::instance(false)
        ]));
    }

    /**
     * Analyzes the `<=>` operator.
     *
     * @param Node $node @phan-unused-param
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinarySpaceship(Node $node) : UnionType
    {
        // TODO: Any sanity checks should go here.

        // <=> returns -1, 0, or 1
        return IntType::instance(false)->asUnionType();
    }

    /**
     * Analyzes the `<<` operator.
     *
     * @param Node $node @phan-unused-param
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryShiftLeft(Node $node) : UnionType
    {
        // TODO: Any sanity checks should go here.
        return IntType::instance(false)->asUnionType();
    }

    /**
     * Analyzes the `>>` operator.
     *
     * @param Node $node @phan-unused-param
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryShiftRight(Node $node) : UnionType
    {
        // TODO: Any sanity checks should go here.
        return IntType::instance(false)->asUnionType();
    }

    /**
     * Code can bitwise xor strings byte by byte (or integers by value) in PHP
     * @override
     */
    public function visitBinaryBitwiseXor(Node $node) : UnionType
    {
        return $this->analyzeBinaryBitwiseCommon($node);
    }

    /**
     * @override
     */
    public function visitBinaryBitwiseOr(Node $node) : UnionType
    {
        return $this->analyzeBinaryBitwiseCommon($node);
    }

    /**
     * @override
     */
    public function visitBinaryBitwiseAnd(Node $node) : UnionType
    {
        return $this->analyzeBinaryBitwiseCommon($node);
    }

    private function analyzeBinaryBitwiseCommon(Node $node) : UnionType
    {
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['left'],
            $this->should_catch_issue_exception
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right'],
            $this->should_catch_issue_exception
        );

        if ($left->hasNonNullIntType()) {
            if ($right->hasNonNullIntType()) {
                return self::computeIntegerOperationResult($node, $left, $right);
            }
            if ($right->hasNonNullStringType()) {
                $this->emitIssue(
                    Issue::TypeMismatchBitwiseBinaryOperands,
                    $node->lineno ?? 0,
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags],
                    $left,
                    $right
                );
            }
        } elseif ($left->hasNonNullStringType()) {
            if ($right->hasNonNullStringType()) {
                return StringType::instance(false)->asUnionType();
            }
            if ($right->hasNonNullIntType()) {
                $this->emitIssue(
                    Issue::TypeMismatchBitwiseBinaryOperands,
                    $node->lineno ?? 0,
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags],
                    $left,
                    $right
                );
            }
        }
        if (!$left->hasValidBitwiseOperand() || !$right->hasValidBitwiseOperand()) {
            $this->emitIssue(
                Issue::TypeInvalidBitwiseBinaryOperator,
                $node->lineno ?? 0,
                PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags],
                $left,
                $right
            );
        }

        return IntType::instance(false)->asUnionType();
    }

    private static function computeIntegerOperationResult(
        Node $node,
        UnionType $left,
        UnionType $right
    ) : UnionType {
        $left_value = $left->asSingleScalarValueOrNull();
        if (is_int($left_value)) {
            $right_value = $right->asSingleScalarValueOrNull();
            if (is_int($right_value)) {
                switch ($node->flags) {
                    case ast\flags\BINARY_BITWISE_OR:
                        return LiteralIntType::instanceForValue($left_value | $right_value, false)->asUnionType();
                    case ast\flags\BINARY_BITWISE_AND:
                        return LiteralIntType::instanceForValue($left_value & $right_value, false)->asUnionType();
                    case ast\flags\BINARY_BITWISE_XOR:
                        return LiteralIntType::instanceForValue($left_value ^ $right_value, false)->asUnionType();
                    case ast\flags\BINARY_MUL:
                        $value = $left_value * $right_value;
                        return is_int($value) ? LiteralIntType::instanceForValue($value, false)->asUnionType()
                                              : FloatType::instance(false)->asUnionType();
                    case ast\flags\BINARY_SUB:
                        $value = $left_value - $right_value;
                        return is_int($value) ? LiteralIntType::instanceForValue($value, false)->asUnionType()
                                              : FloatType::instance(false)->asUnionType();
                    case ast\flags\BINARY_ADD:
                        $value = $left_value + $right_value;
                        return is_int($value) ? LiteralIntType::instanceForValue($value, false)->asUnionType()
                                              : FloatType::instance(false)->asUnionType();
                    case ast\flags\BINARY_POW:
                        $value = $left_value ** $right_value;
                        return is_int($value) ? LiteralIntType::instanceForValue($value, false)->asUnionType()
                                              : FloatType::instance(false)->asUnionType();
                }
            }
        }

        return IntType::instance(false)->asUnionType();
    }

    /**
     * @param string $issue_type
     * The type of issue to emit such as Issue::ParentlessClass
     *
     * @param int $lineno
     * The line number where the issue was found
     *
     * @param int|string|FQSEN|UnionType|Type ...$parameters
     * Template parameters for the issue's error message
     *
     * @return void
     */
    protected function emitIssue(
        string $issue_type,
        int $lineno,
        ...$parameters
    ) {
        Issue::maybeEmitWithParameters(
            $this->code_base,
            $this->context,
            $issue_type,
            $lineno,
            $parameters
        );
    }

    /**
     * @param Node $unused_node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryBoolAnd(Node $unused_node) : UnionType
    {
        return BoolType::instance(false)->asUnionType();
    }

    /**
     * @param Node $unused_node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryBoolXor(Node $unused_node) : UnionType
    {
        return BoolType::instance(false)->asUnionType();
    }

    /**
     * @param Node $unused_node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryBoolOr(Node $unused_node) : UnionType
    {
        return BoolType::instance(false)->asUnionType();
    }

    /**
     * @param Node $node A node to check types on (@phan-unused-param)
     *
     * TODO: Check that both types can cast to string or scalars?
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryConcat(Node $node) : UnionType
    {
        $left_node = $node->children['left'];
        $left_value = $left_node instanceof Node ? UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $left_node,
            $this->should_catch_issue_exception
        )->asSingleScalarValueOrNullOrSelf() : $left_node;
        if (\is_object($left_value)) {
            return StringType::instance(false)->asUnionType();
        }
        $right_node = $node->children['right'];
        $right_value = $right_node instanceof Node ? UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $right_node,
            $this->should_catch_issue_exception
        )->asSingleScalarValueOrNullOrSelf() : $right_node;
        if (\is_object($right_value)) {
            return StringType::instance(false)->asUnionType();
        }
        return LiteralStringType::instanceForValue($left_value . $right_value, false)->asUnionType();
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    private function visitBinaryOpCommon(Node $node)
    {
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['left'],
            $this->should_catch_issue_exception
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right'],
            $this->should_catch_issue_exception
        );

        $left_is_array_like = $left->isExclusivelyArrayLike();
        $right_is_array_like = $right->isExclusivelyArrayLike();

        $left_can_cast_to_array = $left->canCastToUnionType(
            ArrayType::instance(false)->asUnionType()
        );

        $right_can_cast_to_array = $right->canCastToUnionType(
            ArrayType::instance(false)->asUnionType()
        );

        if ($left_is_array_like
            && !$right->hasArrayLike()
            && !$right_can_cast_to_array
            && !$right->isEmpty()
            && !$right->containsNullable()
            && !$left->hasAnyType($right->getTypeSet())  // TODO: Strict canCastToUnionType() variant?
        ) {
            $this->emitIssue(
                Issue::TypeComparisonFromArray,
                $node->lineno ?? 0,
                (string)$right->asNonLiteralType()
            );
        } elseif ($right_is_array_like
            && !$left->hasArrayLike()
            && !$left_can_cast_to_array
            && !$left->isEmpty()
            && !$left->containsNullable()
            && !$right->hasAnyType($left->getTypeSet())  // TODO: Strict canCastToUnionType() variant?

        ) {
            $this->emitIssue(
                Issue::TypeComparisonToArray,
                $node->lineno ?? 0,
                (string)$left->asNonLiteralType()
            );
        }

        return BoolType::instance(false)->asUnionType();
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsIdentical(Node $node) : UnionType
    {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsNotIdentical(Node $node) : UnionType
    {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsEqual(Node $node) : UnionType
    {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsNotEqual(Node $node) : UnionType
    {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsSmaller(Node $node) : UnionType
    {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsSmallerOrEqual(Node $node) : UnionType
    {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsGreater(Node $node) : UnionType
    {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsGreaterOrEqual(Node $node) : UnionType
    {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node with type AST_BINARY_OP
     * @param Closure(Type):bool $is_valid_type
     * @return void
     */
    private function warnAboutInvalidUnionType(
        Node $node,
        Closure $is_valid_type,
        UnionType $left,
        UnionType $right,
        string $left_issue_type,
        string $right_issue_type
    ) {
        if (!$left->isEmpty()) {
            if (!$left->hasTypeMatchingCallback($is_valid_type)) {
                $this->emitIssue(
                    $left_issue_type,
                    $node->children['left']->lineno ?? $node->lineno,
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags],
                    $left
                );
            }
        }
        if (!$right->isEmpty()) {
            if (!$right->hasTypeMatchingCallback($is_valid_type)) {
                $this->emitIssue(
                    $right_issue_type,
                    $node->children['right']->lineno ?? $node->lineno,
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags],
                    $right
                );
            }
        }
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     * @suppress PhanTypeMismatchArgumentNullable false positives for static initializing
     */
    public function visitBinaryAdd(Node $node) : UnionType
    {
        $code_base = $this->code_base;
        $context = $this->context;
        $left = UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $node->children['left'],
            $this->should_catch_issue_exception
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $node->children['right'],
            $this->should_catch_issue_exception
        );

        // fast-track common cases
        if ($left->isNonNullIntType() && $right->isNonNullIntType()) {
            return self::computeIntegerOperationResult($node, $left, $right);
        }

        // If both left and right union types are arrays, then this is array
        // concatenation.
        if ($left->isGenericArray() && $right->isGenericArray()) {
            if ($left->isEqualTo($right)) {
                return $left;
            }
            return ArrayType::combineArrayTypesOverriding($left, $right);
        }

        $this->warnAboutInvalidUnionType(
            $node,
            static function (Type $type) : bool {
                return $type->isValidNumericOperand() || $type instanceof ArrayType;
            },
            $left,
            $right,
            Issue::TypeInvalidLeftOperandOfAdd,
            Issue::TypeInvalidRightOperandOfAdd
        );

        static $float_type = null;
        static $array_type = null;
        static $int_or_float_union_type = null;
        if ($int_or_float_union_type === null) {
            $float_type = FloatType::instance(false);
            $array_type = ArrayType::instance(false);
            $int_or_float_union_type = new UnionType([
                IntType::instance(false),
                $float_type
            ]);
        }

        if ($left->isNonNullNumberType() && $right->isNonNullNumberType()) {
            if (!$left->hasNonNullIntType() || !$right->hasNonNullIntType()) {
                // Heuristic: If one or more of the sides is a float, the result is always a float.
                // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
                return $float_type->asUnionType();
            }
            return $int_or_float_union_type;
        }

        $left_is_array = (
            !$left->genericArrayElementTypes()->isEmpty()
            && $left->nonArrayTypes()->isEmpty()
        ) || $left->isType($array_type);
        // @phan-suppress-previous-line PhanTypeMismatchArgumentNullable false positive for static initialization

        $right_is_array = (
            !$right->genericArrayElementTypes()->isEmpty()
            && $right->nonArrayTypes()->isEmpty()
        ) || $right->isType($array_type);
        // @phan-suppress-previous-line PhanTypeMismatchArgumentNullable false positive for static initialization

        if ($left_is_array || $right_is_array) {
            if ($left_is_array && $right_is_array) {
                // TODO: Make the right types for array offsets completely override the left types?
                return ArrayType::combineArrayTypesOverriding($left, $right);
            }

            if ($left_is_array
                && !$right->canCastToUnionType(
                    ArrayType::instance(false)->asUnionType()
                )
            ) {
                $this->emitIssue(
                    Issue::TypeInvalidRightOperand,
                    $node->lineno ?? 0
                );
                return UnionType::empty();
                // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
            } elseif ($right_is_array && !$left->canCastToUnionType($array_type->asUnionType())) {
                $this->emitIssue(
                    Issue::TypeInvalidLeftOperand,
                    $node->lineno ?? 0
                );
                return UnionType::empty();
            }
            // If it is a '+' and we know one side is an array
            // and the other is unknown, assume array
            // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
            return $array_type->asUnionType();
        }

        return $int_or_float_union_type;
    }

    /**
     * Analyzes the result of a floating-point or integer arithmetic operation.
     * The result will be a combination of 'int' or 'float'
     *
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    private function getTypeOfNumericArithmeticOp(Node $node) : UnionType
    {
        $code_base = $this->code_base;
        $context = $this->context;
        $left = UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $node->children['left'],
            $this->should_catch_issue_exception
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $node->children['right'],
            $this->should_catch_issue_exception
        );

        // fast-track common cases
        if ($left->isNonNullIntType() && $right->isNonNullIntType()) {
            return self::computeIntegerOperationResult($node, $left, $right);
        }

        $this->warnAboutInvalidUnionType(
            $node,
            static function (Type $type) : bool {
                // TODO: Stricten this to warn about strings based on user config.
                return $type->isValidNumericOperand();
            },
            $left,
            $right,
            Issue::TypeInvalidLeftOperandOfNumericOp,
            Issue::TypeInvalidRightOperandOfNumericOp
        );

        static $float_type = null;
        static $int_or_float_union_type = null;
        if ($int_or_float_union_type === null) {
            $float_type = FloatType::instance(false);
            $int_or_float_union_type = new UnionType([
                IntType::instance(false),
                $float_type
            ]);
        }

        if ($left->isNonNullNumberType() && $right->isNonNullNumberType()) {
            if (!$left->hasNonNullIntType() || !$right->hasNonNullIntType()) {
                // Heuristic: If one or more of the sides is a float, the result is always a float.
                // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
                return $float_type->asUnionType();
            }
            return $int_or_float_union_type;
        }

        // TODO: warn about subtracting to/from non-number

        return $int_or_float_union_type;
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinarySub(Node $node) : UnionType
    {
        return $this->getTypeOfNumericArithmeticOp($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryMul(Node $node) : UnionType
    {
        return $this->getTypeOfNumericArithmeticOp($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryPow(Node $node) : UnionType
    {
        return $this->getTypeOfNumericArithmeticOp($node);
    }

    /**
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryMod(Node $unused_node)
    {
        // TODO: Warn about invalid left or right side
        return IntType::instance(false)->asUnionType();
    }

    /**
     * Common visitor for binary boolean operations
     *
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryCoalesce(Node $node) : UnionType
    {
        $left_node = $node->children['left'];
        $left_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $left_node,
            $this->should_catch_issue_exception
        );
        if (!($left_node instanceof Node)) {
            // TODO: Warn about this being an unnecessary coalesce operation
            // TODO: Be more aggressive for constants, etc, when we are very sure the type is accurate.
            return $left_type;
        }

        $right_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right'],
            $this->should_catch_issue_exception
        );

        // On the left side, remove null and replace '?T' with 'T'
        // Don't bother if the right side contains null.
        if (!$right_type->isEmpty() && $left_type->containsNullable() && !$right_type->containsNullable()) {
            $left_type = $left_type->nonNullableClone();
        }

        return $left_type->withUnionType($right_type);
    }
}
