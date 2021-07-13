<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ASTReverter;
use Phan\Parse\ParseVisitor;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for occurrences of unsafe constructs such as shell_exec, eval(), etc.
 *
 * This file demonstrates plugins for Phan. Plugins hook into various events.
 * UnsafeCodePlugin hooks into one event:
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a visitor that is called on every AST node from every
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
class UnsafeCodePlugin extends PluginV3 implements PostAnalyzeNodeCapability
{

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return UnsafeCodeVisitor::class;
    }
}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class UnsafeCodeVisitor extends PluginAwarePostAnalysisVisitor
{

    // A plugin's visitors should not override visit() unless they need to.

    /**
     * @param Node $node a
     * A node of kind ast\AST_INCLUDE_OR_EVAL to analyze
     * @override
     */
    public function visitIncludeOrEval(Node $node): void
    {
        if ($node->flags !== ast\flags\EXEC_EVAL) {
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            $this->context,
            'PhanPluginUnsafeEval',
            'eval() is often unsafe and may have better alternatives such as closures and is unanalyzable. Suppress this issue if you are confident that input is properly escaped for this use case and there is no better way to do this.',
            []
        );
    }

    /**
     * @param Node $node a
     * A node of kind ast\AST_SHELL_EXEC to analyze
     * @override
     */
    public function visitShellExec(Node $node): void
    {
        if (!ParseVisitor::isConstExpr($node->children['expr'], ParseVisitor::CONSTANT_EXPRESSION_FORBID_NEW_EXPRESSION)) {
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginUnsafeShellExecDynamic',
                'This syntax for shell_exec() ({CODE}) is easily confused for a string and does not allow proper exit code/stderr handling, and is used with a non-constant. Consider proc_open() instead.',
                [ASTReverter::toShortString($node)]
            );
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            $this->context,
            'PhanPluginUnsafeShellExec',
            'This syntax for shell_exec() ({CODE}) is easily confused for a string and does not allow proper exit code/stderr handling. Consider proc_open() instead.',
            [ASTReverter::toShortString($node)]
        );
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new UnsafeCodePlugin();
