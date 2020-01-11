<?php

declare(strict_types=1);

namespace Phan\PluginV3;

/**
 * Plugins can implement this to have their visitor run to analyze a node in the analysis phase
 * (To post-analyze the node)
 *
 * @see PostAnalyzeNodeCapability::getPostAnalyzeNodeVisitorClassName()
 *
 * Note: If $this->parent_node_list is declared as an instance property, then that will automatically get set.
 * $this->parent_node_list will have the elements closest to the current node at the end.
 *
 * - If that property is absent, it will not be set.
 */
interface PostAnalyzeNodeCapability
{
    /**
     * Returns the name of the visitor class to be instantiated and invoked to analyze a node in the analysis phase.
     * (To post-analyze a node)
     * (PostAnalyzeNodeCapability is run after PreAnalyzeNodeCapability and after analysis of child nodes)
     *
     * The class should be created by the plugin visitor, and must extend PluginAwarePostAnalysisVisitor.
     *
     * If state needs to be shared with a visitor and a plugin, a plugin author may use static variables of that plugin.
     *
     * @return string - The name of a class extending PluginAwarePostAnalysisVisitor
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string;
}
