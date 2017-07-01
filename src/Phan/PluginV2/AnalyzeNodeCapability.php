<?php declare(strict_types=1);
namespace Phan\PluginV2;

interface AnalyzeNodeCapability {
    /**
     * Returns the name of the class to be instantiated and invoked in the analysis phase.
     * (To analyze a node, after preAnalysis is called)
     * The class should be created by the plugin visitor, and must extend PluginAwareAnalysisVisitor.
     *
     * @return string - The name of a class extending PluginAwareAnalysisVisitor
     */
    public static function getAnalyzeNodeVisitorClassName() : string;
}
