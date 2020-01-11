<?php

declare(strict_types=1);

namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Element\Property;

/**
 * @deprecated use PluginV3 instead
 */
interface AnalyzePropertyCapability
{
    /**
     * Analyze (and modify) a property definition,
     * after parsing and before analyzing.
     *
     * @return void
     */
    public function analyzeProperty(
        CodeBase $code_base,
        Property $property
    );
}
