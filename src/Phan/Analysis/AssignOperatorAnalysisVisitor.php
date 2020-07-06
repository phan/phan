<?php

declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use ast\Node;
use Closure;
use Phan\AST\ASTReverter;
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
use Phan\Language\FQSEN;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
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
     */
    public function visit(Node $node): Context
    {
        $this->emitIssue(
            Issue::Unanalyzable,
            $node->lineno
        );
        return $this->context;
    }

    /**
     * @param Node $node a node of kind AST_VAR
     * @param Closure(UnionType):UnionType $get_type
     */
    private function updateTargetVariableWithType(Node $node, Closure $get_type): Context
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
            $variable = clone(
                $this->context->getScope()->getVariableByName(
                    $variable_name
                )
            );
            $variable->setUnionType($get_type($variable->getUnionType()));
            return $this->context->withScopeVariable($variable);
        }

        if (Variable::isHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope())) {
            return $this->context;
        }
        // no such variable exists, warn about this
        Issue::maybeEmitWithParameters(
            $this->code_base,
            $this->context,
            Issue::UndeclaredVariableAssignOp,
            $node->lineno,
            [$variable_name],
            IssueFixSuggester::suggestVariableTypoFix($this->code_base, $this->context, $variable_name)
        );
        // Then create the variable
        $variable = new Variable(
            $this->context,
            $variable_name,
            $get_type(NullType::instance(false)->asPHPDocUnionType()),
            0
        );
        return $this->context->withScopeVariable($variable);
    }

    /**
     * Based on AssignmentVisitor->visitDim
     * @param Node $assign_op_node a node of kind ast\AST_ASSIGN_OP with ast\AST_DIM as the left hand side
     * @param Closure(UnionType):UnionType $get_type
     */
    private function updateTargetDimWithType(Node $assign_op_node, Closure $get_type): Context
    {
        $node = $assign_op_node->children['var'];
        if (!$node instanceof Node) {
            // Should be impossible as currently called, but warn anyway.
            $this->emitIssue(
                Issue::InvalidWriteToTemporaryExpression,
                $assign_op_node->lineno,
                ASTReverter::toShortString($node),
                Type::fromObject($node)
            );
            return $this->context;
        }
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
        $dim_node = $node->children['dim'];
        if ($expr_node->kind === \ast\AST_VAR) {
            $variable_name = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getVariableName();
            if (Variable::isHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope())) {
                if ($variable_name === 'GLOBALS') {
                    if (\is_string($dim_node)) {
                        $assign_op_node = new Node(ast\AST_ASSIGN_OP, 0, [
                            'var' => new Node(ast\AST_VAR, 0, ['name' => $dim_node], $node->lineno),
                            'expr' => $assign_op_node->children['expr'],
                        ], $assign_op_node->lineno);
                        if ($this->context->isInGlobalScope()) {
                            return $this->updateTargetWithType($assign_op_node, $get_type);
                        }
                        // TODO: Could handle using both `global $x` and `$GLOBALS['x']` in the same function (low priority)

                        // Modify the global scope
                        (new self(
                            $this->code_base,
                            $this->context->withScope(new GlobalScope())
                        ))->updateTargetWithType($assign_op_node, $get_type);
                        // fall through and return the context still inside of the function
                    }
                    return $this->context;
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

        try {
            $old_type = UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $node,
                false
            );
        } catch (\Exception $_) {
            return $this->context;
        }

        $new_type = $get_type($old_type);

        // Recurse into whatever we're []'ing
        return (new AssignmentVisitor(
            $this->code_base,
            $this->context,
            $node,
            $new_type
        ))->visitDim($node);
    }

    /**
     * Based on AssignmentVisitor->visitProp
     * @param Node $assign_op_node a node of kind ast\AST_ASSIGN_OP with ast\AST_PROP as the left hand side
     * @param Closure(UnionType):UnionType $get_type
     */
    private function updateTargetPropWithType(Node $assign_op_node, Closure $get_type): Context
    {
        $node = $assign_op_node->children['var'];
        if (!($node instanceof Node)) {
            $this->emitIssue(
                Issue::InvalidWriteToTemporaryExpression,
                $assign_op_node->lineno,
                ASTReverter::toShortString($node),
                Type::fromObject($node)
            );
            return $this->context;
        }
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

        try {
            $old_type = UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $node,
                false
            );
        } catch (\Exception $_) {
            return $this->context;
        }

        $new_type = $get_type($old_type);

        // Recurse into whatever we're []'ing
        return (new AssignmentVisitor(
            $this->code_base,
            $this->context,
            $node,
            $new_type
        ))->visitProp($node);
    }

    /**
     * @param Node $node
     * @param Closure(UnionType):UnionType $get_type
     */
    private function updateTargetWithType(Node $node, Closure $get_type): Context
    {
        $left = $node->children['var'];
        // The left can be a non-Node for an invalid AST
        $kind = $left->kind ?? null;
        if ($kind === ast\AST_VAR) {
            return $this->updateTargetVariableWithType($node, $get_type);
        } elseif ($kind === ast\AST_DIM) {
            return $this->updateTargetDimWithType($node, $get_type);
        } elseif ($kind === ast\AST_PROP) {
            return $this->updateTargetPropWithType($node, $get_type);
        }
        // TODO: Could check types of other expressions, such as properties
        // TODO: Could check for `@property-read` (invalid to pass to assignment operator), etc.
        return $this->context;
    }

    /**
     * @see BinaryOperatorFlagVisitor::visitBinaryAdd() for analysis of "+", which is similar to "+="
     */
    public function visitBinaryAdd(Node $node): Context
    {
        return $this->updateTargetWithType($node, function (UnionType $left) use ($node): UnionType {
            $code_base = $this->code_base;
            $context = $this->context;

            $right = UnionTypeVisitor::unionTypeFromNode(
                $code_base,
                $context,
                $node->children['expr']
            );

            // fast-track common cases
            if ($left->isNonNullIntType() && $right->isNonNullIntType()) {
                if (!$context->isInLoop()) {
                    return BinaryOperatorFlagVisitor::computeIntOrFloatOperationResult($node, $left, $right);
                }
                return IntType::instance(false)->asPHPDocUnionType();
            }

            // If both left and right are arrays, then this is array
            // concatenation.
            if ($left->isGenericArray() && $right->isGenericArray()) {
                BinaryOperatorFlagVisitor::checkInvalidArrayShapeCombination($this->code_base, $this->context, $node, $left, $right);
                if ($left->isEqualTo($right)) {
                    return $left;
                }
                return ArrayType::combineArrayTypesOverriding($left, $right, false);
            }

            $this->warnAboutInvalidUnionType(
                $node,
                static function (Type $type): bool {
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
                $int_or_float_union_type = UnionType::fromFullyQualifiedPHPDocString('int|float');
            }

            if ($left->isNonNullNumberType() && $right->isNonNullNumberType()) {
                if (!$context->isInLoop()) {
                    return BinaryOperatorFlagVisitor::computeIntOrFloatOperationResult($node, $left, $right);
                }
                if (!$left->hasNonNullIntType() || !$right->hasNonNullIntType()) {
                    // Heuristic: If one or more of the sides is a float, the result is always a float.
                    return $float_type->asPHPDocUnionType();
                }
                return $int_or_float_union_type;
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
                    return UnionType::empty();
                } elseif ($right_is_array && !$left->canCastToUnionType($array_type->asPHPDocUnionType())) {
                    $this->emitIssue(
                        Issue::TypeInvalidLeftOperand,
                        $node->lineno ?? 0
                    );
                    return UnionType::empty();
                }
                // If it is a '+' and we know one side is an array
                // and the other is unknown, assume array
                return $array_type->asPHPDocUnionType();
            }

            return $int_or_float_union_type;
        });
    }

    public function visitBinaryCoalesce(Node $node): Context
    {
        $var_node = $node->children['var'];
        if (!$var_node instanceof Node) {
            // nonsense like `2 ??= $x`
            $this->emitIssue(
                Issue::InvalidNode,
                $node->lineno,
                "Invalid left hand side for ??="
            );
            return $this->context;
        }
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

    private function analyzeNumericArithmeticOp(Node $node, bool $combination_is_int): Context
    {
        return $this->updateTargetWithType($node, function (UnionType $left) use ($node, $combination_is_int): UnionType {
            $code_base = $this->code_base;
            $context = $this->context;

            $right = UnionTypeVisitor::unionTypeFromNode(
                $code_base,
                $context,
                $node->children['expr']
            );
            if (!$right->isEmpty() && !$right->containsTruthy()) {
                $this->warnRightSideZero($node, $right);
            }

            static $float_type = null;
            static $int_or_float_union_type = null;
            if ($int_or_float_union_type === null) {
                $float_type = FloatType::instance(false);
                $int_or_float_union_type = UnionType::fromFullyQualifiedPHPDocString('int|float');
            }

            // fast-track common cases
            if ($left->isNonNullIntType() && $right->isNonNullIntType()) {
                if (!$context->isInLoop()) {
                    return BinaryOperatorFlagVisitor::computeIntOrFloatOperationResult($node, $left, $right);
                }
                if ($combination_is_int) {
                    // XXX can overflow to float so asRealUnionType isn't used.
                    return IntType::instance(false)->asPHPDocUnionType();
                } else {
                    return $int_or_float_union_type;
                }
            }

            $this->warnAboutInvalidUnionType(
                $node,
                static function (Type $type): bool {
                    // TODO: Stricten this to warn about strings based on user config.
                    return $type instanceof ScalarType || $type instanceof MixedType;
                },
                $left,
                $right,
                Issue::TypeInvalidLeftOperandOfNumericOp,
                Issue::TypeInvalidRightOperandOfNumericOp
            );

            if ($left->isNonNullNumberType() && $right->isNonNullNumberType()) {
                if (!$context->isInLoop()) {
                    return BinaryOperatorFlagVisitor::computeIntOrFloatOperationResult($node, $left, $right);
                }
                if (!$left->hasNonNullIntType() || !$right->hasNonNullIntType()) {
                    // Heuristic: If one or more of the sides is a float, the result is always a float.
                    // TODO: Return real types if both sides are real types, e.g. `$x = 2; $x += 3;`
                    return $float_type->asPHPDocUnionType();
                }
                return $int_or_float_union_type;
            }

            // TODO: warn about subtracting to/from non-number

            return $int_or_float_union_type;
        });
    }

    /**
     * Warn about the right hand side always casting to zero when used in a numeric operation.
     * @param UnionType $right_type a type that always casts to zero.
     */
    private function warnRightSideZero(Node $node, UnionType $right_type): void
    {
        $issue_type = PostOrderAnalysisVisitor::ISSUE_TYPES_RIGHT_SIDE_ZERO[$node->flags] ?? null;
        if (!\is_string($issue_type)) {
            return;
        }
        $this->emitIssue(
            $issue_type,
            $node->children['expr']->lineno ?? $node->lineno,
            ASTReverter::toShortString($node->children['expr']),
            $right_type
        );
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
    ): void {
        if (!$left->isEmpty()) {
            if (!$left->hasTypeMatchingCallback($is_valid_type)) {
                $this->emitIssue(
                    $left_issue_type,
                    $node->children['var']->lineno ?? $node->lineno,
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags] . '=',
                    $left
                );
            }
        }
        if (!$right->isEmpty()) {
            if (!$right->hasTypeMatchingCallback($is_valid_type)) {
                $this->emitIssue(
                    $right_issue_type,
                    $node->children['expr']->lineno ?? $node->lineno,
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags] . '=',
                    $right
                );
            }
        }
    }

    private function analyzeBitwiseOperation(Node $node): Context
    {
        return $this->updateTargetWithType($node, function (UnionType $left_type) use ($node): UnionType {
            // TODO: Warn about invalid left and right-hand sides here and in BinaryOperatorFlagVisitor.
            // TODO: Return real types if both sides are real types.
            // Expect int|string

            $right_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['expr']);

            $this->warnAboutInvalidUnionType(
                $node,
                static function (Type $type): bool {
                    return ($type instanceof IntType || $type instanceof StringType || $type instanceof MixedType) && !$type->isNullable();
                },
                $left_type,
                $right_type,
                Issue::TypeInvalidLeftOperandOfBitwiseOp,
                Issue::TypeInvalidRightOperandOfBitwiseOp
            );
            if (!$this->context->isInLoop()) {
                if ($left_type->isNonNullNumberType() && $right_type->isNonNullNumberType()) {
                    return BinaryOperatorFlagVisitor::computeIntOrFloatOperationResult($node, $left_type, $right_type);
                }
            }
            if ($right_type->hasStringType() || $left_type->hasStringType()) {
                if ($right_type->isNonNullStringType() && $left_type->isNonNullStringType()) {
                    return StringType::instance(false)->asPHPDocUnionType();
                }
                return UnionType::fromFullyQualifiedPHPDocString('int|string');
            }
            return IntType::instance(false)->asPHPDocUnionType();
        });
    }

    public function visitBinaryBitwiseAnd(Node $node): Context
    {
        return $this->analyzeBitwiseOperation($node);
    }

    public function visitBinaryBitwiseOr(Node $node): Context
    {
        return $this->analyzeBitwiseOperation($node);
    }

    public function visitBinaryBitwiseXor(Node $node): Context
    {
        return $this->analyzeBitwiseOperation($node);
    }

    public function visitBinaryConcat(Node $node): Context
    {
        return $this->updateTargetWithType($node, static function (UnionType $unused_left): UnionType {
            // TODO: Check if both sides can cast to string and warn if they can't.
            return StringType::instance(false)->asRealUnionType();
        });
    }

    public function visitBinaryDiv(Node $node): Context
    {
        return $this->analyzeNumericArithmeticOp($node, false);
    }

    public function visitBinaryMod(Node $node): Context
    {
        $this->warnForInvalidOperandsOfModOp($node);
        return $this->updateTargetWithType($node, function (UnionType $left) use ($node): UnionType {
            $right = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['expr']);
            if (!$this->context->isInLoop()) {
                if ($left->isNonNullNumberType() && $right->isNonNullNumberType()) {
                    return BinaryOperatorFlagVisitor::computeIntOrFloatOperationResult($node, $left, $right);
                }
            }
            // TODO: Check if both sides can cast to int and warn if they can't.
            return IntType::instance(false)->asRealUnionType();
        });
    }

    private function warnForInvalidOperandsOfModOp(Node $node): void
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
        if (!$right->isEmpty() && !$right->containsTruthy()) {
            $this->warnRightSideZero($node, $right);
        }
        $this->warnAboutInvalidUnionType(
            $node,
            static function (Type $type): bool {
                return $type->isValidNumericOperand();
            },
            $left,
            $right,
            Issue::TypeInvalidLeftOperandOfNumericOp,
            Issue::TypeInvalidRightOperandOfNumericOp
        );
    }


    public function visitBinaryMul(Node $node): Context
    {
        return $this->analyzeNumericArithmeticOp($node, true);
    }

    public function visitBinaryPow(Node $node): Context
    {
        // TODO: 2 ** (-2)  is a float
        return $this->analyzeNumericArithmeticOp($node, true);
    }

    /**
     * @return Context
     * NOTE: There's a draft RFC to make binary shift left/right apply to strings. (https://wiki.php.net/rfc/string-bitwise-shifts)
     * For now, it always casts to int.
     */
    public function visitBinaryShiftLeft(Node $node): Context
    {
        $this->analyzeBinaryShift($node);
        return $this->updateTargetWithType($node, static function (UnionType $unused_left): UnionType {
            // TODO: Check if both sides can cast to int and warn if they can't.
            // TODO: Handle both sides being literals
            return IntType::instance(false)->asRealUnionType();
        });
    }

    public function visitBinaryShiftRight(Node $node): Context
    {
        $this->analyzeBinaryShift($node);
        return $this->updateTargetWithType($node, static function (UnionType $unused_left): UnionType {
            // TODO: Check if both sides can cast to int and warn if they can't.
            // TODO: Handle both sides being literals
            return IntType::instance(false)->asRealUnionType();
        });
    }

    private function analyzeBinaryShift(Node $node): void
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
            static function (Type $type): bool {
                return ($type instanceof IntType || $type instanceof MixedType) && !$type->isNullable();
            },
            $left,
            $right,
            Issue::TypeInvalidLeftOperandOfIntegerOp,
            Issue::TypeInvalidRightOperandOfIntegerOp
        );
    }

    public function visitBinarySub(Node $node): Context
    {
        return $this->analyzeNumericArithmeticOp($node, true);
    }

    /**
     * @param string $issue_type
     * The type of issue to emit.
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
}
