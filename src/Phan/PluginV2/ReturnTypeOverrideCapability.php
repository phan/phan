<?php declare(strict_types=1);
namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Element\Clazz;

interface ReturnTypeOverrideCapability {
    /**
     * @return \Closure[] maps FQSEN of function or method to a closure used to override the return type.
     *                    The returned type is not validated.
     *                    '\A::foo' as a key will override a method, and '\foo' as a key will override a function.
     */
    public function getReturnTypeOverrides(CodeBase $code_base) : array;
}
