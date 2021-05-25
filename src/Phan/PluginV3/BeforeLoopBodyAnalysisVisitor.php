<?php

declare(strict_types=1);

namespace Phan\PluginV3;

/**
 * For plugins that want to analyze loop conditions before the body.
 *
 * Public APIs for use by plugins:
 *
 * - visitForeach(...), visitFor(...), visitWhile(...) (Override these methods)
 * - emitPluginIssue(CodeBase $code_base, Config $config, ...) (Call these methods)
 * - emit(...)
 * - Public methods from Phan\AST\AnalysisVisitor
 *
 * TODO Parent interface is too broad
 */
abstract class BeforeLoopBodyAnalysisVisitor extends PluginAwareBaseAnalysisVisitor
{
    // Subclasses should declare protected $parent_node_list as an instance property if they need to know the list.

    // @var list<Node> - Set after the constructor is called if an instance property with this name is declared
    // protected $parent_node_list;

    // Implementations should omit the constructor or call parent::__construct(CodeBase $code_base, Context $context)
}
