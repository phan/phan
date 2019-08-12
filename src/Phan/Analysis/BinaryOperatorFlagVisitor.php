<?php

declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use ast;
use ast\Node;
use Closure;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\Element;
use Phan\AST\Visitor\FlagVisitorImplementation;
use Phan\CodeBase;
use Phan\Debug;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\ListType;
use Phan\Language\Type\LiteralFloatType;
use Phan\Language\Type\LiteralIntType;
use Phan\Language\Type\LiteralStringType;
use Phan\Language\Type\MixedType;
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
    private function handleMissing(Node $node): void
    {
        throw new AssertionError("All flags must match. Found kind=" . Debug::nodeName($node) . ', flags=' . Element::flagDescription($node) . ' raw flags=' . $node->flags . ' at ' . $this->context->withLineNumberStart((int)$node->lineno));
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
    public function visit(Node $node): UnionType
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
                static function (Type $type): bool {
                    return !($type instanceof FloatType);
                }
            ) && $right->hasTypeMatchingCallback(
                static function (Type $type): bool {
                        return !($type instanceof FloatType);
                }
            )
            ) {
                return $int_or_float ?? ($int_or_float = UnionType::fromFullyQualifiedPHPDocString('int|float'));
            }

            return FloatType::instance(false)->asPHPDocUnionType();
        } elseif ($left->hasNonNullIntType()
            && $right->hasNonNullIntType()
        ) {
            return IntType::instance(false)->asPHPDocUnionType();
        }

        return $int_or_float ?? ($int_or_float = UnionType::fromFullyQualifiedPHPDocString('int|float'));
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
    public function visitBinarySpaceship(Node $node): UnionType
    {
        // TODO: Any sanity checks should go here.

        // <=> returns -1, 0, or 1
        return UnionType::fromFullyQualifiedRealString('-1|0|1');
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
    public function visitBinaryShiftLeft(Node $node): UnionType
    {
        // TODO: Any sanity checks should go here.
        return IntType::instance(false)->asRealUnionType();
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
    public function visitBinaryShiftRight(Node $node): UnionType
    {
        // TODO: Any sanity checks should go here.
        return IntType::instance(false)->asRealUnionType();
    }

    /**
     * Code can bitwise xor strings byte by byte (or integers by value) in PHP
     * @override
     */
    public function visitBinaryBitwiseXor(Node $node): UnionType
    {
        return $this->analyzeBinaryBitwiseCommon($node);
    }

    /**
     * @override
     */
    public function visitBinaryBitwiseOr(Node $node): UnionType
    {
        return $this->analyzeBinaryBitwiseCommon($node);
    }

    /**
     * @override
     */
    public function visitBinaryBitwiseAnd(Node $node): UnionType
    {
        return $this->analyzeBinaryBitwiseCommon($node);
    }

    private function analyzeBinaryBitwiseCommon(Node $node): UnionType
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
                return self::computeIntOrFloatOperationResult($node, $left, $right);
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
                return UnionType::fromFullyQualifiedPHPDocAndRealString('string', 'int|string');
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

        return UnionType::fromFullyQualifiedPHPDocAndRealString('int', 'int|string');
    }

    /**
     * TODO: Switch to asRealUnionType when both operands are real
     * @internal
     */
    public static function computeIntOrFloatOperationResult(
        Node $node,
        UnionType $left,
        UnionType $right
    ): UnionType {
        static $real_int_or_string;
        static $real_int;
        static $real_float;
        static $real_int_or_float;
        if ($real_int_or_string === null) {
            $real_int_or_string = [IntType::instance(false), StringType::instance(false)];
            $real_int = [IntType::instance(false)];
            $real_float = [FloatType::instance(false)];
            $real_int_or_float = [IntType::instance(false), FloatType::instance(false)];
        }
        $left_value = $left->asSingleScalarValueOrNull();
        if ($left_value !== null) {
            $right_value = $right->asSingleScalarValueOrNull();
            if ($right_value !== null) {
                /**
                 * This will aggressively infer the real type for expressions where both values have known real literal types (e.g. 2+2*3),
                 * but fall back if the real type was less specific.
                 *
                 * @param list<Type> $default_types
                 */
                $make_literal_union_type = static function (Type $result, array $default_types) use ($left, $right): UnionType {
                    if ($left->isExclusivelyRealTypes() && $right->isExclusivelyRealTypes()) {
                        return $result->asRealUnionType();
                    }
                    return UnionType::of([$result], $default_types);
                };
                switch ($node->flags) {
                    case ast\flags\BINARY_BITWISE_OR:
                        return $make_literal_union_type(
                            LiteralIntType::instanceForValue($left_value | $right_value, false),
                            $real_int
                        );
                    case ast\flags\BINARY_BITWISE_AND:
                        return $make_literal_union_type(
                            LiteralIntType::instanceForValue($left_value & $right_value, false),
                            $real_int
                        );
                    case ast\flags\BINARY_BITWISE_XOR:
                        return $make_literal_union_type(
                            LiteralIntType::instanceForValue($left_value ^ $right_value, false),
                            $real_int
                        );
                    case ast\flags\BINARY_MUL:
                        $value = $left_value * $right_value;
                        return $make_literal_union_type(
                            is_int($value) ? LiteralIntType::instanceForValue($value, false)
                                           : LiteralFloatType::instanceForValue($value, false),
                            $real_int_or_float
                        );
                    case ast\flags\BINARY_DIV:
                        // @phan-suppress-next-line PhanSuspiciousTruthyString deliberate check - this possible string is implicitly cast to a number.
                        if (!$right_value) {
                            // TODO: Emit warning about division by zero.
                            return FloatType::instance(false)->asRealUnionType();
                        }
                        $value = $left_value / $right_value;
                        return $make_literal_union_type(
                            is_int($value) ? LiteralIntType::instanceForValue($value, false)
                                           : LiteralFloatType::instanceForValue($value, false),
                            $real_int_or_float
                        );
                    case ast\flags\BINARY_MOD:
                        // @phan-suppress-next-line PhanSuspiciousTruthyString deliberate check - this possible string is implicitly cast to a number.
                        if (!$right_value) {
                            // TODO: Emit warning about division by zero.
                            return IntType::instance(false)->asRealUnionType();
                        }
                        $value = $left_value % $right_value;
                        return $make_literal_union_type(
                            LiteralIntType::instanceForValue($value, false),
                            $real_int
                        );
                    case ast\flags\BINARY_SUB:
                        $value = $left_value - $right_value;
                        return $make_literal_union_type(
                            is_int($value) ? LiteralIntType::instanceForValue($value, false)
                                           : LiteralFloatType::instanceForValue($value, false),
                            $real_int_or_float
                        );
                    case ast\flags\BINARY_ADD:
                        $value = $left_value + $right_value;
                        return $make_literal_union_type(
                            is_int($value) ? LiteralIntType::instanceForValue($value, false)
                                           : LiteralFloatType::instanceForValue($value, false),
                            $real_int_or_float
                        );
                    case ast\flags\BINARY_POW:
                        $value = $left_value ** $right_value;
                        return $make_literal_union_type(
                            is_int($value) ? LiteralIntType::instanceForValue($value, false)
                                           : LiteralFloatType::instanceForValue($value, false),
                            $real_int_or_float
                        );
                }
            }
        }

        $is_binary_op = \in_array($node->flags, [ast\flags\BINARY_BITWISE_XOR, ast\flags\BINARY_BITWISE_AND, ast\flags\BINARY_BITWISE_OR], true);

        if ($is_binary_op) {
            return UnionType::fromFullyQualifiedPHPDocAndRealString('int', 'int|string');
        }
        if ($left->isExclusivelyRealFloatTypes() || $right->isExclusivelyRealFloatTypes()) {
            return FloatType::instance(false)->asRealUnionType();
        }
        if ($node->flags === ast\flags\BINARY_DIV) {
            return UnionType::fromFullyQualifiedRealString('int|float');
        }
        // A heuristic to reduce false positives.
        // e.g. an operation on float and float returns float.
        // e.g. an operation on int|float and int|float returns int|float.
        // e.g. an operation on int and int returns int.
        if ($left->hasTypesCoercingToNonInt() || $right->hasTypesCoercingToNonInt()) {
            $main_type = ($left->hasIntType() && $right->hasIntType()) ? 'int|float' : 'float';
        } else {
            $main_type = 'int';
        }
        return UnionType::fromFullyQualifiedPHPDocAndRealString(
            $main_type,
            'int|float'
        );
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
     */
    protected function emitIssue(
        string $issue_type,
        int $lineno,
        ...$parameters
    ): void {
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
    public function visitBinaryBoolAnd(Node $unused_node): UnionType
    {
        return BoolType::instance(false)->asRealUnionType();
    }

    /**
     * @param Node $unused_node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryBoolXor(Node $unused_node): UnionType
    {
        return BoolType::instance(false)->asRealUnionType();
    }

    /**
     * @param Node $unused_node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryBoolOr(Node $unused_node): UnionType
    {
        return BoolType::instance(false)->asRealUnionType();
    }

    /**
     * @param Node $node A node to check types on (@phan-unused-param)
     *
     * TODO: Check that both types can cast to string or scalars?
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryConcat(Node $node): UnionType
    {
        $left_node = $node->children['left'];
        $left_value = $left_node instanceof Node ? UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $left_node,
            $this->should_catch_issue_exception
        )->asSingleScalarValueOrNullOrSelf() : $left_node;
        if (\is_object($left_value)) {
            return StringType::instance(false)->asRealUnionType();
        }
        $right_node = $node->children['right'];
        $right_value = $right_node instanceof Node ? UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $right_node,
            $this->should_catch_issue_exception
        )->asSingleScalarValueOrNullOrSelf() : $right_node;
        if (\is_object($right_value)) {
            return StringType::instance(false)->asRealUnionType();
        }
        return LiteralStringType::instanceForValue($left_value . $right_value, false)->asRealUnionType();
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    private function visitBinaryOpCommon(Node $node): UnionType
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
            ArrayType::instance(false)->asPHPDocUnionType()
        );

        $right_can_cast_to_array = $right->canCastToUnionType(
            ArrayType::instance(false)->asPHPDocUnionType()
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

        return BoolType::instance(false)->asRealUnionType();
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsIdentical(Node $node): UnionType
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
    public function visitBinaryIsNotIdentical(Node $node): UnionType
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
    public function visitBinaryIsEqual(Node $node): UnionType
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
    public function visitBinaryIsNotEqual(Node $node): UnionType
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
    public function visitBinaryIsSmaller(Node $node): UnionType
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
    public function visitBinaryIsSmallerOrEqual(Node $node): UnionType
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
    public function visitBinaryIsGreater(Node $node): UnionType
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
    public function visitBinaryIsGreaterOrEqual(Node $node): UnionType
    {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node with type AST_BINARY_OP
     * @param Closure(Type):bool $is_valid_type
     */
    private function warnAboutInvalidUnionType(
        Node $node,
        Closure $is_valid_type,
        UnionType $left,
        UnionType $right,
        string $left_issue_type,
        string $right_issue_type
    ): void {
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
     */
    public function visitBinaryAdd(Node $node): UnionType
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

        static $probably_float_type = null;
        static $probably_int_or_float_type = null;
        static $probably_array_type = null;
        static $probably_unknown_type = null;
        static $array_type = null;
        if ($probably_float_type === null) {
            $probably_float_type = UnionType::fromFullyQualifiedPHPDocAndRealString('float', 'int|float|array');
            $probably_int_or_float_type = UnionType::fromFullyQualifiedPHPDocAndRealString('int|float', 'int|float|array');
            $probably_array_type = UnionType::fromFullyQualifiedPHPDocAndRealString('array', 'int|float|array');
            $probably_unknown_type = UnionType::fromFullyQualifiedPHPDocAndRealString('', 'int|float|array');
            // TODO: More precise check for array
            $array_type = ArrayType::instance(false);
        }


        // fast-track common cases
        if ($left->isNonNullIntOrFloatType() && $right->isNonNullIntOrFloatType()) {
            return self::computeIntOrFloatOperationResult($node, $left, $right);
        }

        // If both left and right union types are arrays, then this is array
        // concatenation. (`$left + $right`)
        if ($left->isGenericArray() && $right->isGenericArray()) {
            self::checkInvalidArrayShapeCombination($this->code_base, $this->context, $node, $left, $right);
            if ($left->isEqualTo($right)) {
                return $left;
            }
            return ArrayType::combineArrayTypesOverriding($left, $right, false);
        }

        $this->warnAboutInvalidUnionType(
            $node,
            static function (Type $type): bool {
                return $type->isValidNumericOperand() || $type instanceof ArrayType;
            },
            $left,
            $right,
            Issue::TypeInvalidLeftOperandOfAdd,
            Issue::TypeInvalidRightOperandOfAdd
        );

        if ($left->isNonNullNumberType() && $right->isNonNullNumberType()) {
            if (!$left->hasNonNullIntType() || !$right->hasNonNullIntType()) {
                // Heuristic: If one or more of the sides is a float, the result is always a float.
                return $probably_float_type;
            }
            return $probably_int_or_float_type;
        }

        $left_is_array = (
            !$left->genericArrayElementTypes()->isEmpty()
            && $left->nonArrayTypes()->isEmpty()
        ) || $left->isType($array_type);

        $right_is_array = (
            !$right->genericArrayElementTypes()->isEmpty()
            && $right->nonArrayTypes()->isEmpty()
        ) || $right->isType($array_type);

        if ($left_is_array || $right_is_array) {
            if ($left_is_array && $right_is_array) {
                return ArrayType::combineArrayTypesOverriding($left, $right, false);
            }

            if ($left_is_array
                && !$right->canCastToUnionType(
                    ArrayType::instance(false)->asPHPDocUnionType()
                )
            ) {
                $this->emitIssue(
                    Issue::TypeInvalidRightOperand,
                    $node->lineno ?? 0
                );
                return $probably_unknown_type;
            } elseif ($right_is_array && !$left->canCastToUnionType($array_type->asPHPDocUnionType())) {
                $this->emitIssue(
                    Issue::TypeInvalidLeftOperand,
                    $node->lineno ?? 0
                );
                return $probably_unknown_type;
            }
            // If it is a '+' and we know one side is an array
            // and the other is unknown, assume array
            return $probably_array_type;
        }

        return $probably_int_or_float_type;
    }

    /**
     * Check for suspicious combination of two arrays with
     * `+` or `+=` operators.
     */
    public static function checkInvalidArrayShapeCombination(
        CodeBase $code_base,
        Context $context,
        Node $node,
        UnionType $left,
        UnionType $right
    ): void {
        if (!$left->hasRealTypeSet() || !$right->hasRealTypeSet()) {
            return;
        }
        $possible_right_fields = [];
        foreach ($right->getRealTypeSet() as $type) {
            if (!$type instanceof ArrayShapeType) {
                if ($type instanceof ListType) {
                    continue;
                }
                return;
            }
            $possible_right_fields += $type->getFieldTypes();
        }
        $common_left_fields = null;
        foreach ($left->getRealTypeSet() as $type) {
            // if ($type->isNullable()) { return; }

            if (!$type instanceof ArrayShapeType) {
                if ($type instanceof ListType) {
                    continue;
                }
                return;
            }
            $left_fields = [];
            foreach ($type->getFieldTypes() as $key => $inner_type) {
                if (!$inner_type->isPossiblyUndefined()) {
                    $left_fields[$key] = true;
                }
            }
            if (\is_array($common_left_fields)) {
                $common_left_fields = \array_intersect($common_left_fields, $left_fields);
            } else {
                $common_left_fields = $left_fields;
            }
        }
        if ($common_left_fields && !\array_diff_key($possible_right_fields, $common_left_fields)) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::UselessBinaryAddRight,
                $node->lineno,
                $left,
                $right,
                ASTReverter::toShortString($node)
            );
            return;
        }
        $common_left_fields = $common_left_fields ?? [];
        if ($common_left_fields === \array_values($common_left_fields) && $possible_right_fields === \array_values($possible_right_fields)) {
            foreach (\array_merge($left->getRealTypeSet(), $right->getRealTypeSet()) as $type) {
                if ($type instanceof ArrayShapeType) {
                    // @phan-suppress-next-line PhanAccessMethodInternal
                    if (!$type->canCastToList()) {
                        return;
                    }
                }
            }
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::SuspiciousBinaryAddLists,
                $node->lineno,
                $left,
                $right,
                ASTReverter::toShortString($node)
            );
        }
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
    private function getTypeOfNumericArithmeticOp(Node $node): UnionType
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
        if ($left->isNonNullIntOrFloatType() && $right->isNonNullIntOrFloatType()) {
            return self::computeIntOrFloatOperationResult($node, $left, $right);
        }

        $this->warnAboutInvalidUnionType(
            $node,
            static function (Type $type): bool {
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
            $float_type = FloatType::instance(false)->asRealUnionType();
            $int_or_float_union_type = UnionType::fromFullyQualifiedRealString('int|float');
        }
        if ($left->isExclusivelyRealFloatTypes() || $right->isExclusivelyRealFloatTypes()) {
            return $float_type;
        }
        return $int_or_float_union_type;
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinarySub(Node $node): UnionType
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
    public function visitBinaryMul(Node $node): UnionType
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
    public function visitBinaryDiv(Node $node): UnionType
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
    public function visitBinaryPow(Node $node): UnionType
    {
        return $this->getTypeOfNumericArithmeticOp($node);
    }

    /**
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryMod(Node $unused_node): UnionType
    {
        // TODO: Warn about invalid left or right side
        return IntType::instance(false)->asRealUnionType();
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
    public function visitBinaryCoalesce(Node $node): UnionType
    {
        $left_node = $node->children['left'];
        $left_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $left_node,
            $this->should_catch_issue_exception
        );
        if (!($left_node instanceof Node)) {
            // TODO: Be more aggressive for constants, etc, when we are very sure the type is accurate.
            return $left_type;
        }

        $right_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right'],
            $this->should_catch_issue_exception
        );
        if ($left_type->isEmpty()) {
            if ($right_type->isEmpty()) {
                return MixedType::instance(false)->asPHPDocUnionType();
            } elseif ($right_type->isNull()) {
                // When the right type is null, and the left type is unknown,
                // infer nullable mixed.
                //
                // To infer something useful when strict type checking is disabled,
                // don't add mixed when the right type is something other than null.
                return MixedType::instance(true)->asPHPDocUnionType();
            }
        }

        // On the left side, remove null and replace '?T' with 'T'
        // Don't bother if the right side contains null.
        if (!$right_type->isEmpty() && $left_type->containsNullable() && !$right_type->containsNullable()) {
            $left_type = $left_type->nonNullableClone();
        }

        return $left_type->withUnionType($right_type)->asNormalizedTypes();
    }
}
