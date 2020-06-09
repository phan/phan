<?php

declare(strict_types=1);

namespace Phan\PluginV3;

/**
 * Plugins can implement this to specify a visitor to analyze loop conditions before the body.
 *
 * Note: If $this->parent_node_list is declared as an instance property, then that will automatically get set.
 * $this->parent_node_list will have the elements closest to the current node at the end.
 *
 * - If that property is absent, it will not be set.
 */
interface BeforeLoopBodyAnalysisCapability
{
    /**
     * Returns the name of the visitor class to be instantiated and invoked to analyze loop conditions before the body.
     *
     * The class should be created by the plugin visitor, and must extend BeforeLoopBodyAnalysisVisitor.
     *
     * @return class-string - The name of a class extending BeforeLoopBodyAnalysisVisitor
     */
    public static function getBeforeLoopBodyAnalysisVisitorClassName(): string;
}
