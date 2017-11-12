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
class AssignOperatorFlagVisitor extends FlagVisitorImplementation
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
        // AST_ASSIGN_OP uses \ast\flags\BINARY_* in ast versions >= 20.
        // NOTE: Some operations currently don't exist in any php version, such as `$x ||= 2;`, `$x xor= 2;`
        return (new Element($node))->acceptBinaryFlagVisitor($this);
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
        // TODO: For some types (e.g. xor, bitwise or), set the type of the variable?
        // Or should that be done in PreOrderAnalysisVisitor?
        $left = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['var']
        );

        $right = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['expr']
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

            return new UnionType();
        } elseif ($left->hasType(IntType::instance(false))
            && $right->hasType(IntType::instance(false))
        ) {
            return IntType::instance(false)->asUnionType();
        } elseif ($left->hasType(FloatType::instance(false))
            && $right->hasType(FloatType::instance(false))
        ) {
            return FloatType::instance(false)->asUnionType();
        }

        return new UnionType([
            IntType::instance(false),
            FloatType::instance(false)
        ]);
    }

    public function visitBinaryBitwiseAnd(Node $node)
    {
        $left = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['var']
        );

        $right = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['expr']
        );
        if ($left->hasType(IntType::instance(false))
            && $right->hasType(IntType::instance(false))
        ) {
            return IntType::instance(false)->asUnionType();
        } elseif ($left->hasType(StringType::instance(false)) &&
            $right->hasType(StringType::instance(false))) {
            // $x = 'a'; $x &= 'c';
            return StringType::instance(false)->asUnionType();
        }
        return IntType::instance(false)->asUnionType();
    }

    public function visitBinaryBitwiseOr(Node $node)
    {
        $left = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['var']
        );

        $right = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['expr']
        );
        if ($left->hasType(IntType::instance(false))
            && $right->hasType(IntType::instance(false))
        ) {
            return IntType::instance(false)->asUnionType();
        } elseif ($left->hasType(StringType::instance(false)) &&
            $right->hasType(StringType::instance(false))) {
            // $x = 'a'; $x |= 'c';
            return StringType::instance(false)->asUnionType();
        }
        return IntType::instance(false)->asUnionType();
    }

    // Code can bitwise xor strings byte by byte in PHP
    public function visitBinaryBitwiseXor(Node $node)
    {
        $left = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['var']
        );

        $right = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['expr']
        );

        // TODO: check for other invalid types
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

            return new UnionType();
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
    public function visitBinaryAdd(Node $node) : UnionType
    {
        $left = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['var']
        );

        $right = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['expr']
        );

        // fast-track common cases
        if ($left->isType(IntType::instance(false))
            && $right->isType(IntType::instance(false))
        ) {
            return IntType::instance(false)->asUnionType();
        }

        // If both left and right are arrays, then this is array
        // concatenation.
        if ($left->isGenericArray() && $right->isGenericArray()) {
            if ($left->isEqualTo($right)) {
                return $left;
            }

            return ArrayType::instance(false)->asUnionType();
        }

        if (($left->isType(IntType::instance(false))
            || $left->isType(FloatType::instance(false)))
            && ($right->isType(IntType::instance(false))
            || $right->isType(FloatType::instance(false)))
        ) {
            return FloatType::instance(false)->asUnionType();
        }

        $left_is_array = (
            !$left->genericArrayElementTypes()->isEmpty()
            && $left->nonArrayTypes()->isEmpty()
        ) || $left->isType(ArrayType::instance(false));

        $right_is_array = (
            !$right->genericArrayElementTypes()->isEmpty()
            && $right->nonArrayTypes()->isEmpty()
        ) || $right->isType(ArrayType::instance(false));

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
            return new UnionType();
        } elseif ($right_is_array
            && !$left->canCastToUnionType(ArrayType::instance(false)->asUnionType())
        ) {
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::TypeInvalidLeftOperand,
                $node->lineno ?? 0
            );
            return new UnionType();
        } elseif ($left_is_array || $right_is_array) {
            // If it is a '+' and we know one side is an array
            // and the other is unknown, assume array
            return ArrayType::instance(false)->asUnionType();
        }

        return new UnionType([
            IntType::instance(false),
            FloatType::instance(false)
        ]);
    }
}
