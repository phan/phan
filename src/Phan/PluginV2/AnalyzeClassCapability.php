<?php declare(strict_types=1);

namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Element\Clazz;

/**
 * Plugins should move to PluginV3
 * @see \Phan\PluginV3\AnalyzeClassCapability
 */
interface AnalyzeClassCapability
{
    /** @return void deprecated */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    );
}
