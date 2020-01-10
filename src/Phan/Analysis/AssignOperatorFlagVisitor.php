<?php

declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\Element;
use Phan\AST\Visitor\FlagVisitorImplementation;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;

/**
 * This visitor returns a Context with the updated changes caused by an assignment operation (e.g. changes to Variables, Variable types)
 *
 * TODO: Improve analysis of bitwise operations, warn if non-int is provided and consistently return int if it's guaranteed
 *
 * TODO: Make the type inferences of this stay in sync with AssignOperatorAnalysisVisitor.
 */
class AssignOperatorFlagVisitor extends FlagVisitorImplementation
{

    /**
     * @var CodeBase The code base within which we're operating
     */
    private $code_base;

    /**
     * @var Context The context in which we are determining the union type of the result of an assignment operator
     */
    private $context;

    /**
     * Create a new AssignOperatorFlagVisitor
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
     * @return UnionType
     */
    public function __invoke(Node $node)
    {
        // NOTE: Some operations currently don't exist in any php version, such as `$x ||= 2;`, `$x xor= 2;`
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
    public function visit(Node $node): UnionType
    {
        // TODO: For some types (e.g. xor, bitwise or), set the type of the variable?
        // Or should that be done in PreOrderAnalysisVisitor?
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );

        if ($left->isExclusivelyArray()
            || $right->isExclusivelyArray()
        ) {
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::TypeArrayOperator,
                $node->lineno ?? 0,
                PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags],
                $left,
                $right
            );

