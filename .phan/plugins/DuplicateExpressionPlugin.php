<?php

declare(strict_types=1);

use ast\flags;
use ast\Node;
use Phan\Analysis\PostOrderAnalysisVisitor;
use Phan\AST\ASTHasher;
use Phan\AST\ASTReverter;
use Phan\AST\InferValue;
use Phan\Config;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PluginAwarePreAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use Phan\PluginV3\PreAnalyzeNodeCapability;

/**
 * This plugin checks for duplicate expressions in a statement
 * that are likely to be a bug.
 *
 * - E.g. `expr1 == expr1`
 *
 * This file demonstrates plugins for Phan. Plugins hook into various events.
 * DuplicateExpressionPlugin hooks into two events:
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a visitor that is called on every AST node from every
 *   file being analyzed in post-order
 * - getPreAnalyzeNodeVisitorClassName
 *   This method returns a visitor that is called on every AST node from every
 *   file being analyzed in pre-order
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV3
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
class DuplicateExpressionPlugin extends PluginV3 implements
    PostAnalyzeNodeCapability,
    PreAnalyzeNodeCapability
{

    /**
     * @return class-string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return RedundantNodePostAnalysisVisitor::class;
    }

    /**
     * @return class-string - name of PluginAwarePreAnalysisVisitor subclass
     */
    public static function getPreAnalyzeNodeVisitorClassName(): string
    {
        return RedundantNodePreAnalysisVisitor::class;
    }
}

/**
 * This visitor analyzes node kinds that can be the root of expressions
 * containing duplicate expressions, and is called on nodes in post-order.
 */
class RedundantNodePostAnalysisVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * These are types of binary operations for which it is
     * likely to be a typo if both the left and right-hand sides
     * of the operation are the same.
     */
    private const REDUNDANT_BINARY_OP_SET = [
        flags\BINARY_BOOL_AND            => true,
        flags\BINARY_BOOL_OR             => true,
        flags\BINARY_BOOL_XOR            => true,
        flags\BINARY_BITWISE_OR          => true,
        flags\BINARY_BITWISE_AND         => true,
        flags\BINARY_BITWISE_XOR         => true,
        flags\BINARY_SUB                 => true,
        flags\BINARY_DIV                 => true,
        flags\BINARY_MOD                 => true,
        flags\BINARY_IS_IDENTICAL        => true,
        flags\BINARY_IS_NOT_IDENTICAL    => true,
        flags\BINARY_IS_EQUAL            => true,
        flags\BINARY_IS_NOT_EQUAL        => true,
        flags\BINARY_IS_SMALLER          => true,
        flags\BINARY_IS_SMALLER_OR_EQUAL => true,
        flags\BINARY_IS_GREATER          => true,
        flags\BINARY_IS_GREATER_OR_EQUAL => true,
        flags\BINARY_SPACESHIP           => true,
        flags\BINARY_COALESCE            => true,
    ];

    /**
     * A subset of REDUNDANT_BINARY_OP_SET.
     *
     * These binary operations will make this plugin warn if both sides are literals.
     */
    private const BINARY_OP_BOTH_LITERAL_WARN_SET = [
        flags\BINARY_BOOL_AND            => true,
        flags\BINARY_BOOL_OR             => true,
        flags\BINARY_BOOL_XOR            => true,
        flags\BINARY_IS_IDENTICAL        => true,
        flags\BINARY_IS_NOT_IDENTICAL    => true,
        flags\BINARY_IS_EQUAL            => true,
        flags\BINARY_IS_NOT_EQUAL        => true,
        flags\BINARY_IS_SMALLER          => true,
        flags\BINARY_IS_SMALLER_OR_EQUAL => true,
        flags\BINARY_IS_GREATER          => true,
        flags\BINARY_IS_GREATER_OR_EQUAL => true,
        flags\BINARY_SPACESHIP           => true,
        flags\BINARY_COALESCE            => true,
    ];

    /**
     * @param Node $node
     * A binary operation node to analyze
     * @override
     * @suppress PhanAccessClassConstantInternal
     */
    public function visitBinaryOp(Node $node): void
    {
        $flags = $node->flags;
        if (!\array_key_exists($flags, self::REDUNDANT_BINARY_OP_SET)) {
            // Nothing to warn about
            return;
        }
        $left = $node->children['left'];
        $right = $node->children['right'];
        if (ASTHasher::hash($left) === ASTHasher::hash($right)) {
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginDuplicateExpressionBinaryOp',
                'Both sides of the binary operator {OPERATOR} are the same: {CODE}',
                [
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags],
                    ASTReverter::toShortString($left),
                ]
            );
            return;
        }
        if (!\array_key_exists($flags, self::BINARY_OP_BOTH_LITERAL_WARN_SET)) {
            return;
        }
        if ($left instanceof Node) {
            $left = self::resolveLiteralValue($left);
            if ($left instanceof Node) {
                return;
            }
        }
        if ($right instanceof Node) {
            $right = self::resolveLiteralValue($right);
            if ($right instanceof Node) {
                return;
            }
        }
        try {
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument TODO: handle
            $result_representation = ASTReverter::toShortString(InferValue::computeBinaryOpResult($left, $right, $flags));
        } catch (Error $_) {
            $result_representation = '(unknown)';
        }
        $this->emitPluginIssue(
            $this->code_base,
            $this->context,
            'PhanPluginBothLiteralsBinaryOp',
            'Suspicious usage of a binary operator where both operands are literals. Expression: {CODE} {OPERATOR} {CODE} (result is {CODE})',
            [
                ASTReverter::toShortString($left),
                PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$flags],
                ASTReverter::toShortString($right),
                $result_representation,
            ]
        );
    }

    /**
     * @param Node $node
     * An assignment operation node to analyze
     * @override
     */
    public function visitAssignRef(Node $node): void
    {
        $this->visitAssign($node);
    }

    private const ASSIGN_OP_FLAGS = [
        flags\BINARY_BITWISE_OR => '|',
        flags\BINARY_BITWISE_AND => '&',
        flags\BINARY_BITWISE_XOR => '^',
        flags\BINARY_CONCAT => '.',
        flags\BINARY_ADD => '+',
        flags\BINARY_SUB => '-',
        flags\BINARY_MUL => '*',
        flags\BINARY_DIV => '/',
        flags\BINARY_MOD => '%',
        flags\BINARY_POW => '**',
        flags\BINARY_SHIFT_LEFT => '<<',
        flags\BINARY_SHIFT_RIGHT => '>>',
        flags\BINARY_COALESCE => '??',
    ];

    /**
     * @param Node $node
     * An assignment operation node to analyze
     * @override
     */
    public function visitAssign(Node $node): void
    {
        $expr = $node->children['expr'];
        if (!$expr instanceof Node) {
            // Guaranteed not to contain duplicate expressions in valid php assignments.
            return;
        }
        $var = $node->children['var'];
        if ($expr->kind === ast\AST_BINARY_OP) {
            $op_str = self::ASSIGN_OP_FLAGS[$expr->flags] ?? null;
            if (is_string($op_str) && ASTHasher::hash($var) === ASTHasher::hash($expr->children['left'])) {
                $message = 'Can simplify this assignment to {CODE} {OPERATOR} {CODE}';
                if ($expr->flags === ast\flags\BINARY_COALESCE) {
                    if (Config::get_closest_minimum_target_php_version_id() < 70400) {
                        return;
                    }
                    $message .= ' (requires php version 7.4 or newer)';
                }

                $this->emitPluginIssue(
                    $this->code_base,
                    $this->context,
                    'PhanPluginDuplicateExpressionAssignmentOperation',
                    $message,
                    [
                        ASTReverter::toShortString($var),
                        $op_str . '=',
                        ASTReverter::toShortString($expr->children['right']),
                    ]
                );
            }
            return;
        }
        if (ASTHasher::hash($var) === ASTHasher::hash($expr)) {
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginDuplicateExpressionAssignment',
                'Both sides of the assignment {OPERATOR} are the same: {CODE}',
                [
                    $node->kind === ast\AST_ASSIGN_REF ? '=&' : '=',
                    ASTReverter::toShortString($var),
                ]
            );
            return;
        }
    }

    /**
     * @return bool|null|Node the resolved value of $node, or $node if it could not be resolved
     * This could be more permissive about what constants are allowed (e.g. user-defined constants, real constants like PI, etc.),
     * but that may cause more false positives.
     */
    private static function resolveLiteralValue(Node $node)
    {
        if ($node->kind !== ast\AST_CONST) {
            return $node;
        }
        // @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal
        switch (\strtolower($node->children['name']->children['name'] ?? null)) {
            case 'false':
                return false;
            case 'true':
                return true;
            case 'null':
                return null;
            default:
                return $node;
        }
    }

    /**
     * @param Node $node
     * A binary operation node to analyze
     * @override
     */
    public function visitConditional(Node $node): void
    {
        $cond_node = $node->children['cond'];
        $true_node_hash = ASTHasher::hash($node->children['true']);

        if (ASTHasher::hash($cond_node) === $true_node_hash) {
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginDuplicateConditionalTernaryDuplication',
                '"X ? X : Y" can usually be simplified to "X ?: Y". The duplicated expression X was {CODE}',
                [ASTReverter::toShortString($cond_node)]
            );
            return;
        }
        $false_node_hash = ASTHasher::hash($node->children['false']);
        if ($true_node_hash === $false_node_hash) {
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginDuplicateConditionalUnnecessary',
                '"X ? Y : Y" results in the same expression Y no matter what X evaluates to. Y was {CODE}',
                [ASTReverter::toShortString($cond_node)]
            );
            return;
        }

        if (!$cond_node instanceof Node) {
            return;
        }
        switch ($cond_node->kind) {
            case ast\AST_ISSET:
                if (ASTHasher::hash($cond_node->children['var']) === $true_node_hash) {
                    $this->warnDuplicateConditionalNullCoalescing('isset(X) ? X : Y', $node->children['true']);
                }
                break;
            case ast\AST_BINARY_OP:
                $this->checkBinaryOpOfConditional($cond_node, $true_node_hash);
                break;
            case ast\AST_UNARY_OP:
                $this->checkUnaryOpOfConditional($cond_node, $true_node_hash);
                break;
        }
    }

    /**
     * @param Node $node
     * A statement list of kind ast\AST_STMT_LIST to analyze.
     * @override
     */
    public function visitStmtList(Node $node): void
    {
        $children = $node->children;
        if (count($children) < 2) {
            return;
        }
        $prev_hash = null;
        foreach ($children as $child) {
            $hash = ASTHasher::hash($child);
            if ($hash === $prev_hash) {
                $this->emitPluginIssue(
                    $this->code_base,
                    (clone($this->context))->withLineNumberStart($child->lineno ?? $node->lineno),
                    'PhanPluginDuplicateAdjacentStatement',
                    "Statement {CODE} is a duplicate of the statement on the above line. Suppress this issue instance if there's a good reason for this.",
                    [ASTReverter::toShortString($child)]
                );
            }
            $prev_hash = $hash;
        }
    }

    /**
     * @param int|string $true_node_hash
     */
    private function checkBinaryOpOfConditional(Node $cond_node, $true_node_hash): void
    {
        if ($cond_node->flags !== ast\flags\BINARY_IS_NOT_IDENTICAL) {
            return;
        }
        $left_node = $cond_node->children['left'];
        $right_node = $cond_node->children['right'];
        if (self::isNullConstantNode($left_node)) {
            if (ASTHasher::hash($right_node) === $true_node_hash) {
                $this->warnDuplicateConditionalNullCoalescing('null !== X ? X : Y', $right_node);
            }
        } elseif (self::isNullConstantNode($right_node)) {
            if (ASTHasher::hash($left_node) === $true_node_hash) {
                $this->warnDuplicateConditionalNullCoalescing('X !== null ? X : Y', $left_node);
            }
        }
    }

    /**
     * @param int|string $true_node_hash
     */
    private function checkUnaryOpOfConditional(Node $cond_node, $true_node_hash): void
    {
        if ($cond_node->flags !== ast\flags\UNARY_BOOL_NOT) {
            return;
        }
        $expr = $cond_node->children['expr'];
        if (!$expr instanceof Node) {
            return;
        }
        if ($expr->kind === ast\AST_CALL) {
            $function = $expr->children['expr'];
            if (!$function instanceof Node ||
                    $function->kind !== ast\AST_NAME ||
                    strcasecmp((string)($function->children['name'] ?? ''), 'is_null') !== 0
            ) {
                return;
            }
            $args = $expr->children['args']->children;
            if (count($args) !== 1) {
                return;
            }
            if (ASTHasher::hash($args[0]) === $true_node_hash) {
                $this->warnDuplicateConditionalNullCoalescing('!is_null(X) ? X : Y', $args[0]);
            }
        }
    }

    /**
     * @param Node|mixed $node
     */
    private static function isNullConstantNode($node): bool
    {
        if (!$node instanceof Node) {
            return false;
        }
        return $node->kind === ast\AST_CONST && strcasecmp((string)($node->children['name']->children['name'] ?? ''), 'null') === 0;
    }

    /**
     * @param ?(Node|string|int|float) $x_node
     */
    private function warnDuplicateConditionalNullCoalescing(string $expr, $x_node): void
    {
        $this->emitPluginIssue(
            $this->code_base,
            $this->context,
            'PhanPluginDuplicateConditionalNullCoalescing',
            '"' . $expr . '" can usually be simplified to "X ?? Y" in PHP 7. The duplicated expression X was {CODE}',
            [ASTReverter::toShortString($x_node)]
        );
    }
}

