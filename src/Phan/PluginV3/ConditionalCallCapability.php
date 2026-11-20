<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use ast\Node;
use Closure;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;

/**
 * ConditionalCallCapability is used to change the Context (e.g. narrow the types of variables)
 * when a call of a function or method is used as a condition, e.g. `if (isSafe($x)) { ... }`.
 *
 * @see AnalyzeFunctionCallCapability for analyzing every call of a function or method
 */
interface ConditionalCallCapability
{
    /**
     * @return array<string,Closure(CodeBase,Context,FunctionInterface,Node,list<Node|string|int|float>,bool):Context>
     * maps the FQSEN of a function or method to a closure that returns the Context after the condition, keyed like
     * AnalyzeFunctionCallCapability::getAnalyzeFunctionCallClosures(): '\in_array' for a function, '\MyClass::isSafe' for a method.
     *
     * Closure type: function(CodeBase $code_base, Context $context, FunctionInterface $function, Node $call, array $args, bool $is_negated): Context {...}
     *
     * - $call is the node of the call (`ast\AST_CALL`, `ast\AST_METHOD_CALL`, `ast\AST_NULLSAFE_METHOD_CALL` or `ast\AST_STATIC_CALL`).
     * - $args are the arguments of the call, normalized to declaration order as for getAnalyzeFunctionCallClosures():
     *   named arguments are placed at the positions of their parameters, and the array may be sparse.
     * - $is_negated is false when the condition is assumed to be truthy (e.g. the body of `if (isSafe($x))`),
     *   and true when it is assumed to be falsey (e.g. `if (!isSafe($x))`, or the `else` branch).
     *
     * The closure must return a Context: the Context it was passed if it changes nothing, or a Context derived from it.
     * It runs after Phan's own narrowing for the call (e.g. for `in_array($x, ['a', 'b'], true)`).
     * It must not emit issues: the call is analyzed elsewhere, and a closure may be called more than once for the same call,
     * with or without negation (e.g. for `&&`, `||` and ternaries, and for the code after an `if` statement).
     *
     * The closure is only called when Phan resolves the call to exactly one function or method,
     * and not for first-class callable syntax (`isSafe(...)`). A closure for a method also applies to the
     * copies of that method that subclasses inherit without overriding it.
     * A method call is not resolved if the receiver may be an object of another class: a type such as `object`,
     * `mixed` or a `class-string` without a class, or a class that is not final and does not declare the method
     * (a subclass may declare it). It is not resolved either if the method cannot be called from the scope of the call
     * (a private method called from outside its class is an error, or a call of `__call()`).
     * For `isSafe($x) !== false` and `isSafe($x) !== true`, the closure is only called if the function or method
     * returns a bool: those comparisons are also true for other values.
     *
     * A closure for a method is also called when the receiver may be an instance of a subclass that overrides the method:
     * as for return types, an overriding method is expected to keep what the overridden method promises.
     * Register the closure for the class that declares the method. For a class that inherits it, the closure applies
     * to that class and not to its subclasses, as for getAnalyzeFunctionCallClosures().
     * For a call in a nullsafe chain (`$v?->isSafe($x)`, `$v?->prop->isSafe($x)`), the closure is not called for the
     * falsey case: the expression is null without calling the method when `$v` is null.
     * Closures of several plugins for the same function are called in the order the plugins are loaded,
     * and each one is passed the Context returned by the previous one.
     */
    public function getConditionalCallClosures(CodeBase $code_base): array;
}
