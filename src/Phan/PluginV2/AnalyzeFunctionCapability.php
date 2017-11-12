<?php declare(strict_types=1);
namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Element\Func;

interface AnalyzeFunctionCapability
{
    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     *
     * @return void
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    );
}
