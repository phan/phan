<?php

declare(strict_types=1);

namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Element\Method;

/**
 * Use PluginV3 instead
 */
interface AnalyzeMethodCapability
{
    /**
     * Analyze (and modify) a method definition, after parsing and before analyzing.
     *
     * @return void
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    );
}
