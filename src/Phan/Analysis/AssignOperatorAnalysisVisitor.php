<?php declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use ast\Node;
use Closure;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\Element;
use Phan\AST\Visitor\FlagVisitorImplementation;
use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\ScalarType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;

/**
 * This visitor determines the returned union type of an assignment operation.
 *
 * TODO: Improve analysis of bitwise operations, warn if non-int is provided and consistently return int if it's guaranteed
 */
class AssignOperatorAnalysisVisitor extends FlagVisitorImplementation
{

    /**
     * @var CodeBase The code base within which we're operating
     */
    private $code_base;

    /**
     * @var Context The context in which we are analyzing an assignment operator
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
     * @return Context
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
     * @return Context
     */
    public function visit(Node $node)
    {
        Issue::maybeEmit(
            $this->code_base,
            $this->context,
            Issue::Unanalyzable,
            $node->lineno ?? 0
        );
        return $this->context;
    }

    /**
     * @param Node $node a node of kind AST_VAR
     * @param Closure(UnionType):UnionType $get_type
     * @return Context
     */
    private function updateTargetVariableWithType(Node $node, Closure $get_type)
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
        if ($this->context->getScope()->hasVariableWithName(
            $variable_name
        )) {
            $variable =
                $this->context->getScope()->getVariableByName(
                    $variable_name
                );
            $variable->setUnionType($get_type($variable->getUnionType()));
        } else {
            if (Variable::isHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope())) {
                return $this->context;
            }
            // no such variable exists, warn about this
            // TODO: Add Suggestions
            Issue::maybeEmitWithParameters(
                $this->code_base,
                $this->context,
                Issue::UndeclaredVariableAssignOp,
                $node->lineno ?? 0,
                [$variable_name],
                IssueFixSuggester::suggestVariableTypoFix($this->code_base, $this->context, $variable_name)
            );
        }
        return $this->context;
    }

    /**
     * @param Node $node
     * @param Closure(UnionType):UnionType $get_type
     * @return Context
     */
    private function updateTargetWithType(Node $node, Closure $get_type) : Context
    {
        $left = $node->children['var'];
        // The left can be a non-Node for an invalid AST
        $kind = $left->kind ?? null;
        if ($kind === ast\AST_VAR) {
            return $this->updateTargetVariableWithType($node, $get_type);
        }
        // TODO: Could check types of other expressions, such as properties
        // TODO: Could check for `@property-read` (invalid to pass to assignment operator), etc.
        return $this->context;
    }

    /**
     * @return Context
     *
     * @see BinaryOperatorFlagVisitor::visitBinaryAdd() for analysis of "+", which is similar to "+="
     */
    public function visitBinaryAdd(Node $node)
    {
        return $this->updateTargetWithType($node, function (UnionType $left) use ($node) : UnionType {
            $code_base = $this->code_base;
            $context = $this->context;

            $right = UnionTypeVisitor::unionTypeFromNode(
                $code_base,
                $context,
                $node->children['expr']
            );

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

            $this->warnAboutInvalidUnionType(
                $node,
                static function (Type $type) : bool {
                    // TODO: Stricten this to warn about strings based on user config.
                    return $type instanceof ScalarType || $type instanceof ArrayType || $type instanceof MixedType;
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
            // @phan-suppress-previous-line PhanTypeMismatchArgumentNullable false positive from static init

            $right_is_array = (
                !$right->genericArrayElementTypes()->isEmpty()
                && $right->nonArrayTypes()->isEmpty()
            ) || $right->isType($array_type);
            // @phan-suppress-previous-line PhanTypeMismatchArgumentNullable false positive from static init

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
                        $code_base,
                        $context,
                        Issue::TypeInvalidRightOperand,
                        $node->lineno ?? 0
                    );
                    return UnionType::empty();
                    // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
                } elseif ($right_is_array && !$left->canCastToUnionType($array_type->asUnionType())) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
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
        });
    }

    /**
     * @return Context
     */
    public function visitBinaryCoalesce(Node $node)
    {
        $var_node = $node->children['var'];
        $new_node = new ast\Node(ast\AST_BINARY_OP, $node->lineno, [
            'left' => $var_node,
            'right' => $node->children['expr'],
        ], ast\flags\BINARY_COALESCE);

        $new_type = (new BinaryOperatorFlagVisitor(
            $this->code_base,
            $this->context,
            true
        ))->visitBinaryCoalesce($new_node);
        return (new AssignmentVisitor(
            $this->code_base,
            $this->context,
            $var_node,
            $new_type
        ))->__invoke($var_node);
    }

    private function analyzeNumericArithmeticOp(Node $node, bool $combination_is_int) : Context
    {
        return $this->updateTargetWithType($node, function (UnionType $left) use ($node, $combination_is_int) : UnionType {
            $code_base = $this->code_base;
            $context = $this->context;

            $right = UnionTypeVisitor::unionTypeFromNode(
                $code_base,
                $context,
                $node->children['expr']
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

            // fast-track common cases
            if ($left->isNonNullIntType() && $right->isNonNullIntType()) {
                if ($combination_is_int) {
                    return IntType::instance(false)->asUnionType();
                } else {
                    return $int_or_float_union_type;
                }
            }

            $this->warnAboutInvalidUnionType(
                $node,
                static function (Type $type) : bool {
                    // TODO: Stricten this to warn about strings based on user config.
                    return $type instanceof ScalarType || $type instanceof MixedType;
                },
                $left,
                $right,
                Issue::TypeInvalidLeftOperandOfNumericOp,
                Issue::TypeInvalidRightOperandOfNumericOp
            );

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
        });
    }

    /**
     * @param Node $node with type AST_BINARY_OP
     * @param Closure(Type):bool $is_valid_type
     * @return void
     *
     * TODO: Deduplicate and move to a trait?
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
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    $left_issue_type,
                    $node->children['var']->lineno ?? $node->lineno,
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags] . '=',
                    $left
                );
            }
        }
        if (!$right->isEmpty()) {
            if (!$right->hasTypeMatchingCallback($is_valid_type)) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    $right_issue_type,
                    $node->children['expr']->lineno ?? $node->lineno,
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags] . '=',
                    $right
                );
            }
        }
    }

    private function analyzeBitwiseOperation(Node $node) : Context
    {
        return $this->updateTargetWithType($node, function (UnionType $left_type) use ($node) : UnionType {
            // TODO: Warn about invalid left and right-hand sides here and in BinaryOperatorFlagVisitor.
            // Expect int|string

            $right_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['expr']);
            if ($right_type->hasStringType() || $left_type->hasStringType()) {
                if ($right_type->isNonNullStringType() && $left_type->isNonNullStringType()) {
                    return StringType::instance(false)->asUnionType();
                }
                return UnionType::fromFullyQualifiedString('int|string');
            }
            return IntType::instance(false)->asUnionType();
        });
    }

    /**
     * @return Context
     */
    public function visitBinaryBitwiseAnd(Node $node)
    {
        return $this->analyzeBitwiseOperation($node);
    }

    /**
     * @return Context
     */
    public function visitBinaryBitwiseOr(Node $node)
    {
        return $this->analyzeBitwiseOperation($node);
    }

    /**
     * @return Context
     */
    public function visitBinaryBitwiseXor(Node $node)
    {
        return $this->analyzeBitwiseOperation($node);
    }

    /**
     * @return Context
     */
    public function visitBinaryConcat(Node $node)
    {
        return $this->updateTargetWithType($node, static function (UnionType $unused_left) : UnionType {
            // TODO: Check if both sides can cast to string and warn if they can't.
            return StringType::instance(false)->asUnionType();
        });
    }

    /**
     * @return Context
     */
    public function visitBinaryDiv(Node $node)
    {
        return $this->analyzeNumericArithmeticOp($node, false);
    }

    /**
     * @return Context
     */
    public function visitBinaryMod(Node $node)
    {
        $this->warnForInvalidOperandsOfNumericOp($node);
        return $this->updateTargetWithType($node, static function (UnionType $unused_left) : UnionType {
            // TODO: Check if both sides can cast to string and warn if they can't.
            return IntType::instance(false)->asUnionType();
        });
    }

    /**
     * @return Context
     */
    public function visitBinaryMul(Node $node)
    {
        return $this->analyzeNumericArithmeticOp($node, true);
    }

    /**
     * @return Context
     */
    public function visitBinaryPow(Node $node)
    {
        // TODO: 2 ** (-2)  is a float
        return $this->analyzeNumericArithmeticOp($node, true);
    }

    /**
     * @return Context
     * TODO: There's an RFC to make binary shift left/right apply to strings.
     */
    public function visitBinaryShiftLeft(Node $node)
    {
        $this->analyzeBinaryShift($node);
        return $this->updateTargetWithType($node, static function (UnionType $unused_left) : UnionType {
            // TODO: Check if both sides can cast to int and warn if they can't.
            // TODO: Handle both sides being literals
            return IntType::instance(false)->asUnionType();
        });
    }

    /**
     * @return Context
     */
    public function visitBinaryShiftRight(Node $node)
    {
        $this->analyzeBinaryShift($node);
        return $this->updateTargetWithType($node, static function (UnionType $unused_left) : UnionType {
            // TODO: Check if both sides can cast to int and warn if they can't.
            // TODO: Handle both sides being literals
            return IntType::instance(false)->asUnionType();
        });
    }

    private function analyzeBinaryShift(Node $node)
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
        $this->warnAboutInvalidUnionType(
            $node,
            static function (Type $type) : bool {
                return $type instanceof IntType && !$type->getIsNullable();
            },
            $left,
            $right,
            Issue::TypeInvalidLeftOperandOfIntegerOp,
            Issue::TypeInvalidRightOperandOfIntegerOp
        );
    }

    private function warnForInvalidOperandsOfNumericOp(Node $node)
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
        $this->warnAboutInvalidUnionType(
            $node,
            static function (Type $type) : bool {
                return $type->isValidNumericOperand();
            },
            $left,
            $right,
            Issue::TypeInvalidLeftOperandOfNumericOp,
            Issue::TypeInvalidRightOperandOfNumericOp
        );
    }

    /**
     * @return Context
     */
    public function visitBinarySub(Node $node)
    {
        return $this->analyzeNumericArithmeticOp($node, true);
    }
}
