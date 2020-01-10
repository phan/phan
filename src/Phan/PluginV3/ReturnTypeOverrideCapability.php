<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Phan\CodeBase;

/**
 * ReturnTypeOverrideCapability is used when you want to get a return type of a function or method that is dependent on the arguments.
 * (e.g. for analyzing `my_printf`)
 *
 * @see AnalyzeFunctionCallCapability for analyzing the parameters whether or not return types are used.
 */
interface ReturnTypeOverrideCapability
{
    /**
     * @return array<string,\Closure>
     *         maps FQSEN of function or method to a closure used to override the returned UnionType.
     *         The returned type is not validated.
     *         '\A::foo' as a key will override a method, and '\foo' as a key will override a function.
     *         Closure type: function(CodeBase $code_base, Context $context, Func|Method $function, array $args) : UnionType {...}
     */
    public function getReturnTypeOverrides(CodeBase $code_base): array;
}
