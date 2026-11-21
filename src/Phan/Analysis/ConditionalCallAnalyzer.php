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
use Phan\Language\Type\ClassStringType;
use Phan\Language\Type\StringType;
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
 * resolves to exactly one function or method, which is the one that the call runs: not if the receiver may be an
 * object of another class, and not if the method cannot be called from the scope of the call.
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
     * @param bool $requires_bool true if that assumption is only valid for a function that returns a bool:
     * `f($x) !== false` is also true for 0, and `f($x) !== true` is also true for 1.
     */
    public static function analyze(CodeBase $code_base, Context $context, Node $node, bool $is_negated, bool $requires_bool = false): Context
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
            if (($is_negated || $requires_bool) && self::isInNullsafeChain($node)) {
                // `$x?->isSafe($y)` is null without calling the method when `$x` is null:
                // that the expression is falsey does not mean that the method returned a falsey value,
                // and `$x?->isSafe($y) !== false` is true for null.
                return $context;
            }
            $function = self::resolveMethod($code_base, $context, $node);
        }
        if ($function === null || !$function->hasConditionalCallClosure()) {
            return $context;
        }
        if ($requires_bool && !$function->getUnionType()->isExclusivelyBoolTypes()) {
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
                    // `$class::method()`, for an object or the name of a class
                    $union_type = UnionType::empty();
                    foreach (self::getTypeOfReceiver($code_base, $context, $class_node)->getTypeSet() as $type) {
                        if ($type instanceof ClassStringType) {
                            $class_union_type = $type->getClassUnionTypeResolvingBounds();
                            if ($class_union_type->isEmpty()) {
                                // `class-string` can be the name of any class.
                                return null;
                            }
                            $union_type = $union_type->withUnionType($class_union_type);
                        } elseif ($type instanceof StringType) {
                            // A string can be the name of any class.
                            return null;
                        } else {
                            $union_type = $union_type->withType($type);
                        }
                    }
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
            $accessing_class_fqsen = $context->getClassFQSENOrNull();
            foreach ($union_type->withStaticResolvedInContext($context)->getUniqueFlattenedTypeSet() as $type) {
                if (!$type->isObjectWithKnownFQSEN()) {
                    if ($type->isPossiblyObject()) {
                        // e.g. `object`, `mixed`, `iterable` or a template type: an object of a class that is not known
                        return null;
                    }
                    // e.g. `null` or `int`: the call would throw.
                    continue;
                }
                $class_fqsen = FullyQualifiedClassName::fromType($type);
                if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                    return null;
                }
                $class = $code_base->getClassByFQSEN($class_fqsen);
                if (!$class->hasMethodWithName($code_base, $method_name, true)) {
                    if (!$class->isFinal() || ($is_static ? $class->allowsCallingUndeclaredStaticMethod($code_base) : $class->allowsCallingUndeclaredInstanceMethod($code_base))) {
                        // A subclass or a class implementing the interface may declare the method,
                        // and __call() or __callStatic() is another candidate.
                        return null;
                    }
                    // Calling the method on an instance of this final class would throw.
                    continue;
                }
                $method = $class->getMethodByName($code_base, $method_name);
                if (!$method->isAccessibleFromClass($code_base, $accessing_class_fqsen)) {
                    // The call throws, or calls __call() or __callStatic() instead of this method.
                    return null;
                }
                if (!$is_static && $accessing_class_fqsen !== null && self::isPrivateMethodOfScopeCalled($code_base, $accessing_class_fqsen, $class_fqsen, $method_name)) {
                    return null;
                }
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
     * @return bool whether a call of the method $method_name on an instance of $class_fqsen, in the scope of the class
     * $accessing_class_fqsen, calls a private method of that scope instead of the method of $class_fqsen.
     * PHP does that for an instance of a subclass, also when the subclass declares a method with the same name.
     */
    private static function isPrivateMethodOfScopeCalled(CodeBase $code_base, FullyQualifiedClassName $accessing_class_fqsen, FullyQualifiedClassName $class_fqsen, string $method_name): bool
    {
        if ($accessing_class_fqsen === $class_fqsen || !$code_base->hasClassWithFQSEN($accessing_class_fqsen)) {
            return false;
        }
        $accessing_class = $code_base->getClassByFQSEN($accessing_class_fqsen);
        if (!$accessing_class->hasMethodWithName($code_base, $method_name, true)) {
            return false;
        }
        $method = $accessing_class->getMethodByName($code_base, $method_name);
        return $method->isPrivate()
            && $method->getDefiningClassFQSEN() === $accessing_class_fqsen
            && $class_fqsen->asType()->isSubtypeOf($accessing_class_fqsen->asType(), $code_base);
    }

    /**
     * @return bool whether $node (a method call or a static call) can evaluate to null without the method being called:
     * `$x?->method()`, or a call that follows a nullsafe access in a chain, such as `$x?->prop->method()`
     * or `$x?->factory()::make()->method()`
     */
    private static function isInNullsafeChain(Node $node): bool
    {
        while (true) {
            switch ($node->kind) {
                case ast\AST_NULLSAFE_METHOD_CALL:
                case ast\AST_NULLSAFE_PROP:
                    return true;
                case ast\AST_METHOD_CALL:
                case ast\AST_PROP:
                case ast\AST_DIM:
                    $node = $node->children['expr'];
                    break;
                case ast\AST_STATIC_CALL:
                case ast\AST_STATIC_PROP:
                    // e.g. `$x?->factory()::make()->method()`
                    $node = $node->children['class'];
                    break;
                default:
                    return false;
            }
            if (!$node instanceof Node) {
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
