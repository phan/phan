<?php

declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use ast\Node;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\UnionType;

/**
 * Runs the closures that change the context when a call of a function or method is used as a condition
 * (\Phan\PluginV3\ConditionalCallCapability, FunctionInterface::addConditionalCallClosure()).
 *
 * ConditionVisitor and NegatedConditionVisitor call analyze() after their own analysis of a call.
 * To keep conditions that call other functions cheap, only the calls whose function or method name is registered
 * here (registerFunctionName(), registerMethodName()) are resolved. Analysis::loadMethodPlugins() registers the
 * names of the closures of the plugins; code that adds closures without a plugin must register the names too.
 *
 * A call is resolved without reporting issues (the call is analyzed elsewhere, and a condition can be analyzed
 * several times): lookups that fail return null instead of emitting an issue. The closures only run if the call
 * resolves to exactly one function or method.
 */
final class ConditionalCallAnalyzer
{
    /** @var array<string,true> the lowercase names (without the namespace) of the functions that may have closures */
    private static $function_names = [];

    /** @var array<string,true> the lowercase names of the methods that may have closures */
    private static $method_names = [];

    /**
     * Forget the registered names. Analysis::loadMethodPlugins() calls this before it registers the names for an analysis,
     * so that the names of an earlier analysis in the same process (the daemon without pcntl, unit tests) are not kept.
     */
    public static function reset(): void
    {
        self::$function_names = [];
        self::$method_names = [];
    }

    /**
     * Register the name of a function that may have closures: calls of functions with this name are resolved.
     *
     * @param string $name the name or the FQSEN of the function (e.g. 'isSafe' or '\MyNS\isSafe'); the namespace is ignored
     */
    public static function registerFunctionName(string $name): void
    {
        $pos = \strrpos($name, '\\');
        if ($pos !== false) {
            $name = \substr($name, $pos + 1);
        }
        if ($name !== '') {
            self::$function_names[\strtolower($name)] = true;
        }
    }

    /**
     * Register the name of a method that may have closures: calls of methods with this name are resolved.
     *
     * @param string $name the name of the method, without the class
     */
    public static function registerMethodName(string $name): void
    {
        if ($name !== '') {
            self::$method_names[\strtolower($name)] = true;
        }
    }

    /**
     * Returns the context after the closures of the function or method called by $node (if they apply) ran on $context.
     *
     * @param Node $node a node of kind ast\AST_CALL, ast\AST_METHOD_CALL, ast\AST_NULLSAFE_METHOD_CALL or ast\AST_STATIC_CALL
     * @param bool $is_negated false if the call is assumed to be truthy, true if it is assumed to be falsey
     */
    public static function analyze(CodeBase $code_base, Context $context, Node $node, bool $is_negated): Context
    {
        if ($node->kind === ast\AST_CALL) {
            if (!self::$function_names) {
                return $context;
            }
            $function = self::resolveFunction($code_base, $context, $node);
        } else {
            if (!self::$method_names) {
                return $context;
            }
            if ($is_negated && self::isInNullsafeChain($node)) {
                // `$x?->isSafe($y)` is null without calling the method when `$x` is null:
                // that the expression is falsey does not mean that the method returned a falsey value.
                return $context;
            }
            $function = self::resolveMethod($code_base, $context, $node);
        }
        if ($function === null || !$function->hasConditionalCallClosure()) {
            return $context;
        }
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument the arguments of a call are a list
        $args = ArgumentType::normalizeNamedArgs($node->children['args']->children, $function);
        return $function->analyzeConditionalCall($code_base, $context, $node, $args, $is_negated);
    }

