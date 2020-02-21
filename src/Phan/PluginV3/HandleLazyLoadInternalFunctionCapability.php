<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Phan\CodeBase;
use Phan\Language\Element\Func;

/**
 * HandleLazyLoadInternalFunctionCapability is used when you want to modify some subset of global functions used in the program,
 * when some global functions (such as register_shutdown_function) won't be loaded until the analysis phase.
 */
interface HandleLazyLoadInternalFunctionCapability
{
    /**
     * This method is called after Phan lazily loads a global internal function.
     *
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * The function that was just now added to $code_base
     */
    public function handleLazyLoadInternalFunction(
        CodeBase $code_base,
        Func $function
    ): void;
}
