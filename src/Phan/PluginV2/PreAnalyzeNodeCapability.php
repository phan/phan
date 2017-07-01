<?php declare(strict_types=1);
namespace Phan\PluginV2;

interface PreAnalyzeNodeCapability {
    /**
     * Returns the name of the class to be instantiated and invoked in the analysis phase.
     * (To pre-analyze a node)
     * The class should be created by the plugin visitor, and must extend PluginAwarePreAnalysisVisitor.
     *
     * @return string
     */
    public static function getPreAnalyzeNodeVisitorClassName() : string;
}
