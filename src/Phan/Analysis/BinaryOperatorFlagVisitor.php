<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\Element;
use Phan\AST\Visitor\FlagVisitorImplementation;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\UnionType;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\LiteralStringType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\StringType;
use Phan\Issue;
use ast\Node;

// TODO: Improve analysis of bitwise operations, warn if non-int is provided and consistently return int if it's guaranteed
class BinaryOperatorFlagVisitor extends FlagVisitorImplementation
{

    /**
     * @var CodeBase
     */
    private $code_base;

    /**
     * @var Context
     */
    private $context;

    /**
     * Create a new BinaryOperatorFlagVisitor
     */
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
    }

    /**
     * @param Node $node
     * A node to visit
     */
    public function __invoke(Node $node)
    {
        return Element::acceptBinaryFlagVisitor($node, $this);
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
        // TODO: % operator always returns int.

        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['left']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right']
        );

        if ($left->isType(ArrayType::instance(false))
            || $right->isType(ArrayType::instance(false))
        ) {
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::TypeArrayOperator,
                $node->lineno ?? 0,
                $left,
                $right
            );

            return UnionType::empty();
        } elseif ($left->hasType(FloatType::instance(false))
            || $right->hasType(FloatType::instance(false))
        ) {
            return FloatType::instance(false)->asUnionType();
        } elseif ($left->hasNonNullIntType()
            && $right->hasNonNullIntType()
        ) {
            return IntType::instance(false)->asUnionType();
        }

        static $int_or_float = null;
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
     * Code can bitwise xor strings byte by byte in PHP
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
            $node->children['left']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right']
        );

        if ($left->isType(ArrayType::instance(false))
            || $right->isType(ArrayType::instance(false))
        ) {
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::TypeArrayOperator,
                $node->lineno ?? 0,
                $left,
                $right
            );

            return UnionType::empty();
        } elseif ($left->hasNonNullIntType()
            && $right->hasNonNullIntType()
        ) {
            return IntType::instance(false)->asUnionType();
        } elseif ($left->hasNonNullStringType()
            && $right->hasNonNullStringType()
        ) {
            return StringType::instance(false)->asUnionType();
        }

        return IntType::instance(false)->asUnionType();
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryBoolAnd(Node $node) : UnionType
    {
        return $this->visitBinaryBool($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryBoolXor(Node $node) : UnionType
    {
        return $this->visitBinaryBool($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryBoolOr(Node $node) : UnionType
    {
        return $this->visitBinaryBool($node);
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
            $left_node
        )->asSingleScalarValueOrNull() : $left_node;
        if ($left_value === null) {
            return StringType::instance(false)->asUnionType();
        }
        $right_node = $node->children['right'];
        $right_value = $right_node instanceof Node ? UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $right_node
        )->asSingleScalarValueOrNull() : $right_node;
        if ($right_value === null) {
            return StringType::instance(false)->asUnionType();
        }
        return LiteralStringType::instance_for_value($left_value . $right_value, false)->asUnionType();
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
            $node->children['left']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right']
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
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
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
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
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
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryAdd(Node $node) : UnionType
    {
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['left']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right']
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

        // fast-track common cases
        if ($left->isNonNullIntType() && $right->isNonNullIntType()) {
            return IntType::instance(false)->asUnionType();
        }

        // If both left and right are arrays, then this is array
        // concatenation.
        if ($left->isGenericArray() && $right->isGenericArray()) {
            if ($left->isEqualTo($right)) {
                return $left;
            }
            return ArrayType::combineArrayTypesOverriding($left, $right);
        }

        if (($left->isNonNullIntType()
            || $left->isType($float_type))
            && ($right->isNonNullIntType()
            || $right->isType($float_type))
        ) {
            return $float_type->asUnionType();
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
                // TODO: Make the right types for array offsets completely override the left types?
                return ArrayType::combineArrayTypesOverriding($left, $right);
            }

            if ($left_is_array
                && !$right->canCastToUnionType(
                    ArrayType::instance(false)->asUnionType()
                )
            ) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    Issue::TypeInvalidRightOperand,
                    $node->lineno ?? 0
                );
                return UnionType::empty();
            } elseif ($right_is_array
                && !$left->canCastToUnionType($array_type->asUnionType())
            ) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    Issue::TypeInvalidLeftOperand,
                    $node->lineno ?? 0
                );
                return UnionType::empty();
            } elseif ($left_is_array || $right_is_array) {
                // If it is a '+' and we know one side is an array
                // and the other is unknown, assume array
                return $array_type->asUnionType();
            }
        }

        return $int_or_float_union_type;
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
    private function visitBinaryBool(Node $node) : UnionType
    {
        // TODO: Check for suspicious operations (E.g. always false, always true, always object)
        $unused_left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['left']
        );

        $unused_right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right']
        );

        return BoolType::instance(false)->asUnionType();
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
            $left_node
        );
        if (!($left_node instanceof Node)) {
            // TODO: Warn about this being an unnecessary coalesce operation
            // TODO: Be more aggressive for constants, etc, when we are very sure the type is accurate.
            return $left_type;
        }

        $right_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right']
        );

        // On the left side, remove null and replace '?T' with 'T'
        // Don't bother if the right side contains null.
        if (!$right_type->isEmpty() && $left_type->containsNullable() && !$right_type->containsNullable()) {
            $left_type = $left_type->nonNullableClone();
        }

        return $left_type->withUnionType($right_type);
    }
}
