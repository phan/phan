<?php
declare(strict_types=1);

use ast\flags;
use ast\Node;
use Phan\Analysis\PostOrderAnalysisVisitor;
use Phan\AST\ASTHasher;
use Phan\AST\ASTReverter;
use Phan\PluginV2;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;
use Phan\PluginV2\PostAnalyzeNodeCapability;

/**
 * This plugin checks for duplicate expressions in a statement
 * that are likely to be a bug.
 *
 * - E.g. `expr1 == expr1`
 *
 * This file demonstrates plugins for Phan. Plugins hook into various events.
 * DuplicateExpressionPlugin hooks into one event:
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a visitor that is called on every AST node from every
 *   file being analyzed
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV2
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
class DuplicateExpressionPlugin extends PluginV2 implements PostAnalyzeNodeCapability
{

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return RedundantNodeVisitor::class;
    }
}

/**
 * This visitor analyzes node kinds that can be the root of expressions
 * containing duplicate expressions.
 */
class RedundantNodeVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * These are types of binary operations for which it is
     * likely to be a typo if both the left and right-hand sides
     * of the operation are the same.
     */
    const REDUNDANT_BINARY_OP_SET = [
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
    const BINARY_OP_BOTH_LITERAL_WARN_SET = [
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
     *
     * @return void
     * @override
     * @suppress PhanAccessClassConstantInternal
     */
    public function visitBinaryOp(Node $node)
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
        $this->emitPluginIssue(
            $this->code_base,
            $this->context,
            'PhanPluginBothLiteralsBinaryOp',
            'Suspicious usage of a binary operator where both operands are literals. Expression: {CODE} {OPERATOR} {CODE} (result is {CODE})',
            [
                ASTReverter::toShortString($left),
                PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$flags],
                ASTReverter::toShortString($right),
                ASTReverter::toShortString(self::computeResultForBothLiteralsWarning($left, $right, $flags)),
            ]
        );
    }

    /**
     * @param Node $node
     * An assignment operation node to analyze
     *
     * @return void
     * @override
     */
    public function visitAssignRef(Node $node)
    {
        $this->visitAssign($node);
    }

    /**
     * @param Node $node
     * An assignment operation node to analyze
     *
     * @return void
     * @override
     */
    public function visitAssign(Node $node)
    {
        $var = $node->children['var'];
        $expr = $node->children['expr'];
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
     * Compute result of a binary operator for a PhanPluginBothLiteralsBinaryOp warning
     * @param int|string|float|bool|null $left left hand side of operation
     * @param int|string|float|bool|null $right right hand side of operation
     * @param int $flags
     * @return int|string|float|bool|null
     */
    private static function computeResultForBothLiteralsWarning($left, $right, int $flags)
    {
        switch ($flags) {
            case flags\BINARY_BOOL_AND:
                return $left && $right;
            case flags\BINARY_BOOL_OR:
                return $left || $right;
            case flags\BINARY_BOOL_XOR:
                return $left xor $right;
            case flags\BINARY_IS_IDENTICAL:
                return $left === $right;
            case flags\BINARY_IS_NOT_IDENTICAL:
                return $left !== $right;
            case flags\BINARY_IS_EQUAL:
                return $left == $right;
            case flags\BINARY_IS_NOT_EQUAL:
                return $left != $right;
            case flags\BINARY_IS_SMALLER:
                return $left < $right;
            case flags\BINARY_IS_SMALLER_OR_EQUAL:
                return $left <= $right;
            case flags\BINARY_IS_GREATER:
                return $left > $right;
            case flags\BINARY_IS_GREATER_OR_EQUAL:
                return $left >= $right;
            case flags\BINARY_SPACESHIP:
                return $left <=> $right;
            case flags\BINARY_COALESCE:
                return $left ?? $right;
            default:
                return '(unknown)';
        }
    }

    /**
     * @return int|string|float|bool|null|Node the resolved value of $node, or $node if it could not be resolved
     */
    private static function resolveLiteralValue(Node $node)
    {
        if ($node->kind !== ast\AST_CONST) {
            return $node;
        }
        // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
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
     *
     * @return void
     * @override
     */
    public function visitConditional(Node $node)
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
     * @param int|string $true_node_hash
     */
    private function checkBinaryOpOfConditional(Node $cond_node, $true_node_hash)
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
    private function checkUnaryOpOfConditional(Node $cond_node, $true_node_hash)
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
            if ($function->kind !== ast\AST_NAME || strcasecmp((string)($function->children['name'] ?? ''), 'is_null') !== 0) {
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
    private static function isNullConstantNode($node) : bool
    {
        if (!$node instanceof Node) {
            return false;
        }
        return $node->kind === ast\AST_CONST && strcasecmp((string)$node->children['name']->children['name'] ?? '', 'null') === 0;
    }

    /**
     * @param ?(Node|string|int|float) $x_node
     */
    private function warnDuplicateConditionalNullCoalescing(string $expr, $x_node)
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

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.

return new DuplicateExpressionPlugin();
