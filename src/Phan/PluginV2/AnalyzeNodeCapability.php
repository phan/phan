<?php declare(strict_types=1);
namespace Phan\PluginV2;

interface AnalyzeNodeCapability
{
    /**
     * Returns the name of the visitor class to be instantiated and invoked to analyze a node in the analysis phase.
     * (To analyze a node. AnalyzeNodeCapability is run after PreAnalyzeNodeCapability)
     * The class should be created by the plugin visitor, and must extend PluginAwareAnalysisVisitor.
     *
     * If state needs to be shared with a visitor and a plugin, a plugin author may use static variables of that plugin.
     *
     * @return string - The name of a class extending PluginAwareAnalysisVisitor
     */
    public static function getAnalyzeNodeVisitorClassName() : string;
}
