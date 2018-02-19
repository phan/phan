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
        $left = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['left']
        );

        $right = UnionType::fromNode(
            $this->context,
            $this->code_base,
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
        } elseif ($left->hasType(IntType::instance(false))
            && $right->hasType(IntType::instance(false))
        ) {
            return IntType::instance(false)->asUnionType();
        } elseif ($left->hasType(FloatType::instance(false))
            && $right->hasType(FloatType::instance(false))
        ) {
            return FloatType::instance(false)->asUnionType();
        }

        static $int_or_float = null;
        return $int_or_float ?? ($int_or_float = new UnionType([
            IntType::instance(false),
            FloatType::instance(false)
        ]));
    }

    // Code can bitwise xor strings byte by byte in PHP
    public function visitBinaryBitwiseXor(Node $node) : UnionType
    {
        $left = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['left']
        );

        $right = UnionType::fromNode(
            $this->context,
            $this->code_base,
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
        } elseif ($left->hasType(IntType::instance(false))
            && $right->hasType(IntType::instance(false))
        ) {
            return IntType::instance(false)->asUnionType();
        } elseif ($left->hasType(StringType::instance(false))
            && $right->hasType(StringType::instance(false))
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
     * @param Node $node
     * A node to check types on
     * TODO: Check that both types can cast to string or scalars?
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     * @suppress PhanPluginUnusedMethodArgument
     */
    public function visitBinaryConcat(Node $node) : UnionType
    {
        return StringType::instance(false)->asUnionType();
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
        $left = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['left']
        );

        $right = UnionType::fromNode(
            $this->context,
            $this->code_base,
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
                (string)$right
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
                (string)$left
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
        $left = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['left']
        );

        $right = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['right']
        );

        static $int_type = null;
        static $float_type = null;
        static $array_type = null;
        static $int_or_float_union_type = null;
        if ($int_type === null) {
            $int_type = IntType::instance(false);
            $float_type = FloatType::instance(false);
            $array_type = ArrayType::instance(false);
            $int_or_float_union_type = new UnionType([
                $int_type,
                $float_type
            ]);
        }

        // fast-track common cases
        if ($left->isType($int_type) && $right->isType($int_type)) {
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

        if (($left->isType($int_type)
            || $left->isType($float_type))
            && ($right->isType($int_type)
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
        $unused_left = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['left']
        );

        $unused_right = UnionType::fromNode(
            $this->context,
            $this->code_base,
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
        $left_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['left']
        );

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
