<?php declare(strict_types=1);

namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Element\Func;

/**
 * Use PluginV3 instead.
 */
interface HandleLazyLoadInternalFunctionCapability
{
    /**
     * This method is called after Phan lazily loads a global internal function.
     *
     * @return void
     */
    public function handleLazyLoadInternalFunction(
        CodeBase $code_base,
        Func $function
    );
}
