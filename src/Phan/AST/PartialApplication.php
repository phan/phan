<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\Node;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Parameter;
use Phan\Language\Type\ClosureDeclarationParameter;
use Phan\Language\Type\ClosureDeclarationType;
use Phan\Language\Type\ClosureType;
use Phan\Language\Type\FunctionLikeDeclarationType;
use Phan\Language\Type\MixedType;
use Phan\Language\UnionType;

/**
 * Utilities for the arguments of a node of kind ast\AST_CALLABLE_CONVERT:
 * first-class callable syntax (`f(...)`) and php 8.6 partial function application (`f(1, ?)`, `f(1, ...)`, `f(a: ?, b: 2)`).
 *
 * In AST version 130, the node has a child 'args' of kind ast\AST_ARG_LIST. Its elements are argument expressions,
 * ast\AST_NAMED_ARG nodes (the expression of which may be a placeholder), and placeholders: nodes of kind ast\AST_PLACEHOLDER_ARG,
 * with the flag ast\flags\PLACEHOLDER_VARIADIC for `...`. `f(...)` has a single variadic placeholder.
 *
 * In older AST versions, the node has no children: partial function application cannot be told apart from `f(...)`
 * and is treated like it.
 *
 * @see https://wiki.php.net/rfc/partial_function_application_v2
 */
final class PartialApplication
{
    /**
     * @param Node $node a node of kind ast\AST_CALLABLE_CONVERT
     * @return list<Node|string|int|float> the arguments, including the placeholders
     */
    public static function getArgs(Node $node): array
    {
        $args_node = $node->children['args'] ?? null;
        if (!($args_node instanceof Node)) {
            return [];
        }
        // @phan-suppress-next-line PhanPartialTypeMismatchReturn the elements of an argument list are never null
        return \array_values($args_node->children);
    }

    /**
     * @param Node|string|int|float|null $arg an argument
     * @return bool whether this is `?` or `...`
     */
    public static function isPlaceholder(Node|float|int|string|null $arg): bool
    {
        return $arg instanceof Node && $arg->kind === ast\AST_PLACEHOLDER_ARG;
    }

    /**
     * @param Node|string|int|float|null $arg an argument
     * @return bool whether this is the placeholder `...`
     */
    public static function isVariadicPlaceholder(Node|float|int|string|null $arg): bool
    {
        return $arg instanceof Node && $arg->kind === ast\AST_PLACEHOLDER_ARG && ($arg->flags & ast\flags\PLACEHOLDER_VARIADIC) !== 0;
    }

    /**
     * @param Node $node a node of kind ast\AST_CALLABLE_CONVERT
     * @return bool whether this is partial function application: there is an argument other than a single `...`
     */
    public static function isPartialApplication(Node $node): bool
    {
        $args = self::getArgs($node);
        return \count($args) > 1 || (\count($args) === 1 && !self::isVariadicPlaceholder($args[0]));
    }

    /**
     * @param Node $node a node of kind ast\AST_CALLABLE_CONVERT
     * @return list<Node|string|int|float> the arguments that are bound to values when the closure is created:
     * the arguments without the placeholders. A named argument is returned as its ast\AST_NAMED_ARG node.
     */
    public static function getBoundArgs(Node $node): array
    {
        $result = [];
        foreach (self::getArgs($node) as $arg) {
            if (self::isPlaceholder($arg)) {
                continue;
            }
            if ($arg instanceof Node && $arg->kind === ast\AST_NAMED_ARG && self::isPlaceholder($arg->children['expr'])) {
                continue;
            }
            $result[] = $arg;
        }
        return $result;
    }

    /**
     * @param Node $node a node of kind ast\AST_CALLABLE_CONVERT
     * @return ?int the index of the argument that is the only placeholder, when it is a positional `?` or `...`
     * and every other argument is a value: the forms `f(?)`, `f(1, ?)`, `f(1, ...)`, `f(?, b: 2)`,
     * where an argument in the place of the placeholder is passed to the parameter that the closure has.
     * Null for anything else, including `...` after a named argument.
     */
    public static function getIndexOfSinglePositionalPlaceholder(Node $node): ?int
    {
        $index = null;
        $has_named_arg = false;
        foreach (self::getArgs($node) as $i => $arg) {
            if (self::isPlaceholder($arg)) {
                if ($index !== null) {
                    return null;
                }
                if ($has_named_arg) {
                    // `...` after a named argument, e.g. `f(b: 1, ...)`: the remaining parameters are not at this position.
                    return null;
                }
                $index = $i;
            } elseif ($arg instanceof Node && $arg->kind === ast\AST_NAMED_ARG) {
                if (self::isPlaceholder($arg->children['expr'])) {
                    return null;
                }
                $has_named_arg = true;
            }
        }
        return $index;
    }

