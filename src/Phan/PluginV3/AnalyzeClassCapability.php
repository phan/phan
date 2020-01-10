<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Phan\CodeBase;
use Phan\Language\Element\Clazz;

/**
 * Plugins should implement this to be called to
 * analyze (and possibly modify) a class definition, after parsing and before analyzing.
 */
interface AnalyzeClassCapability extends \Phan\PluginV2\AnalyzeClassCapability
{
    /**
     * Analyze (and modify) a class definition, after parsing and before analyzing.
     *
     * @param CodeBase $code_base
     * The code base in which the class exists
     *
     * @param Clazz $class
     * A class being analyzed
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    ): void;
}
