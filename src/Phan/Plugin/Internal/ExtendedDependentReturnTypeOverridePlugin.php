<?php declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Closure;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Type;
use Phan\Language\Type\IntType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use Phan\PluginV2;
use Phan\PluginV2\ReturnTypeOverrideCapability;
use Throwable;

use function count;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * This internal plugin will aggressively infer return types
 * for certain methods if all arguments are known literal values (e.g. str_replace, implode)
 */
final class ExtendedDependentReturnTypeOverridePlugin extends PluginV2 implements
    ReturnTypeOverrideCapability
{
    /**
     * @param CodeBase $code_base @phan-unused-param
     * @return array<string,\Closure>
     * @phan-return array<string, Closure(CodeBase,Context,Func,array):UnionType>
     */
    private static function getReturnTypeOverridesStatic(CodeBase $code_base) : array
    {
        $string_union_type = StringType::instance(false)->asUnionType();
        $mixed_union_type = MixedType::instance(false)->asUnionType();
        /**
         * @param callable-string $function
         * @return Closure(CodeBase,Context,Func,array):UnionType
         */
        $wrap_n_argument_function = static function (
            callable $function,
            int $min_args,
            int $max_args = null,
            UnionType $default_type = null
        ) use ($string_union_type) : Closure {
            $default_type = $default_type ?? $string_union_type;
            $max_args = $max_args ?? $min_args;
            /**
             * @param array<int,Node|string|int|float> $args
             */
            return static function (
                CodeBase $code_base,
                Context $context,
                Func $unused_function,
                array $args
            ) use (
                $default_type,
                $function,
                $min_args,
                $max_args
            ) : UnionType {
                if (count($args) < $min_args || count($args) > $max_args) {
                    return $default_type;
                }
                $values = [];
                foreach ($args as $arg) {
                    $value = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $arg)->asValueOrNullOrSelf();
                    if (\is_object($value)) {
                        return $default_type;
                    }
                    $values[] = $value;
                }
                try {
                    $result = \with_disabled_phan_error_handler(/** @return mixed */ static function () use ($function, $values) {
                        return @$function(...$values);
                    });
                } catch (Throwable $e) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::TypeErrorInInternalCall,
                        $args[0]->lineno ?? $context->getLineNumberStart(),
                        $function,
                        $e->getMessage()
                    );
                    return $default_type;
                }
                return Type::fromObjectExtended($result)->asUnionType();
            };
        };
        $basic_return_type_overrides = (new DependentReturnTypeOverridePlugin())->getReturnTypeOverrides($code_base);
        /**
         * @param callable-string $function
         */
        $wrap_n_argument_function_with_fallback = static function (callable $function, int $min, int $max = null) use ($basic_return_type_overrides, $wrap_n_argument_function, $mixed_union_type) : Closure {
            $cb = $wrap_n_argument_function($function, $min, $max, $mixed_union_type);
            $cb_fallback = $basic_return_type_overrides[$function];
            /**
             * @param array<int,Node|string|int|float> $args
             */
            return static function (
                CodeBase $code_base,
                Context $context,
                Func $function_decl,
                array $args
            ) use (
                $cb,
                $cb_fallback,
                $mixed_union_type
) : UnionType {
                $result = $cb($code_base, $context, $function_decl, $args);
                if ($result !== $mixed_union_type) {
                    return $result;
                }
                return $cb_fallback($code_base, $context, $function_decl, $args);
            };
        };
        $int_union_type = IntType::instance(false)->asUnionType();

        return [
            // commonly used functions where the return type depends only on the passed in arguments
            // TODO: Add remaining functions
            'abs'          => $wrap_n_argument_function('abs', 1, 1, $int_union_type),
            'addcslashes'  => $wrap_n_argument_function('addcslashes', 2),
            'addslashes'   => $wrap_n_argument_function('addslashes', 1),
            'explode'      => $wrap_n_argument_function('explode', 2, 3, UnionType::fromFullyQualifiedString('array<int,string>')),
            'implode'      => $wrap_n_argument_function('implode', 1, 2),
            // TODO: Improve this to warn about invalid json with json_error_last()
            'json_decode'  => $wrap_n_argument_function_with_fallback('json_decode', 1, 4),
            'json_encode'  => $wrap_n_argument_function('json_encode', 1, 3, UnionType::fromFullyQualifiedString('string|false')),
            'substr'       => $wrap_n_argument_function('substr', 1, 3),
            'strlen'       => $wrap_n_argument_function('strlen', 1, 3),
            'join'         => $wrap_n_argument_function('join', 1),
            'ltrim'        => $wrap_n_argument_function('ltrim', 1, 2),
            'rtrim'        => $wrap_n_argument_function('rtrim', 1, 2),
            'str_ireplace' => $wrap_n_argument_function('str_ireplace', 3, 4),
            'str_replace'  => $wrap_n_argument_function('str_replace', 3, 4),
            'strpos'       => $wrap_n_argument_function('strpos', 1, 3),
            'strrpos'      => $wrap_n_argument_function('strrpos', 1, 3),
            'strripos'     => $wrap_n_argument_function('strripos', 1, 3),
            'stripos'      => $wrap_n_argument_function('stripos', 1, 3),
            'strrev'       => $wrap_n_argument_function('strrev', 1),
            'strtolower'   => $wrap_n_argument_function('strtolower', 1),
            'strtoupper'   => $wrap_n_argument_function('strtoupper', 1),
            'trim'         => $wrap_n_argument_function('trim', 1, 2),
            'chr'          => $wrap_n_argument_function('chr', 1, 1),
            'ord'          => $wrap_n_argument_function('ord', 1, 1, $int_union_type),
        ];
    }

    /**
     * @return array<string,\Closure>
     * @override
     */
    public function getReturnTypeOverrides(CodeBase $code_base) : array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $overrides = null;
        if ($overrides === null) {
            $overrides = self::getReturnTypeOverridesStatic($code_base);
        }
        return $overrides;
    }
}
