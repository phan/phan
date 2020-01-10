<?php

declare(strict_types=1);

namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Element\Func;

/**
 * Use PluginV3 instead.
 * @see \Phan\PluginV3\AnalyzeFunctionCapability
 */
interface AnalyzeFunctionCapability
{
    /**
     * @return void use pluginv3 instead
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    );
}
