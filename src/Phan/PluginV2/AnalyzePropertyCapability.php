<?php declare(strict_types=1);
namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Element\Property;

interface AnalyzePropertyCapability
{
    /**
     * @param CodeBase $code_base
     * The code base in which the property exists
     *
     * @param Property $property
     * A property being analyzed
     *
     * @return void
     */
    public function analyzeProperty(
        CodeBase $code_base,
        Property $property
    );
}