    /**
     * Returns the type of the closure created by partial application of $function.
     *
     * The parameters of the closure are, in this order:
     * the parameters of the positional placeholders `?`, the parameters of the named placeholders `name: ?` in the order they are written,
     * and, if there is a `...`, the remaining parameters that no argument is bound to.
     *
     * @param Node $node a node of kind ast\AST_CALLABLE_CONVERT for which self::isPartialApplication() is true
     * @return FunctionLikeDeclarationType|ClosureType a closure type with the parameters that are left,
     * or `\Closure` if the arguments cannot be matched to the parameters (e.g. `f(...$args, ?)`).
     */
    public static function createClosureType(FunctionInterface $function, Node $node): ClosureType|FunctionLikeDeclarationType
    {
        $parameter_list = $function->getParameterList();
        /** @var array<string,int> $index_of_name */
        $index_of_name = [];
        foreach ($parameter_list as $i => $parameter) {
            $index_of_name[$parameter->getName()] = $i;
        }
        $parameter_count = \count($parameter_list);
        $last_parameter = $parameter_count > 0 ? $parameter_list[$parameter_count - 1] : null;
        $variadic_parameter = $last_parameter && $last_parameter->isVariadic() ? $last_parameter : null;

        /** @var array<int,true> $handled the indexes of the parameters with a value or a placeholder */
        $handled = [];
        /** @var list<ClosureDeclarationParameter> $closure_params */
        $closure_params = [];
        $has_variadic_placeholder = false;
        $position = 0;
        /** @var list<int> $variadic_element_indexes the indexes in $closure_params of the placeholders for elements of the variadic parameter */
        $variadic_element_indexes = [];
        /** @var array<string,true> $used_names the names of the other parameters of the closure */
        $used_names = [];
        foreach (self::getArgs($node) as $arg) {
            if ($arg instanceof Node) {
                if ($arg->kind === ast\AST_UNPACK) {
                    // The number of arguments is not known.
                    return ClosureType::instance(false);
                }
                if ($arg->kind === ast\AST_NAMED_ARG) {
                    $name = $arg->children['name'];
                    if (!\is_string($name) || !isset($index_of_name[$name])) {
                        // An unknown named argument is reported when the bound arguments are analyzed.
                        // (It may also be collected by a variadic parameter)
                        continue;
                    }
                    $index = $index_of_name[$name];
                    if (self::isPlaceholder($arg->children['expr']) && !isset($handled[$index])) {
                        $closure_params[] = self::asClosureParameter($parameter_list[$index], true);
                        $used_names[$name] = true;
                    }
                    $handled[$index] = true;
                    continue;
                }
                if (self::isVariadicPlaceholder($arg)) {
                    $has_variadic_placeholder = true;
                    continue;
                }
            }
            // A positional argument: a value or `?`
            $index = $position++;
            $is_placeholder = self::isPlaceholder($arg);
            if ($index < $parameter_count && !$parameter_list[$index]->isVariadic()) {
                if ($is_placeholder) {
                    $closure_params[] = self::asClosureParameter($parameter_list[$index], true);
                    $used_names[$parameter_list[$index]->getName()] = true;
                }
                $handled[$index] = true;
            } elseif ($is_placeholder && $variadic_parameter) {
                // `?` for an element of the variadic parameter is a required parameter of the closure. It is named below.
                $variadic_element_indexes[] = \count($closure_params);
                $closure_params[] = self::asRequiredElementOfVariadic($variadic_parameter, $variadic_parameter->getName());
            }
            // else there are too many arguments, which is reported when the bound arguments are analyzed.
        }
        if ($has_variadic_placeholder) {
            foreach ($parameter_list as $index => $parameter) {
                if (!isset($handled[$index])) {
                    $closure_params[] = self::asClosureParameter($parameter, false);
                    $used_names[$parameter->getName()] = true;
                }
            }
        }
        if ($variadic_element_indexes && $variadic_parameter) {
            // The elements of the variadic parameter are named e.g. $nums0, $nums1, skipping the names that other parameters of the closure have:
            // for `function f(int $nums0, int ...$nums)`, the parameters of `f(?, ?)` are $nums0 and $nums1.
            $n = 0;
            foreach ($variadic_element_indexes as $i) {
                do {
                    $name = $variadic_parameter->getName() . $n++;
                } while (isset($used_names[$name]));
                $used_names[$name] = true;
                $closure_params[$i] = self::asRequiredElementOfVariadic($variadic_parameter, $name);
            }
        }

        // `static` in the return type of a method is the class of the method: the closure may be called outside of the class.
        $return_type = $function->getUnionType()->withStaticResolvedInFunctionLike($function);
        if ($return_type->isEmpty()) {
            $return_type = MixedType::instance(false)->asPHPDocUnionType();
        }
        return new ClosureDeclarationType(
            $function->getFileRef(),
            $closure_params,
            $return_type,
            $function->returnsRef(),
            false
        );
    }

    /**
     * The parameter of the closure for a parameter of the function: it keeps the name, the type,
     * and whether it is variadic or passed by reference.
     *
     * @param bool $is_placeholder whether the parameter is there for a `?`, which makes it required.
     * A parameter that is there for `...` is optional if the parameter of the function is.
     */
    private static function asClosureParameter(Parameter $parameter, bool $is_placeholder): ClosureDeclarationParameter
    {
        $type = $parameter->getNonVariadicUnionType();
        if ($type->isEmpty()) {
            $type = MixedType::instance(false)->asPHPDocUnionType();
        }
        return new ClosureDeclarationParameter(
            $type,
            $parameter->isVariadic(),
            $parameter->isPassByReference(),
            !$is_placeholder && $parameter->isOptional(),
            $parameter->getName()
        );
    }

    private static function asRequiredElementOfVariadic(Parameter $parameter, string $name): ClosureDeclarationParameter
    {
        $type = $parameter->getNonVariadicUnionType();
        if ($type->isEmpty()) {
            $type = MixedType::instance(false)->asPHPDocUnionType();
        }
        return new ClosureDeclarationParameter($type, false, $parameter->isPassByReference(), false, $name);
    }

    /**
     * @param Node $node a node of kind ast\AST_CALLABLE_CONVERT
     * @return UnionType the type of the closure created for $function by first-class callable syntax or partial application
     */
    public static function createClosureUnionType(FunctionInterface $function, Node $node): UnionType
    {
        if (self::isPartialApplication($node)) {
            return self::createClosureType($function, $node)->asRealUnionType();
        }
        if ($function instanceof ClosureDeclarationType) {
            return $function->asRealUnionType();
        }
        return ClosureType::instanceWithClosureFQSEN($function->getFQSEN(), $function)->asRealUnionType();
    }
}
