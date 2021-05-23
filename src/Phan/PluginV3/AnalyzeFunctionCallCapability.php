<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use ast\Node;
use Closure;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;

/**
 * AnalyzeFunctionCallCapability is used when you want to analyze the parameters passed to a function or method, whether or not the return value is used.
 * (e.g. for analyzing `my_printf($fmtstr, ...$args)`)
 *
 * @see ReturnTypeOverrideCapability for making the return type depend on the passed in parameters.
 */
interface AnalyzeFunctionCallCapability
{
    /**
     * @return array<string,Closure(CodeBase,Context,FunctionInterface,list<mixed>,?Node)>
     * maps FQSEN of function or method to a closure used to analyze the function in question.
     * '\A::foo' or 'A::foo' as a key will override a method, and '\foo' or 'foo' as a key will override a function.
     * Closure Type: function(CodeBase $code_base, Context $context, Func|Method $function, array $args, ?Node $node) : void {...}
     *
     * If compatibility with older Phan versions is needed, make the param for $node optional.
     *
     * Note that $function->getMostRecentParentNodeListForCall() can be used to get the parent node list of the current call (will be the empty array if fetching it failed).
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array;
}
