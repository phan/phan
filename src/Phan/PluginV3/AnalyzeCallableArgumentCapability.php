<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Closure;
use Phan\CodeBase;

/**
 * AnalyzeCallableArgumentCapability is used when you want to analyze callable/Closure
 * arguments passed to ANY function or method, without needing to enumerate targets
 * or handle lazy loading.
 *
 * Unlike AnalyzeFunctionCallCapability where the plugin must discover which functions
 * have callable parameters and return a map keyed by FQSEN, this capability automatically
 * fires for every callable-typed argument across the entire codebase.
 *
 * The framework handles:
 * - Scanning all functions/methods for callable/Closure parameters
 * - Lazy-load handling for internal functions
 * - call_user_func / call_user_func_array / forward_static_call family
 * - Resolving callable arguments to FunctionInterface objects
 * - Named argument normalization
 *
 * @see AnalyzeFunctionCallCapability for lower-level per-function analysis
 */
interface AnalyzeCallableArgumentCapability
{
    /**
     * Returns a closure to analyze individual callable arguments.
     *
     * This closure is called once per callable-typed parameter that receives an argument.
     * For example, if `array_filter($arr, $callback)` is called, the closure fires once
     * for the $callback argument (param_index=1).
     *
     * @return Closure(\Phan\CodeBase,\Phan\Language\Context,\Phan\Language\Element\FunctionInterface,int,\ast\Node|int|string|float,list<\Phan\Language\Element\FunctionInterface>):void
     *
     * Parameters of the returned closure:
     * - CodeBase $code_base: The code base
     * - Context $context: Context of the call site (with correct line number)
     * - FunctionInterface $callee: The function/method being called (e.g. array_filter)
     * - int $param_index: The parameter index (0-based) of the callable parameter
     * - Node|int|string|float $arg_node: The raw argument AST node or literal value
     * - list<FunctionInterface> $resolved_callables: Pre-resolved callable targets (may be empty)
     */
    public function getAnalyzeCallableArgumentClosure(CodeBase $code_base): Closure;
}
