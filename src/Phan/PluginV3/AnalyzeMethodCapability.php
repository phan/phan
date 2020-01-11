<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Phan\CodeBase;
use Phan\Language\Element\Method;

/**
 * Plugins can implement this to analyze (and modify) a method definition,
 * after parsing and before analyzing.
 */
interface AnalyzeMethodCapability extends \Phan\PluginV2\AnalyzeMethodCapability
{
    /**
     * Analyze (and modify) a method definition, after parsing and before analyzing.
     *
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ): void;
}