    /**
     * Returns the function called by $node (ast\AST_CALL), or null if it is not a call of a function with a registered name,
     * or if it does not resolve to a function.
     */
    private static function resolveFunction(CodeBase $code_base, Context $context, Node $node): ?FunctionInterface
    {
        $args_node = $node->children['args'];
        if (!$args_node instanceof Node || $args_node->kind === ast\AST_CALLABLE_CONVERT) {
            // First-class callable syntax (or partial application) creates a closure: it is not a call.
            return null;
        }
        $expr = $node->children['expr'];
        if (!$expr instanceof Node || $expr->kind !== ast\AST_NAME) {
            // A dynamic call such as $f($x)
            return null;
        }
        $name = $expr->children['name'];
        if (!\is_string($name)) {
            return null;
        }
        $lowercase_name = \strtolower($name);
        $pos = \strrpos($lowercase_name, '\\');
        if (!isset(self::$function_names[$pos !== false ? \substr($lowercase_name, $pos + 1) : $lowercase_name])) {
            // A function imported under another name, e.g. `use function ns\isSafe as ok;` for `ok($x)`
            if (!($expr->flags & ast\flags\NAME_NOT_FQ) || $pos !== false || !$context->hasNamespaceMapFor(ast\flags\USE_FUNCTION, $name)) {
                return null;
            }
            $fqsen = $context->getNamespaceMapFor(ast\flags\USE_FUNCTION, $name);
            if (!$fqsen instanceof FullyQualifiedFunctionName || !isset(self::$function_names[\strtolower($fqsen->getName())])) {
                return null;
            }
        }
        try {
            // getFunction() throws an IssueException instead of emitting an issue for an undeclared function.
            return (new ContextNode($code_base, $context, $expr))->getFunction($name);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Returns the method called by $node (ast\AST_METHOD_CALL, ast\AST_NULLSAFE_METHOD_CALL or ast\AST_STATIC_CALL), or null
     * if it is not a call of a method with a registered name, or if it does not resolve to exactly one method.
     */
    private static function resolveMethod(CodeBase $code_base, Context $context, Node $node): ?Method
    {
        $method_name = $node->children['method'];
        if (!\is_string($method_name) || !isset(self::$method_names[\strtolower($method_name)])) {
            return null;
        }
        $args_node = $node->children['args'];
        if (!$args_node instanceof Node || $args_node->kind === ast\AST_CALLABLE_CONVERT) {
            return null;
        }
        $is_static = $node->kind === ast\AST_STATIC_CALL;
        try {
            if ($is_static) {
                $class_node = $node->children['class'];
                if ($class_node instanceof Node && $class_node->kind === ast\AST_NAME) {
                    // `Foo::method()`, `self::method()`, `static::method()` or `parent::method()`.
                    // This throws an IssueException instead of emitting an issue.
                    $union_type = UnionTypeVisitor::unionTypeFromClassNode($code_base, $context, $class_node);
                } elseif ($class_node instanceof Node) {
                    // `$class::method()`
                    $union_type = UnionTypeVisitor::expandClassStringTypes(self::getTypeOfReceiver($code_base, $context, $class_node));
                } else {
                    return null;
                }
            } else {
                $expr = $node->children['expr'];
                if (!$expr instanceof Node) {
                    return null;
                }
                $union_type = self::getTypeOfReceiver($code_base, $context, $expr);
            }
            $result = null;
            foreach ($union_type->withStaticResolvedInContext($context)->nonNativeTypes()->getUniqueFlattenedTypeSet() as $type) {
                if (!$type->isObjectWithKnownFQSEN()) {
                    continue;
                }
                $class_fqsen = FullyQualifiedClassName::fromType($type);
                if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                    return null;
                }
                $class = $code_base->getClassByFQSEN($class_fqsen);
                if (!$class->hasMethodWithName($code_base, $method_name, true)) {
                    if ($is_static ? $class->allowsCallingUndeclaredStaticMethod($code_base) : $class->allowsCallingUndeclaredInstanceMethod($code_base)) {
                        // __call() or __callStatic() is another candidate
                        return null;
                    }
                    // Calling the method on an instance of this class would throw.
                    continue;
                }
                $method = $class->getMethodByName($code_base, $method_name);
                if ($result !== null && $result !== $method) {
                    // Several candidates, e.g. for `A|B` when both classes declare the method
                    return null;
                }
                $result = $method;
            }
            return $result;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return bool whether $node (a method call or a static call) can evaluate to null without the method being called:
     * `$x?->method()`, or a call that follows a nullsafe access in a chain, such as `$x?->prop->method()`
     */
    private static function isInNullsafeChain(Node $node): bool
    {
        if ($node->kind === ast\AST_STATIC_CALL) {
            $node = $node->children['class'];
            if (!$node instanceof Node) {
                return false;
            }
        }
        while (true) {
            switch ($node->kind) {
                case ast\AST_NULLSAFE_METHOD_CALL:
                case ast\AST_NULLSAFE_PROP:
                    return true;
                case ast\AST_METHOD_CALL:
                case ast\AST_PROP:
                case ast\AST_DIM:
                    $node = $node->children['expr'];
                    if (!$node instanceof Node) {
                        return false;
                    }
                    break;
                default:
                    return false;
            }
        }
    }

    /**
     * Returns the type of the receiver of a method call.
     * The condition visitors infer the type of the call before calling analyze(), which caches the type of the receiver
     * in the context (unless the context changed since, e.g. for a nullsafe call). Otherwise, the type is inferred with
     * $should_catch_issue_exception = false, so that most issues are thrown as an IssueException instead of being emitted.
     *
     * @throws \Exception (e.g. IssueException) if the type could not be inferred
     */
    private static function getTypeOfReceiver(CodeBase $code_base, Context $context, Node $node): UnionType
    {
        return $context->getUnionTypeOfNodeIfCached(\spl_object_id($node), true)
            ?? UnionTypeVisitor::unionTypeFromNode($code_base, $context, $node, false);
    }
}
