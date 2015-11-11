<?php declare(strict_types=1);
namespace Phan\Language\Type;

use \Phan\Debug;
use \Phan\Language\AST\FlagVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\UnionType;
use \Phan\Language\Type\{
    ArrayType,
    BoolType,
    CallableType,
    FloatType,
    GenericArrayType,
    IntType,
    MixedType,
    NativeType,
    NullType,
    ObjectType,
    ResourceType,
    ScalarType,
    StringType,
    VoidType
};
use \Phan\Log;
use \ast\Node;

class NodeTypeBinaryOpFlagVisitor extends FlagVisitorImplementation {

    /**
     * @var Context
     */
    private $context;

    /**
     * Create a new NodeTypeBinaryOpFlagVisitor
     */
    public function __construct(Context $context) {
        $this->context = $context;
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
    public function visit(Node $node) : UnionType {
        $left = UnionType::fromNode(
            $this->context,
            $node->children['left']
        );

        $right = UnionType::fromNode(
            $this->context,
            $node->children['right']
        );

        if ($left->hasType(ArrayType::instance())
            || $right->hasType(ArrayType::instance())
        ) {
            Log::err(
                Log::ETYPE,
                "invalid array operator",
                $this->context->getFile(),
                $node->lineno
            );
            return new UnionType();
        } else if ($left->hasType(IntType::instance())
            && $right->hasType(IntType::instance())
        ) {
            return IntType::instance()->asUnionType();
        } else if ($left->hasType(FloatType::instance())
            && $right->hasType(FloatType::instance())
        ) {
            return FloatType::instance()->asUnionType();
        }

        return new UnionType([
            IntType::instance(),
            FloatType::instance()
        ]);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryBoolAnd(Node $node) : UnionType {
        return $this->visitBinaryBool($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryBoolXor(Node $node) : UnionType {
        return $this->visitBinaryBool($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryBoolOr(Node $node) : UnionType {
        return $this->visitBinaryBool($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryConcat(Node $node) : UnionType {
        return StringType::instance()->asUnionType();
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    private function visitBinaryOpCommon(Node $node) {
        $left =
            UnionType::fromNode(
                $this->context,
                $node->children['left']
            );

        $right =
            UnionType::fromNode(
                $this->context,
                $node->children['right']
            );

        if (!empty($left->asNonGenericTypes())
            && empty($left->nonGenericTypes())
            && !$right->canCastToUnionType(ArrayType::instance()->asUnionType())
        ) {
            Log::err(
                Log::ETYPE,
                "array to $right comparison",
                $context->getFile(),
                $node->lineno
            );
        } else if (!empty($right->asNonGenericTypes())
            && empty($right->nonGenericTypes())
            && !$left->canCastToUnionType(ArrayType::instance()->asUnionType())
        ) {
            // and the same for the right side
            Log::err(
                Log::ETYPE,
                "$left to array comparison",
                $context->getFile(),
                $node->lineno
            );
        }

        return BoolType::instance()->asUnionType();
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsIdentical(Node $node) : UnionType {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsNotIdentical(Node $node) : UnionType {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsEqual(Node $node) : UnionType {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsNotEqual(Node $node) : UnionType {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsSmaller(Node $node) : UnionType {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsSmallerOrEqual(Node $node) : UnionType {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsGreater(Node $node) : UnionType {
        return $this->visitBinaryOpCommon($node);
    }

    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryIsGreaterOrEqual(Node $node) : UnionType {
        return $this->visitBinaryOpCommon($node);
    }


    /**
     * @param Node $node
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryAdd(Node $node) : UnionType {
        $left = UnionType::fromNode(
            $this->context,
            $node->children['left']
        );

        $right = UnionType::fromNode(
            $this->context,
            $node->children['right']
        );

        // fast-track common cases
        if ($left->isType(IntType::instance())
            && $right->isType(IntType::instance())
        ) {
            return IntType::instance()->asUnionType();
        }

        if (($left->isType(IntType::instance())
            || $left->isType(FloatType::instance()))
            && ($right->isType(IntType::instance())
            || $right->isType(FloatType::instance()))
        ) {
            return FloatType::instance()->asUnionType();
        }

        $left_is_array = (
            !empty($left->asNonGenericTypes())
            && empty($left->nonGenericTypes())
        );

        $right_is_array = (
            !empty($right->asNonGenericTypes())
            && empty($right->nonGenericTypes())
        );

        if($left_is_array
            && !$right->canCastToUnionType(
                ArrayType::instance()->asUnionType())
        ) {
            Log::err(
                Log::ETYPE,
                "invalid operator: left operand is array and right is not",
                $context->getFile(),
                $node->lineno
            );
            return new UnionType();
        } else if($right_is_array
            && !$left->canCastToUnionType(ArrayType::intance()->asUnionType())
        ) {
            Log::err(
                Log::ETYPE,
                "invalid operator: right operand is array and left is not",
                $file,
                $node->lineno
            );
            return new UnionType();
        } else if($left_is_array || $right_is_array) {
            // If it is a '+' and we know one side is an array
            // and the other is unknown, assume array
            return ArrayType::intance()->asUnionType();
        }

        return new UnionType([
            IntType::instance(),
            FloatType::instance()
        ]);
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
    private function visitBinaryBool(Node $node) : UnionType {
        return BoolType::instance()->asUnionType();
    }

}
