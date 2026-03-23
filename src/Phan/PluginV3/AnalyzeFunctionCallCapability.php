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
     *
     * **Named arguments**: The `$args` array passed to closures is normalized to declaration
     * order — named arguments are unwrapped from AST_NAMED_ARG nodes and placed at their
     * parameter positions. However, `$args` may still be sparse (e.g. for omitted optional
     * parameters), so plugins should use `$args[$i] ?? null` to access the argument for
     * parameter `$i` regardless of whether positional or named arguments were used.
     *
     * **Lazy loading**: Internal PHP functions are lazy-loaded — their Func objects are only
     * created when first referenced during analysis. If this method dynamically discovers
     * functions (e.g. by iterating `$code_base->getFunctionMap()`), some internal functions
     * may not yet be loaded. Implement {@see HandleLazyLoadInternalFunctionCapability} to
     * register closures for individual functions as they are loaded.
     *
     * **`call_user_func` / `call_user_func_array`**: Phan's built-in CallableParamPlugin
     * removes its closures for these two functions because ClosureReturnTypeOverridePlugin
     * handles them instead. If your plugin needs to analyze callable arguments passed to
     * `call_user_func` or `call_user_func_array`, you must register closures for them
     * explicitly in the map returned by this method.
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array;
}
