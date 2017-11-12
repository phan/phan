<?php declare(strict_types=1);
namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Element\Clazz;

/**
 * AnalyzeFunctionCallCapability is used when you want to analyze the parameters passed to a function or method, whether or not the return value is used.
 * (e.g. for analyzing `my_printf($fmtstr, ...$args)`)
 *
 * @see AnalyzeFunctionCallCapability for making the return type depend on the passed in parameters.
 */
interface AnalyzeFunctionCallCapability
{
    /**
     * @return \Closure[] maps FQSEN of function or method to a closure used to analyze the function in question.
     *                    '\A::foo' or 'A::foo' as a key will override a method, and '\foo' or 'foo' as a key will override a function.
     *                    Closure Type: function(CodeBase $code_base, Context $context, Func|Method $function, array $args) : void {...}
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array;
}
