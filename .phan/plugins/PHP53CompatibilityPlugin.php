<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ASTReverter;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin contains examples of checks for code that would be incompatible with php 5.3.
 * This goes beyond what `backward_compatibility_checks` checks for.
 *
 * This file demonstrates plugins for Phan. Plugins hook into various events.
 * PHP53CompatibilityPlugin hooks into one event:
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
class PHP53CompatibilityPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return PHP53CompatibilityVisitor::class;
    }
}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class PHP53CompatibilityVisitor extends PluginAwarePostAnalysisVisitor
{

    // A plugin's visitors should not override visit() unless they need to.

    /**
     * @param Node $node
     * A node to analyze of kind ast\AST_ARRAY
     * @override
     */
    public function visitArray(Node $node): void
    {
        if ($node->flags === ast\flags\ARRAY_SYNTAX_SHORT) {
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginCompatibilityShortArray',
                "Short arrays ({CODE}) require support for php 5.4+",
                [ASTReverter::toShortString($node)]
            );
        }
    }

    /**
     * @param Node $node
     * A node to analyze of kind ast\AST_ARG_LIST
     * @override
     */
    public function visitArgList(Node $node): void
    {
        $lastArg = end($node->children);
        if ($lastArg instanceof Node && $lastArg->kind === ast\AST_UNPACK) {
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginCompatibilityArgumentUnpacking',
                "Argument unpacking ({CODE}) requires support for php 5.6+",
                [ASTReverter::toShortString($lastArg)]
            );
        }
    }

    /**
     * @param Node $node
     * A node to analyze of kind ast\AST_PARAM
     * @override
     */
    public function visitParam(Node $node): void
    {
        if ($node->flags & ast\flags\PARAM_VARIADIC) {
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginCompatibilityVariadicParam',
                "Variadic functions ({CODE}) require support for php 5.6+",
                [ASTReverter::toShortString($node)]
            );
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new PHP53CompatibilityPlugin();
