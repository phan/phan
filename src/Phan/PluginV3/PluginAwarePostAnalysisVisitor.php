<?php

declare(strict_types=1);

namespace Phan\PluginV3;

// use ast\Node;

/**
 * For plugins which define their own post-order analysis behaviors in the analysis phase.
 * Called on a node after PluginAwarePreAnalysisVisitor implementations.
 *
 * - visit<VisitSuffix>(...) (Override these methods)
 * - emitPluginIssue(CodeBase $code_base, Config $config, ...) (Call these methods)
 * - emit(...)
 * - Public methods from Phan\AST\AnalysisVisitor
 *
 * NOTE: Subclasses should not implement the visit() method unless they absolutely need to.
 * (E.g. if the body would be empty, or if it could be replaced with a few methods such as visitFuncDecl, visitVar, etc. that are more specific)
 *
 * - Phan is able to figure out which methods a subclass implements, and only call the plugin's visitor for those types,
 *   but only when the plugin's visitor does not override the fallback visit() method.
 *
 * Subclasses should declare protected $parent_node_list as an instance property if they need to know the list of parent nodes.
 */
abstract class PluginAwarePostAnalysisVisitor extends PluginAwareBaseAnalysisVisitor
{
    // Subclasses should declare protected $parent_node_list as an instance property if they need to know the list.

    // @var list<Node> - Set after the constructor is called if an instance property with this name is declared
    // protected $parent_node_list;

    // Implementations should omit the constructor or call parent::__construct(CodeBase $code_base, Context $context)
}
