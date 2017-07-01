<?php declare(strict_types=1);

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeNodeCapability;
use Phan\PluginV2\PluginAwareAnalysisVisitor;
use ast\Node;

/**
 * This file demonstrates plugins for Phan. Plugins hook into
 * four events;
 *
 * - getAnalyzeNodeVisitorClassName
 *   This method returns a visitor that is called on every AST node from every
 *   file being analyzed
 *
 * - analyzeClass
 *   Once all classes have been parsed, this method will be
 *   called on every class that is found in the code base
 *
 * - analyzeMethod
 *   Once all methods are parsed, this method will be called
 *   on every method in the code base
 *
 * - analyzeFunction
 *   Once all functions have been parsed, this method will
 *   be called on every function in the code base.
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\Plugin
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
class DollarDollarPlugin extends PluginV2 implements AnalyzeNodeCapability {

    /**
     * @return string - name of PluginAwareAnalysisVisitor subclass
     */
    public static function getAnalyzeNodeVisitorClassName() : string {
        return DollarDollarVisitor::class;
    }
}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class DollarDollarVisitor extends PluginAwareAnalysisVisitor {

    // A plugin's visitors should not override visit() unless they need to.

    /**
     * @param Node $node
     * A node to analyze
     *
     * @return void
     * @override
     */
    public function visitVar(Node $node) {
        if ($node->children['name'] instanceof Node) {
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginDollarDollar',
                "$$ Variables are not allowed.",
                []
            );
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return new DollarDollarPlugin;
