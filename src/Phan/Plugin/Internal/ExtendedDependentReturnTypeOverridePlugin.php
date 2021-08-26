<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Closure;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\ReturnTypeOverrideCapability;
use Throwable;

use function count;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * This internal plugin will aggressively infer return types
 * for certain methods if all arguments are known literal values (e.g. str_replace, implode)
 */
final class ExtendedDependentReturnTypeOverridePlugin extends PluginV3 implements
    ReturnTypeOverrideCapability
{
    /**
     * Given the name of a pure function with no side effects,
     * this returns a callback that will call the function if all args are known,
     * and return the value as a union type.
     * (or return the default union type)
     *
     * @param callable-string $function_name
     * @return Closure(CodeBase,Context,Func,array):UnionType
     */
    public static function wrapNArgumentFunction(
        string $function_name,
        int $min_args,
        ?int $max_args = null
    ): Closure {
        $max_args = $max_args ?? $min_args;
        /**
         * @param list<Node|string|int|float> $args
         */
        return static function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) use (
            $function_name,
            $min_args,
            $max_args
        ): UnionType {
            if (count($args) < $min_args || count($args) > $max_args) {
                // Phan should already warn about too many or too few
                return $function->getUnionType();
            }
            $values = [];
            foreach ($args as $arg) {
                $value = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $arg)->asValueOrNullOrSelf();
                if (\is_object($value)) {
                    return $function->getUnionType();
                }
                $values[] = $value;
            }
            try {
                $result = \with_disabled_phan_error_handler(/** @return mixed */ static function () use ($function_name, $values) {
                    return @$function_name(...$values);
                });
            } catch (Throwable $e) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::TypeErrorInInternalCall,
                    $args[0]->lineno ?? $context->getLineNumberStart(),
                    $function_name,
                    $e->getMessage()
                );
                return $function->getUnionType();
            }
            return Type::fromObjectExtended($result)->asRealUnionType();
        };
    }

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @return array<string,\Closure>
     * @phan-return array<string, Closure(CodeBase,Context,Func,array):UnionType>
     */
    private static function getReturnTypeOverridesStatic(CodeBase $code_base): array
    {
        $basic_return_type_overrides = (new DependentReturnTypeOverridePlugin())->getReturnTypeOverrides($code_base);
        /**
         * @param callable-string $function
         */
        $wrap = static function (string $function, int $min, ?int $max = null) use ($basic_return_type_overrides): ?Closure {
            if (!\is_callable($function)) {
                return null;
            }
            $cb = self::wrapNArgumentFunction($function, $min, $max);
            $cb_fallback = $basic_return_type_overrides[$function] ?? null;
            if (!$cb_fallback) {
                return $cb;
            }
            /**
             * @param list<Node|string|int|float> $args
             */
            return static function (
                CodeBase $code_base,
                Context $context,
                Func $function_decl,
                array $args
            ) use (
                $cb,
                $cb_fallback
            ): UnionType {
                $result = $cb($code_base, $context, $function_decl, $args);
                if ($result !== $function_decl->getUnionType()) {
                    return $result;
                }
                return $cb_fallback($code_base, $context, $function_decl, $args);
            };
        };

        return \array_filter([
            // commonly used functions where the return type depends only on the passed in arguments
            // TODO: Add remaining functions
            'abs'          => $wrap('abs', 1, 1),
            'addcslashes'  => $wrap('addcslashes', 2),
            'addslashes'   => $wrap('addslashes', 1),
            'explode'      => $wrap('explode', 2, 3),
            'implode'      => $wrap('implode', 1, 2),
            // TODO: Improve this to warn about invalid json with json_error_last()
            'json_decode'  => $wrap('json_decode', 1, 4),
            'json_encode'  => $wrap('json_encode', 1, 3),
            'substr'       => $wrap('substr', 1, 3),
            'strlen'       => $wrap('strlen', 1, 3),
            'join'         => $wrap('join', 1),
            'ltrim'        => $wrap('ltrim', 1, 2),
            'preg_quote'   => $wrap('preg_quote', 1, 2),
            'rtrim'        => $wrap('rtrim', 1, 2),
            'str_ireplace' => $wrap('str_ireplace', 3, 4),
            'str_replace'  => $wrap('str_replace', 3, 4),
            'strpos'       => $wrap('strpos', 1, 3),
            'strrpos'      => $wrap('strrpos', 1, 3),
            'strripos'     => $wrap('strripos', 1, 3),
            'stripos'      => $wrap('stripos', 1, 3),
            'strrev'       => $wrap('strrev', 1),
            'strtolower'   => $wrap('strtolower', 1),
            'strtoupper'   => $wrap('strtoupper', 1),
            'trim'         => $wrap('trim', 1, 2),
            'chr'          => $wrap('chr', 1, 1),
            'ord'          => $wrap('ord', 1, 1),
        ]);
    }

    /**
     * @return array<string,\Closure>
     * @override
     */
    public function getReturnTypeOverrides(CodeBase $code_base): array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $overrides = null;
        if ($overrides === null) {
            $overrides = self::getReturnTypeOverridesStatic($code_base);
        }
        return $overrides;
    }
}
