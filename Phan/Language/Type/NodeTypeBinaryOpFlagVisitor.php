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
use \ast\Node;

class NodeTypeBinaryOpFlagVisitor extends FlagVisitorImplementation {

    /**
     * @var Context
     */
    private $context;

    /**
     *
     */
    public function __construct(Context $context) {
        $this->context = $context;
    }

    public function visitBinaryConcat(Node $node) {
        return StringType::instance()->asUnionType();
    }

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

        if (!empty($left->genericTypes())
            && empty($left->nonGenericTypes())
            && !$right->canCastToUnionType(ArrayType::instance()->asUnionType())
        ) {
            Log::err(
                Log::ETYPE,
                "array to $right comparison",
                $context->getFile(),
                $node->lineno
            );
        } else if (!empty($right->genericTypes())
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

    public function visitBinaryIsIdentical(Node $node) {
        return $this->visitBinaryOpCommon($node);
    }

    public function visitBinaryIsNotIdentical(Node $node) {
        return $this->visitBinaryOpCommon($node);
    }

    public function visitBinaryIsEqual(Node $node) {
        return $this->visitBinaryOpCommon($node);
    }

    public function visitBinaryIsNotEqual(Node $node) {
        return $this->visitBinaryOpCommon($node);
    }

    public function visitBinaryIsSmaller(Node $node) {
        return $this->visitBinaryOpCommon($node);
    }

    public function visitBinaryIsSmallerOrEqual(Node $node) {
        return $this->visitBinaryOpCommon($node);
    }

    public function visitBinaryIsGreater(Node $node) {
        return $this->visitBinaryOpCommon($node);
    }

    public function visitBinaryIsGreaterOrEqual(Node $node) {
        return $this->visitBinaryOpCommon($node);
    }


    public function visitBinaryAdd(Node $node) {
        $left =
            UnionType::fromNode($this->context, $node->children['left']);

        $right =
            UnionType::fromNode($this->context, $node->children['right']);

        // fast-track common cases
        if ($left->isType(IntType::instance())
            && $right->isType(IntType::instance())
        ) {
            return new UnionType(IntType::instance());
        }

        if (($left->isType(IntType::instance())
            || $left->isType(FloatType::instance()))
            && ($right->isType(IntType::instance())
            || $right->isType(FloatType::instance()))
        ) {
            return new UnionType(FloatType::instance());
        }

        $left_is_array = (
            !empty($left->genericTypes()) && empty($left->nonGenericTypes())
        );

        $right_is_array = (
            !empty($right->genericTypes()) && empty($right->nonGenericTypes())
        );

        if($left_is_array
            && !$right->canCastToUnionType(ArrayType::instance()->asUnionType())) {
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

    public function visit(Node $node) {
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

        if ($left->hasType(ArrayType::instance())
            || $right->hasType(ArrayType::instance())
        ) {
            Log::err(
                Log::ETYPE,
                "invalid array operator",
                $context->getFile(),
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

}
