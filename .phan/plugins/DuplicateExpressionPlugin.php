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
     * @param Node $node
     * A binary operation node to analyze
     *
     * @return void
     * @override
     * @suppress PhanAccessClassConstantInternal
     */
    public function visitBinaryOp(Node $node)
    {
        if (!\array_key_exists($node->flags, self::REDUNDANT_BINARY_OP_SET)) {
            // Nothing to warn about
            return;
        }
        if (ASTHasher::hash($node->children['left']) === ASTHasher::hash($node->children['right'])) {
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginDuplicateExpressionBinaryOp',
                'Both sides of the binary operator {OPERATOR} are the same: {CODE}',
                [
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags],
                    ASTReverter::toShortString($node->children['left']),
                ]
            );
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
        if ($cond_node instanceof Node && $cond_node->kind === ast\AST_ISSET) {
            if (ASTHasher::hash($cond_node->children['var']) === $true_node_hash) {
                $this->emitPluginIssue(
                    $this->code_base,
                    $this->context,
                    'PhanPluginDuplicateConditionalNullCoalescing',
                    '"isset(X) ? X : Y" can usually be simplified to "X ?? Y" in PHP 7. The duplicated expression X was {CODE}',
                    [ASTReverter::toShortString($cond_node->children['var'])]
                );
            }
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.

return new DuplicateExpressionPlugin();
