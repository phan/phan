<?php declare(strict_types=1);
namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Element\Method;

interface AnalyzeMethodCapability
{
    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    );
}
