<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use Phan\PluginV3;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin detects reuse of loop variables
 */
class LoopVariableReusePlugin extends PluginV3 implements PostAnalyzeNodeCapability
{
    /**
     * @return class-string - name of PluginAwarePostAnalysisVisitor subclass
     *
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return LoopVariableReuseVisitor::class;
    }
}
