<?php declare(strict_types=1);

use ast\Node;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This file checks for empty statement lists in loops/branches.
 * Due to Phan's AST rewriting for easier analysis, this may miss some edge cases.
 *
 * It hooks into one event:
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a class that is called on every AST node from every
 *   file being analyzed
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
final class EmptyStatementListPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{

    /**
     * @return string - The name of the visitor that will be called.
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return EmptyStatementListVisitor::class;
    }
}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
final class EmptyStatementListVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitIf(Node $node) : void
    {
        // @phan-suppress-next-line PhanUndeclaredProperty set by ASTSimplifier
        if (isset($node->is_simplified)) {
            $last_if_elem = reset($node->children);
        } else {
            $last_if_elem = end($node->children);
        }
        if (!$last_if_elem instanceof Node) {
            // probably impossible
            return;
        }
        if (($last_if_elem->children['stmts']->children ?? null)) {
            // the last if element has statements
            return;
        }
        if ($last_if_elem->children['cond'] === null) {
            // Don't bother warning about else
            return;
        }

        $this->emitPluginIssue(
            $this->code_base,
            clone($this->context)->withLineNumberStart($last_if_elem->children['stmts']->lineno ?? $last_if_elem->lineno),
            'PhanPluginEmptyStatementIf',
            'Empty statement list statement detected for the last if/elseif statement',
            []
        );
    }

    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitFor(Node $node) : void
    {

        if (($node->children['stmts']->children ?? null) || ($node->children['loop']->children ?? null)) {
            // the for loop has statements, in the body and/or in the loop condition.
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            clone($this->context)->withLineNumberStart($node->children['stmts']->lineno ?? $node->lineno),
            'PhanPluginEmptyStatementForLoop',
            'Empty statement list statement detected for the for loop',
            []
        );
    }

    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitWhile(Node $node) : void
    {
        if ($node->children['stmts']->children ?? null) {
            // the while loop has statements
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            clone($this->context)->withLineNumberStart($node->children['stmts']->lineno ?? $node->lineno),
            'PhanPluginEmptyStatementWhileLoop',
            'Empty statement list statement detected for the while loop',
            []
        );
    }

    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitDoWhile(Node $node) : void
    {

        if ($node->children['stmts']->children ?? null) {
            // the while loop has statements
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            clone($this->context)->withLineNumberStart($node->children['stmts']->lineno ?? $node->lineno),
            'PhanPluginEmptyStatementDoWhileLoop',
            'Empty statement list statement detected for the do-while loop',
            []
        );
    }

    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitForeach(Node $node) : void
    {

        if ($node->children['stmts']->children ?? null) {
            // the while loop has statements
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            clone($this->context)->withLineNumberStart($node->children['stmts']->lineno ?? $node->lineno),
            'PhanPluginEmptyStatementForeachLoop',
            'Empty statement list statement detected for the foreach loop',
            []
        );
    }

    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitTry(Node $node) : void
    {
        if (!($node->children['try']->children ?? null)) {
            $this->emitPluginIssue(
                $this->code_base,
                clone($this->context)->withLineNumberStart($node->children['try']->lineno ?? $node->lineno),
                'PhanPluginEmptyStatementTryBody',
                'Empty statement list statement detected for the try statement\'s body',
                []
            );
        }
        if (isset($node->children['finally']) && !$node->children['finally']->children) {
            $this->emitPluginIssue(
                $this->code_base,
                clone($this->context)->withLineNumberStart($node->children['finally']->lineno),
                'PhanPluginEmptyStatementTryFinally',
                'Empty statement list statement detected for the try\'s finally body',
                []
            );
        }
    }
}
// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new EmptyStatementListPlugin();
