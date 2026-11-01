<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Phan\CodeBase;
use Phan\Language\Element\Method;

/**
 * HandleLazyLoadInternalMethodCapability is used when you want to modify the methods of internal classes,
 * when some internal classes (such as Closure or ReflectionClass) won't be loaded until the analysis phase.
 *
 * The methods of the internal classes loaded before the analysis phase can be found with CodeBase::getMethodSet() instead,
 * e.g. in AnalyzeFunctionCallCapability::getAnalyzeFunctionCallClosures().
 */
interface HandleLazyLoadInternalMethodCapability
{
    /**
     * This method is called for each method of an internal class after Phan lazily loads that class.
     *
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method of the class that was just now added to $code_base
     */
    public function handleLazyLoadInternalMethod(
        CodeBase $code_base,
        Method $method
    ): void;
}