/**
 * This visitor analyzes node kinds that can be the root of expressions
 * containing duplicate expressions, and is called on nodes in pre-order.
 */
class RedundantNodePreAnalysisVisitor extends PluginAwarePreAnalysisVisitor
{
    /**
     * @override
     */
    public function visitIf(Node $node): void
    {
        if (count($node->children) <= 1) {
            // There can't be any duplicates.
            return;
        }
        // @phan-suppress-next-line PhanUndeclaredProperty
        if (isset($node->is_inside_else)) {
            return;
        }
        $children = self::extractIfElseifChain($node);
        // The checks of visitIf are done in pre-order (parent nodes analyzed before child nodes)
        // so that checked_duplicate_if can be set, to avoid redundant work.
        // @phan-suppress-next-line PhanUndeclaredProperty
        if (isset($node->checked_duplicate_if)) {
            return;
        }
        // @phan-suppress-next-line PhanUndeclaredProperty
        $node->checked_duplicate_if = true;
        ['cond' => $prev_cond /*, 'stmts' => $prev_stmts */] = $children[0]->children;
        // $prev_stmts_hash = ASTHasher::hash($prev_cond);
        $condition_set = [ASTHasher::hash($prev_cond) => true];
        $N = count($children);
        for ($i = 1; $i < $N; $i++) {
            ['cond' => $cond /*, 'stmts' => $stmts */] = $children[$i]->children;
            $cond_hash = ASTHasher::hash($cond);
            if (isset($condition_set[$cond_hash])) {
                $this->emitPluginIssue(
                    $this->code_base,
                    clone($this->context)->withLineNumberStart($cond->lineno ?? $children[$i]->lineno),
                    'PhanPluginDuplicateIfCondition',
                    'Saw the same condition {CODE} in an earlier if/elseif statement',
                    [ASTReverter::toShortString($cond)]
                );
            } else {
                $condition_set[$cond_hash] = true;
            }
        }
        if (!isset($cond)) {
            $stmts = $children[$N - 1]->children['stmts'];
            if (($stmts->children ?? null) && ASTHasher::hash($stmts) === ASTHasher::hash($children[$N - 2]->children['stmts'])) {
                $this->emitPluginIssue(
                    $this->code_base,
                    clone($this->context)->withLineNumberStart($children[$N - 1]->lineno),
                    'PhanPluginDuplicateIfStatements',
                    'The statements of the else duplicate the statements of the previous if/elseif statement with condition {CODE}',
                    [ASTReverter::toShortString($children[$N - 2]->children['cond'])]
                );
            }
        }
    }

