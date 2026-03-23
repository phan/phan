<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Closure;
use Phan\CodeBase;

/**
 * AnalyzeCallableArgumentCapability is used when you want to analyze callable/Closure
 * arguments passed to functions or methods, without needing to enumerate targets
 * or manually handle most lazy loading.
 *
 * Unlike AnalyzeFunctionCallCapability where the plugin must discover which functions
 * have callable parameters and return a map keyed by FQSEN, this capability automatically
 * fires for every callable-typed argument across the entire codebase.
 *
 * The framework handles:
 * - Scanning known functions/methods for callable/Closure parameters
 * - Lazy-load handling for internal functions (not internal methods)
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
     * To resolve the callable argument to actual function/method objects, use:
     *   UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $arg_node, false)
     *
     * @return Closure(\Phan\CodeBase,\Phan\Language\Context,\Phan\Language\Element\FunctionInterface,int,\ast\Node|int|string|float):void
     *
     * Parameters of the returned closure:
     * - CodeBase $code_base: The code base
     * - Context $context: Context of the call site (with correct line number)
     * - FunctionInterface $callee: The function/method being called (e.g. array_filter)
     * - int $param_index: The parameter index (0-based) of the callable parameter
     * - Node|int|string|float $arg_node: The raw argument AST node or literal value
     */
    public function getAnalyzeCallableArgumentClosure(CodeBase $code_base): Closure;
}
