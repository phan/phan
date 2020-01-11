<?php

declare(strict_types=1);

namespace Phan\PluginV2;

/**
 * For plugins which define their own pre-order analysis behaviors in the analysis phase.
 * Called on a node before PluginAwarePreAnalysisVisitor implementations.
 *
 * @deprecated use PluginV3
 * @suppress PhanDeprecatedClass
 */
abstract class PluginAwarePreAnalysisVisitor extends PluginAwareBaseAnalysisVisitor
{
    // Subclasses should declare protected $parent_node_list as an instance property if they need to know the list.

    // @var list<Node> - Set after the constructor is called if an instance property with this name is declared
    // protected $parent_node_list;

    // Implementations should omit the constructor or call parent::__construct(CodeBase $code_base, Context $context)
}