    /**
     * Visit a node of kind ast\AST_TRY, to check for adjacent catch blocks
     *
     * @override
     * @suppress PhanPossiblyUndeclaredProperty
     */
    public function visitTry(Node $node): void
    {
        if (Config::get_closest_target_php_version_id() < 70100) {
            return;
        }
        $catches = $node->children['catches']->children ?? [];
        $n = count($catches);
        if ($n <= 1) {
            // There can't be any duplicates.
            return;
        }
        $prev_hash = ASTHasher::hash($catches[0]->children['stmts']) . ASTHasher::hash($catches[0]->children['var']);
        for ($i = 1; $i < $n; $prev_hash = $cur_hash, $i++) {
            $cur_hash = ASTHasher::hash($catches[$i]->children['stmts']) . ASTHasher::hash($catches[$i]->children['var']);
            if ($prev_hash === $cur_hash) {
                $this->emitPluginIssue(
                    $this->code_base,
                    clone($this->context)->withLineNumberStart($catches[$i]->lineno),
                    'PhanPluginDuplicateCatchStatementBody',
                    'The implementation of catch({CODE}) and catch({CODE}) are identical, and can be combined if the application only needs to supports php 7.1 and newer',
                    [
                        ASTReverter::toShortString($catches[$i - 1]->children['class']),
                        ASTReverter::toShortString($catches[$i]->children['class']),
                    ]
                );
            }
        }
    }

    /**
     * @param Node $node a node of kind ast\AST_IF
     * @return list<Node> the list of AST_IF_ELEM nodes making up the chain of if/elseif/else if conditions.
     * @suppress PhanPartialTypeMismatchReturn
     */
    private static function extractIfElseifChain(Node $node): array
    {
        $children = $node->children;
        if (count($children) <= 1) {
            return $children;
        }
        $last_child = \end($children);
        // Loop over the `} else {` blocks.
        // @phan-suppress-next-line PhanPossiblyUndeclaredProperty
        while ($last_child->children['cond'] === null) {
            $first_stmt = $last_child->children['stmts']->children[0] ?? null;
            if (!($first_stmt instanceof Node)) {
                break;
            }
            if ($first_stmt->kind !== ast\AST_IF) {
                break;
            }
            // @phan-suppress-next-line PhanUndeclaredProperty
            $first_stmt->is_inside_else = true;
            \array_pop($children);
            \array_push($children, ...$first_stmt->children);
            $last_child = \end($children);
        }
        return $children;
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.

return new DuplicateExpressionPlugin();
