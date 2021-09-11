<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast;
use ast\flags;
use ast\Node;
use Closure;
use Error;
use Exception;
use Phan\Analysis\PostOrderAnalysisVisitor;
use Phan\Analysis\RedundantCondition;
use Phan\AST\ASTReverter;
use Phan\AST\InferValue;
use Phan\AST\UnionTypeVisitor;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\Type\ObjectType;
use Phan\Language\UnionType;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;

use function count;

/**
 * Checks builtin expressions such as empty() for redundant/impossible conditions.
 */
class RedundantConditionVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @override
     */
    public function visitEmpty(Node $node): void
    {
        $var_node = $node->children['expr'];
        try {
            $type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $var_node, false);
        } catch (Exception $_) {
            return;
        }
        if (!$type->hasRealTypeSet()) {
            return;
        }
        $real_type = $type->getRealUnionType();
        if (!$real_type->containsTruthy()) {
            RedundantCondition::emitInstance(
                $var_node,
                $this->code_base,
                $this->context,
                Issue::RedundantCondition,
                [
                    ASTReverter::toShortString($var_node),
                    $type->getRealUnionType(),
                    'empty',
                ],
                static function (UnionType $type): bool {
                    return !$type->containsTruthy();
                }
            );
        } elseif (!$real_type->containsFalsey()) {
            RedundantCondition::emitInstance(
                $var_node,
                $this->code_base,
                $this->context,
                Issue::ImpossibleCondition,
                [
                    ASTReverter::toShortString($var_node),
                    $type->getRealUnionType(),
                    'empty',
                ],
                static function (UnionType $type): bool {
                    return !$type->containsFalsey();
                }
            );
        }
    }

    /**
     * Choose a more specific issue name based on where the issue was emitted from.
     * @param Node|int|string|float $node
     */
    private function chooseIssue($node, string $issue_name): string
    {
        return RedundantCondition::chooseSpecificImpossibleOrRedundantIssueKind($node, $this->context, $issue_name);
    }

    /**
     * Check if a match arm contains a comparison against an impossible value
     * @param Node|string|int|float $cond_node
     * @param UnionType $cond_type the initial union type of $cond_node
     * @param Node $arm_node a kind of ast\AST_MATCH_ARM
     */
    public function checkImpossibleMatchArm(
        $cond_node,
        UnionType $cond_type,
        Node $arm_node
    ): void {
        $code_base = $this->code_base;
        foreach ($arm_node->children['cond']->children ?? [] as $arm_expr) {
            $arm_expr_type = UnionTypeVisitor::unionTypeFromNode($code_base, $this->context, $arm_expr);
            $arm_expr_type = $arm_expr_type->getRealUnionType()->withStaticResolvedInContext($this->context);
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument
            if ($this->checkUselessScalarComparison($arm_node, $cond_type, $arm_expr_type, $cond_node, $arm_expr, ast\flags\BINARY_IS_IDENTICAL)) {
                continue;
            }
            if (!$cond_type->hasAnyTypeOverlap($code_base, $arm_expr_type)) {
                $this->emitIssueForBinaryOp(
                    $arm_node,
                    $cond_type,
                    $arm_expr_type,
                    Issue::ImpossibleTypeComparison,
                    static function (UnionType $new_left_type, UnionType $new_right_type) use ($code_base): bool {
                        return !$new_left_type->hasAnyTypeOverlap($code_base, $new_right_type);
                    },
                    $cond_node,
                    $arm_expr
                );
            }
        }
    }

    /**
    public function visitMatch(Node $node): void
    {
        ['cond' => $cond_node, 'stmts' => $stmts_node] = $node->children;
        $cond_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $cond_node);
        if (!$cond_type->hasRealTypeSet()) {
            return;
        }
        $cond_type = $cond_type->getRealUnionType()->withStaticResolvedInContext($this->context);
        $code_base = $this->code_base;
        foreach ($stmts_node->children as $arm_node) {
            foreach ($arm_node->children['cond']->children ?? [] as $arm_expr) {
                $arm_expr_type = UnionTypeVisitor::unionTypeFromNode($code_base, $this->context, $arm_expr);
                $arm_expr_type = $arm_expr_type->getRealUnionType()->withStaticResolvedInContext($this->context);
                // @phan-suppress-next-line PhanPartialTypeMismatchArgument
                if ($this->checkUselessScalarComparison($node, $cond_type, $arm_expr_type, $cond_node, $arm_expr, ast\flags\BINARY_IS_IDENTICAL)) {
                    continue;
                }
                if (!$cond_type->hasAnyTypeOverlap($code_base, $arm_expr_type)) {
                    $this->emitIssueForBinaryOp(
                        $node,
                        $cond_type,
                        $arm_expr_type,
                        Issue::ImpossibleTypeComparison,
                        static function (UnionType $new_left_type, UnionType $new_right_type) use ($code_base): bool {
                            return !$new_left_type->hasAnyTypeOverlap($code_base, $new_right_type);
                        },
                        $cond_node,
                        $arm_expr
                    );
                }
            }
        }
    }
     */

    public function visitBinaryOp(Node $node): void
    {
        switch ($node->flags) {
            case flags\BINARY_IS_IDENTICAL:
            case flags\BINARY_IS_NOT_IDENTICAL:
                $this->checkImpossibleComparison($node, true);
                break;
            case flags\BINARY_IS_EQUAL:
            case flags\BINARY_IS_NOT_EQUAL:
            case flags\BINARY_IS_SMALLER:
            case flags\BINARY_IS_SMALLER_OR_EQUAL:
            case flags\BINARY_IS_GREATER:
            case flags\BINARY_IS_GREATER_OR_EQUAL:
            case flags\BINARY_SPACESHIP:
                $this->checkImpossibleComparison($node, false);
                break;
            // BINARY_COALESCE is checked for redundant conditions in BlockAnalysisVisitor
            default:
                return;
        }
    }

    private const EQUALITY_CHECKS = [
        flags\BINARY_IS_EQUAL,
        flags\BINARY_IS_IDENTICAL,
        flags\BINARY_IS_NOT_EQUAL,
        flags\BINARY_IS_NOT_IDENTICAL,
    ];

    private function checkImpossibleComparison(Node $node, bool $strict): void
    {
        $left = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['left']);
        if (!$left->hasRealTypeSet()) {
            return;
        }
        $right = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['right']);
        if (!$right->hasRealTypeSet()) {
            return;
        }
        $code_base = $this->code_base;
        $left = $left->getRealUnionType()->withStaticResolvedInContext($this->context);
        $right = $right->getRealUnionType()->withStaticResolvedInContext($this->context);
        // $left_non_literal = $left->asNonLiteralType();
        // $right_non_literal = $right->asNonLiteralType();
        if ($this->checkUselessScalarComparison($node, $left, $right, $node->children['left'], $node->children['right'], $node->flags)) {
            return;
        }
        if (!$left->hasAnyTypeOverlap($code_base, $right) &&
            ($strict || (
                // Warn about 0 == non-zero-int, but not non-zero-int <= 0
                \in_array($node->flags, self::EQUALITY_CHECKS, true)
                ? !$left->hasAnyWeakTypeOverlap($right, $code_base)
                : !$left->asNonLiteralType()->hasAnyWeakTypeOverlap($right->asNonLiteralType(), $code_base)
            ))
        ) {
            $this->emitIssueForBinaryOp(
                $node,
                $left,
                $right,
                $strict ? Issue::ImpossibleTypeComparison : Issue::SuspiciousWeakTypeComparison,
                static function (UnionType $new_left_type, UnionType $new_right_type) use ($strict, $code_base): bool {
                    return !$new_left_type->hasAnyTypeOverlap($code_base, $new_right_type) && ($strict || !$new_left_type->hasAnyWeakTypeOverlap($new_right_type, $code_base));
                }
            );
        }
    }

    /**
     * @param Node|string|int|float $left_node
     * @param Node|string|int|float $right_node
     * @param int $flags a valid flags value for ast\AST_BINARY_OP
     * @suppress PhanAccessMethodInternal
     */
    private function checkUselessScalarComparison(
        Node $node,
        UnionType $left,
        UnionType $right,
        $left_node,
        $right_node,
        int $flags
    ): bool {
        // Give up if any of the sides aren't constant
        $left_values = $left->asScalarValues(true);
        if (!$left_values) {
            return false;
        }
        $right_values = $right->asScalarValues(true);
        if (!$right_values) {
            return false;
        }
        $issue_args = [
            ASTReverter::toShortString($left_node),
            $left,
            ASTReverter::toShortString($right_node),
            $right,
            // @phan-suppress-next-line PhanAccessClassConstantInternal
            PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$flags],
        ];
        $left_count = count($left_values);
        $right_count = count($right_values);
        if ($left_count * $right_count > 100) {
            return false;
        }
        $unique_results = [];
        try {
            foreach ($left_values as $left_value) {
                foreach ($right_values as $right_value) {
                    $value = InferValue::computeBinaryOpResult($left_value, $right_value, $flags);

                    $unique_results[\serialize($value)] = $value;
                    if (count($unique_results) > 1) {
                        return false;
                    }
                }
            }
        } catch (Error $_) {
            return false;
        }

        $context = $this->context;
        $code_base = $this->code_base;
        $check_as_if_in_loop_scope = $this->shouldCheckScalarAsIfInLoopScope($node, \reset($unique_results));
        if ($check_as_if_in_loop_scope) {
            ['left' => $left_node, 'right' => $right_node] = $node->children;
            $left_type_fetcher = RedundantCondition::getLoopNodeTypeFetcher($code_base, $left_node);
            $right_type_fetcher = RedundantCondition::getLoopNodeTypeFetcher($code_base, $right_node);
            if ($left_type_fetcher || $right_type_fetcher) {
                $left_type_fetcher = $left_type_fetcher ?? static function (Context $_) use ($left): UnionType {
                    return $left;
                };
                $right_type_fetcher = $right_type_fetcher ?? static function (Context $_) use ($right): UnionType {
                    return $right;
                };

                $context->deferCheckToOutermostLoop(static function (Context $context_after_loop) use ($code_base, $node, $left_type_fetcher, $right_type_fetcher, $left, $right, $issue_args, $context): void {
                    // Give up in any of these cases, for the left or right types
                    // 1. We don't know how to fetch the new type after the loop.
                    // 2. We don't know the real value of the new type after the loop.
                    // 3. The new type changed to anything else after the loop.
                    $new_left_type = $left_type_fetcher($context_after_loop);
                    if (!$new_left_type || $new_left_type->isEmpty() || !$left->isEqualTo($new_left_type)) {
                        return;
                    }
                    $new_right_type = $right_type_fetcher($context_after_loop);
                    if (!$new_right_type || $new_right_type->isEmpty() || !$right->isEqualTo($new_right_type)) {
                        return;
                    }
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        RedundantCondition::chooseSpecificImpossibleOrRedundantIssueKind($node, $context, Issue::SuspiciousValueComparison),
                        $node->lineno,
                        ...$issue_args
                    );
                });
                return true;
            }
        }
        // Go on to warn if the values comparison result doesn't vary.

        /** A context for choosing the name of the issue to emit. */
        $issue_context = $context;
        if ($issue_context->isInLoop() && !$check_as_if_in_loop_scope) {
            $issue_context = $issue_context->withoutLoops();
        }
        // Don't emit the loop version of this issue if this is in the outermost loop, but still emit it if this is a loop inside of a different loop.
        Issue::maybeEmit(
            $code_base,
            $context,
            RedundantCondition::chooseSpecificImpossibleOrRedundantIssueKind($node, $issue_context, Issue::SuspiciousValueComparison),
            $node->lineno,
            ...$issue_args
        );
        return true;
    }

    /**
     * @param Node $node a node resolving to 1 or more known scalars
     * @param int|string|float|null|Node|array|bool $evaluated_value
     */
    private function shouldCheckScalarAsIfInLoopScope(Node $node, $evaluated_value): bool
    {
        if (!$this->context->isInLoop()) {
            // This isn't even in a loop.
            return false;
        }
        // while loops and for loops have a cond node, foreach loops don't.
        $inner_loop_node_cond = $this->context->getInnermostLoopNode()->children['cond'] ?? null;
        if ($inner_loop_node_cond instanceof Node) {
            // For loops have a list of expressions, the last of which is a condition
            if ($inner_loop_node_cond->kind === ast\AST_EXPR_LIST) {
                $inner_loop_node_cond = \end($inner_loop_node_cond->children);
            }
            if ($inner_loop_node_cond === $node) {
                return (bool) $evaluated_value;
            }
        }
        return true;
    }

    /**
     * Emit an issue. If this is in a loop, defer the check until more is known about possible types of the variable in the loop.
     *
     * @param Node $node a node of kind AST_BINARY_OP
     * @param Closure(UnionType,UnionType):bool $is_still_issue
     * @param Node|string|int|float|null $left_node null to use the node of the binary op
     * @param Node|string|int|float|null $right_node null to use the node of the binary op
     * @suppress PhanAccessMethodInternal
     */
    public function emitIssueForBinaryOp(
        Node $node,
        UnionType $left,
        UnionType $right,
        string $issue_name,
        Closure $is_still_issue,
        $left_node = null,
        $right_node = null
    ): void {
        $left_node = $left_node ?? $node->children['left'];
        $right_node = $right_node ?? $node->children['right'];
        $issue_args = [
            ASTReverter::toShortString($left_node),
            $left,
            ASTReverter::toShortString($right_node),
            $right,
        ];
        $code_base = $this->code_base;
        $context = $this->context;

        // TODO: check $this->shouldCheckScalarAsIfInLoopScope($node) in internal uses.
        // e.g. should have some way to warn about `$x = []; while (!is_array($x)) { $x = null; }`
        if ($this->context->isInLoop()) {
            $left_type_fetcher = RedundantCondition::getLoopNodeTypeFetcher($code_base, $left_node);
            $right_type_fetcher = RedundantCondition::getLoopNodeTypeFetcher($code_base, $right_node);
            if ($left_type_fetcher || $right_type_fetcher) {
                $context->deferCheckToOutermostLoop(static function (Context $context_after_loop) use ($code_base, $node, $left_type_fetcher, $right_type_fetcher, $left, $right, $is_still_issue, $issue_name, $issue_args, $context): void {
                    $left = ($left_type_fetcher ? $left_type_fetcher($context_after_loop) : null) ?? $left;
                    if ($left->isEmpty()) {
                        return;
                    }
                    $right = ($right_type_fetcher ? $right_type_fetcher($context_after_loop) : null) ?? $right;
                    if ($right->isEmpty()) {
                        return;
                    }
                    if (!$is_still_issue($left, $right)) {
                        return;
                    }
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        RedundantCondition::chooseSpecificImpossibleOrRedundantIssueKind($node, $context, $issue_name),
                        $node->lineno,
                        ...$issue_args
                    );
                });
                return;
            }
        }
        Issue::maybeEmit(
            $code_base,
            $context,
            RedundantCondition::chooseSpecificImpossibleOrRedundantIssueKind($node, $context, $issue_name),
            $node->lineno,
            ...$issue_args
        );
    }

    /**
     * Checks if the conditional of a ternary conditional is always true/false
     * @override
     */
    /*
    public function visitConditional(Node $node) : void
    {
        $cond_node = $node->children['cond'];
        $cond_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $cond_node);
        if (!$cond_type->hasRealTypeSet()) {
            return;
        }
        $cond_type = $cond_type->getRealUnionType();

        if (!$cond_type->containsFalsey()) {
            RedundantCondition::emitInstance(
                $cond_node,
                $this->code_base,
                $this->context,
                Issue::RedundantCondition,
                [
                    ASTReverter::toShortString($cond_node),
                    $cond_type->getRealUnionType(),
                    'truthy',
                ],
                static function (UnionType $type) : bool {
                    return !$type->containsFalsey();
                }
            );
        } elseif (!$cond_type->containsTruthy()) {
            RedundantCondition::emitInstance(
                $cond_node,
                $this->code_base,
                $this->context,
                Issue::ImpossibleCondition,
                [
                    ASTReverter::toShortString($cond_node),
                    $cond_type->getRealUnionType(),
                    'truthy',
                ],
                static function (UnionType $type) : bool {
                    return !$type->containsTruthy();
                }
            );
        }
    }
     */

    /**
     * @override
     */
    public function visitIsset(Node $node): void
    {
        $var_node = $node->children['var'];
        try {
            $type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $var_node, false);
        } catch (Exception $_) {
            return;
        }
        if (!$type->hasRealTypeSet()) {
            return;
        }
        $real_type = $type->getRealUnionType();
        if (!$real_type->containsNullableOrUndefined()) {
            if (RedundantCondition::shouldNotWarnAboutIssetCheckForNonNullExpression($this->code_base, $this->context, $var_node)) {
                return;
            }
            RedundantCondition::emitInstance(
                $var_node,
                $this->code_base,
                (clone $this->context)->withLineNumberStart($node->lineno),
                Issue::RedundantCondition,
                [
                    ASTReverter::toShortString($var_node),
                    $real_type,
                    'isset'
                ],
                static function (UnionType $type): bool {
                    return !$type->containsNullableOrUndefined();
                }
            );
        } elseif ($real_type->isNull()) {
            RedundantCondition::emitInstance(
                $var_node,
                $this->code_base,
                (clone $this->context)->withLineNumberStart($node->lineno),
                Issue::ImpossibleCondition,
                [
                    ASTReverter::toShortString($var_node),
                    $real_type,
                    'isset'
                ],
                static function (UnionType $type): bool {
                    return $type->isNull();
                }
            );
        }
    }

    /**
     * Check if a loop is increasing or decreasing when it should be doing the opposite.
     * @override
     */
    public function visitFor(Node $node): void
    {
        $cond_list = $node->children['cond'];
        if (!$cond_list instanceof Node) {
            return;
        }
        $cond_node = \end($cond_list->children);
        if (!$cond_node instanceof Node) {
            return;
        }
        $loop_node = $node->children['loop'];
        if (!$loop_node instanceof Node) {
            return;
        }
        $increment_directions = RedundantConditionLoopCheck::extractIncrementDirections($this->code_base, $this->context, $loop_node);
        if (!$increment_directions) {
            return;
        }

        $comparison_directions = RedundantConditionLoopCheck::extractComparisonDirections($cond_node);
        if (!$comparison_directions) {
            return;
        }
        foreach ($increment_directions as $key => $is_increasing) {
            if (($comparison_directions[$key] ?? $is_increasing) === $is_increasing) {
                continue;
            }
            $this->emitIssue(
                Issue::SuspiciousLoopDirection,
                $cond_node->lineno,
                $is_increasing ? 'increase' : 'decrease',
                ASTReverter::toShortString($loop_node),
                ASTReverter::toShortString($cond_node)
            );
        }
    }

    /**
     * @override
     */
    public function visitInstanceof(Node $node): void
    {
        $expr_node = $node->children['expr'];
        $code_base = $this->code_base;
        try {
            $type = UnionTypeVisitor::unionTypeFromNode($code_base, $this->context, $expr_node, false);
        } catch (Exception $_) {
            return;
        }
        if (!$type->hasRealTypeSet()) {
            return;
        }

        $class_node = $node->children['class'];
        if (!($class_node instanceof Node)) {
            return;
        }

        $class_type = $this->getClassTypeFromNode($class_node);

        $real_type_unresolved = $type->getRealUnionType();
        $real_type = $real_type_unresolved->withStaticResolvedInContext($this->context);
        // The isEqualTo check was added to check for `$this instanceof static`
        // The isExclusivelyStringTypes check warns about everything else, e.g. `$subclass instanceof BaseClass`
        if ($real_type_unresolved->isEqualTo($class_type->asRealUnionType())
            || $real_type->isExclusivelySubclassesOf($code_base, $class_type)
        ) {
            RedundantCondition::emitInstance(
                $expr_node,
                $code_base,
                (clone $this->context)->withLineNumberStart($node->lineno),
                Issue::RedundantCondition,
                [
                    ASTReverter::toShortString($expr_node),
                    $real_type_unresolved,
                    $class_type,
                ],
                static function (UnionType $type) use ($code_base, $class_type): bool {
                    return $type->isExclusivelySubclassesOf($code_base, $class_type);
                }
            );
        } elseif (!$real_type->canPossiblyCastToClass($code_base, $class_type)) {
            RedundantCondition::emitInstance(
                $expr_node,
                $code_base,
                (clone $this->context)->withLineNumberStart($node->lineno),
                Issue::ImpossibleCondition,
                [
                    ASTReverter::toShortString($expr_node),
                    $real_type,
                    $class_type,
                ],
                static function (UnionType $type) use ($code_base, $class_type): bool {
                    return !$type->canPossiblyCastToClass($code_base, $class_type);
                }
            );
        }
    }

    private function getClassTypeFromNode(Node $class_node): Type
    {
        if ($class_node->kind === ast\AST_NAME) {
            $class_union_type = UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $class_node,
                false
            );
            if ($class_union_type->typeCount() === 1) {
                return $class_union_type->getTypeSet()[0];
            }
        }
        return ObjectType::instance(false);
    }

    private function warnForCast(Node $node, UnionType $real_expr_type, string $expected_type): void
    {
        $expr_node = $node->children['expr'];
        $this->emitIssue(
            $this->chooseIssue($expr_node, Issue::RedundantCondition),
            $expr_node->lineno ?? $node->lineno,
            ASTReverter::toShortString($expr_node),
            $real_expr_type,
            $expected_type
        );
    }

    /**
     * @override
     */
    public function visitCast(Node $node): void
    {
        // TODO: Check if the cast would throw an error at runtime, based on the type (e.g. casting object to string/int)
        $expr_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['expr']);
        if (!$expr_type->hasRealTypeSet()) {
            return;
        }
        $real_expr_type = $expr_type->getRealUnionType();
        if ($real_expr_type->containsNullableOrUndefined()) {
            return;
        }
        switch ($node->flags) {
            case \ast\flags\TYPE_BOOL:
                if ($real_expr_type->isExclusivelyBoolTypes()) {
                    $this->warnForCast($node, $real_expr_type, 'bool');
                }
                break;
            case \ast\flags\TYPE_LONG:
                if ($real_expr_type->intTypes()->isEqualTo($real_expr_type)) {
                    $this->warnForCast($node, $real_expr_type, 'int');
                }
                break;
            case \ast\flags\TYPE_DOUBLE:
                // the int `2` and the float `2.0` are not identical
                // in terms of json encoding, var_export, etc.
                if ($real_expr_type->floatTypes()->isEqualTo($real_expr_type)) {
                    if ($real_expr_type->intTypes()->isEmpty()) {
                        $this->warnForCast($node, $real_expr_type, 'float');
                    }
                }
                break;
            case \ast\flags\TYPE_STRING:
                if ($real_expr_type->stringTypes()->isEqualTo($real_expr_type)) {
                    $this->warnForCast($node, $real_expr_type, 'string');
                }
                break;
            case \ast\flags\TYPE_ARRAY:
                if ($real_expr_type->isExclusivelyArray()) {
                    $this->warnForCast($node, $real_expr_type, 'array');
                }
                break;
            case \ast\flags\TYPE_OBJECT:
                if ($real_expr_type->objectTypesStrict()->isEqualTo($real_expr_type)) {
                    $this->warnForCast($node, $real_expr_type, 'object');
                }
                break;
            // ignore other casts such as TYPE_NULL, TYPE_STATIC, TYPE_ITERABLE, TYPE_CALLABLE
        }
    }
}