            return UnionType::empty();
        } elseif ($left->hasNonNullIntType()
            && $right->hasNonNullIntType()
        ) {
            return IntType::instance(false)->asPHPDocUnionType();
        } elseif ($left->hasType(FloatType::instance(false))
            && $right->hasType(FloatType::instance(false))
        ) {
            return FloatType::instance(false)->asPHPDocUnionType();
        }

        static $int_or_float;
        return $int_or_float ?? ($int_or_float = UnionType::fromFullyQualifiedPHPDocString('int|float'));
    }

    public function visitBinaryCoalesce(Node $node): UnionType
    {
        $var_node = $node->children['var'];
        $new_node = new ast\Node(ast\AST_BINARY_OP, $node->lineno, [
            'left' => $var_node,
            'right' => $node->children['expr'],
        ], ast\flags\BINARY_COALESCE);

        return (new BinaryOperatorFlagVisitor(
            $this->code_base,
            $this->context,
            true
        ))->visitBinaryCoalesce($new_node);
    }
    /**
     * @return UnionType for the `&` operator
     */
    public function visitBinaryBitwiseAnd(Node $node): UnionType
    {
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );
        if ($left->hasNonNullIntType()
            && $right->hasNonNullIntType()
        ) {
            return IntType::instance(false)->asPHPDocUnionType();
        } elseif ($left->hasNonNullStringType() &&
            $right->hasNonNullStringType()) {
            // $x = 'a'; $x &= 'c';
            return StringType::instance(false)->asPHPDocUnionType();
        }
        return IntType::instance(false)->asPHPDocUnionType();
    }

    /**
     * @return UnionType for the `|` operator
     */
    public function visitBinaryBitwiseOr(Node $node): UnionType
    {
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );
        if ($left->hasNonNullIntType()
            && $right->hasNonNullIntType()
        ) {
            return IntType::instance(false)->asPHPDocUnionType();
        } elseif ($left->hasNonNullStringType() &&
            $right->hasNonNullStringType()) {
            // $x = 'a'; $x |= 'c';
            return StringType::instance(false)->asPHPDocUnionType();
        }
        return IntType::instance(false)->asPHPDocUnionType();
    }

    /**
     * Analyze the bitwise xor operator.
     *
     * NOTE: Code can bitwise xor strings byte by byte in PHP
     *
     * @return UnionType for the `^` operator
     */
    public function visitBinaryBitwiseXor(Node $node): UnionType
    {
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );

        // TODO: check for other invalid types
        if ($left->isExclusivelyArray()
            || $right->isExclusivelyArray()
        ) {
            // TODO: Move these checks into AssignOperatorAnalysisVisitor
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::TypeArrayOperator,
                $node->lineno ?? 0,
                PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags],
                $left,
                $right
            );

            return UnionType::empty();
        } elseif ($left->hasNonNullIntType()
            && $right->hasNonNullIntType()
        ) {
            return IntType::instance(false)->asPHPDocUnionType();
        } elseif ($left->hasNonNullStringType()
            && $right->hasNonNullStringType()
        ) {
            return StringType::instance(false)->asPHPDocUnionType();
        }

        return IntType::instance(false)->asPHPDocUnionType();
    }

    /**
     * @param Node $node @phan-unused-param
     * A node to check types on
     *
     * @return UnionType
     * The resulting type(s) of the binary operation
     */
    public function visitBinaryConcat(Node $node): UnionType
    {
        return StringType::instance(false)->asRealUnionType();
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
        static $int_or_float_or_array;
        static $probably_int_type;
        static $probably_array_type;
        static $probably_float_type;
        static $probably_int_or_float_type;
        static $unknown_type;
        if ($int_or_float_or_array === null) {
            $int_or_float_or_array = [IntType::instance(false), FloatType::instance(false), ArrayType::instance(false)];
            $probably_float_type = UnionType::of([FloatType::instance(false)], $int_or_float_or_array);
            $probably_int_or_float_type = UnionType::of([IntType::instance(false), FloatType::instance(false)], $int_or_float_or_array);
            $probably_int_type = UnionType::of([IntType::instance(false)], $int_or_float_or_array);
            $probably_array_type = UnionType::of([ArrayType::instance(false)], $int_or_float_or_array);
            $unknown_type = UnionType::of([], $int_or_float_or_array);
        }
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );

        // fast-track common cases
        if ($left->isNonNullIntType()
            && $right->isNonNullIntType()
        ) {
            return $probably_int_type;
        }

        // If both left and right are arrays, then this is array
        // concatenation.
        if ($left->isGenericArray() && $right->isGenericArray()) {
            if ($left->isEqualTo($right)) {
                return $left;
            }

            return $probably_array_type;
        }

        // TODO: isNonNullNumberType
        if (($left->isNonNullIntType()
            || $left->isType(FloatType::instance(false)))
            && ($right->isNonNullIntType()
            || $right->isType(FloatType::instance(false)))
        ) {
            return $probably_float_type;
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
                ArrayType::instance(false)->asPHPDocUnionType()
            )
        ) {
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::TypeInvalidRightOperand,
                $node->lineno ?? 0
            );
            return $unknown_type;
        } elseif ($right_is_array
            && !$left->canCastToUnionType(ArrayType::instance(false)->asPHPDocUnionType())
        ) {
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::TypeInvalidLeftOperand,
                $node->lineno ?? 0
            );
            return $unknown_type;
        } elseif ($left_is_array || $right_is_array) {
            // If it is a '+' and we know one side is an array
            // and the other is unknown, assume array
            return $probably_array_type;
        }

        return $probably_int_or_float_type;
    }

    /** @override */
    public function visitBinaryDiv(Node $_): UnionType
    {
        // analyzed in AssignOperatorAnalysisVisitor
        return FloatType::instance(false)->asRealUnionType();
    }

    /** @override */
    public function visitBinaryMul(Node $node): UnionType
    {
        // both sides are analyzed for issues in AssignOperatorAnalysisVisitor
        return $this->optimisticAnalyzeNumericOp($node);
    }

    /** @override */
    public function visitBinarySub(Node $node): UnionType
    {
        return $this->optimisticAnalyzeNumericOp($node);
    }

    private function optimisticAnalyzeNumericOp(Node $node): UnionType
    {
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );
        if ($left->hasNonNullIntType()
            && $right->hasNonNullIntType()
        ) {
            static $int_type = null;
            return $int_type ?? ($int_type = UnionType::of([IntType::instance(false)], [FloatType::instance(false)]));
        }
        // analyzed in AssignOperatorAnalysisVisitor
        return FloatType::instance(false)->asRealUnionType();
    }

    /** @override */
    public function visitBinaryMod(Node $_): UnionType
    {
        // analyzed in AssignOperatorAnalysisVisitor
        return IntType::instance(false)->asRealUnionType();
    }

    /** @override */
    public function visitBinaryPow(Node $_): UnionType
    {
        // analyzed in AssignOperatorAnalysisVisitor
        return FloatType::instance(false)->asRealUnionType();
    }

    /** @override */
    public function visitBinaryShiftLeft(Node $_): UnionType
    {
        return IntType::instance(false)->asRealUnionType();
    }

    /** @override */
    public function visitBinaryShiftRight(Node $_): UnionType
    {
        return IntType::instance(false)->asRealUnionType();
    }
}
