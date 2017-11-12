<?php declare(strict_types=1);
namespace Phan\PluginV2;

interface PreAnalyzeNodeCapability
{
    /**
     * Returns the name of the visitor class to be instantiated and invoked to pre-analyze a node in the analysis phase.
     * (To pre-analyze a node)
     * (PreAnalyzeNodeCapability is run before AnalyzeNodeCapability)
     * The class should be created by the plugin visitor, and must extend PluginAwarePreAnalysisVisitor.
     *
     * If state needs to be shared with a visitor and a plugin, a plugin author may use static variables of that plugin.
     *
     * @return string - The name of a class extending PluginAwarePreAnalysisVisitor
     */
    public static function getPreAnalyzeNodeVisitorClassName() : string;
}
