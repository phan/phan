<?php declare(strict_types=1);
namespace Phan\Plugin\Internal;

use Phan\CodeBase;
use Phan\Config;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TrueType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\PluginV2;
use Phan\PluginV2\ReturnTypeOverrideCapability;
use Closure;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * This internal plugin will warn if calls to internal string functions
 * or regex function appear to have arguments in the wrong order,
 *
 * e.g. explode($var, ':') or strpos(':', $x)
 */
final class DependentReturnTypeOverridePlugin extends PluginV2 implements
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
        $true_union_type = TrueType::instance(false)->asUnionType();
        $string_or_true_union_type = $string_union_type->withUnionType($true_union_type);
        $void_union_type = VoidType::instance(false)->asUnionType();
        $nullable_string_union_type = StringType::instance(true)->asUnionType();

        /**
         * @phan-return Closure(CodeBase,Context,Func,array):UnionType
         */
        $make_dependent_type_method = static function (int $expected_bool_pos, UnionType $type_if_true, UnionType $type_if_false, UnionType $type_if_unknown) : Closure {
            /**
             * @param Func $function @phan-unused-param
             * @return UnionType
             */
            return static function (
                CodeBase $code_base,
                Context $context,
                Func $function,
                array $args
            ) use (
                $type_if_true,
                $type_if_unknown,
                $type_if_false,
                $expected_bool_pos
) : UnionType {
                if (\count($args) <= $expected_bool_pos) {
                    return $type_if_false;
                }
                $result = (new ContextNode($code_base, $context, $args[$expected_bool_pos]))->getEquivalentPHPScalarValue();
                if (\is_int($result)) {
                    $result = (bool)$result;
                }
                if ($result === true) {
                    return $type_if_true;
                } elseif ($result === false) {
                    return $type_if_false;
                } else {
                    // unable to determine.
                    return $type_if_unknown;
                }
            };
        };

        $json_decode_array_types = UnionType::fromFullyQualifiedString('array|string|float|int|bool|null');
        $json_decode_object_types = UnionType::fromFullyQualifiedString('\stdClass|array<int,mixed>|string|float|int|bool|null');
        $json_decode_array_or_object_types = UnionType::fromFullyQualifiedString('\stdClass|array|string|float|int|bool|null');

        $string_if_2_true           = $make_dependent_type_method(1, $string_union_type, $void_union_type, $nullable_string_union_type);
        $string_if_2_true_else_true = $make_dependent_type_method(1, $string_union_type, $true_union_type, $string_or_true_union_type);

        /**
         * @return UnionType
         * @param Func $function @phan-unused-param
         */
        $json_decode_return_type_handler = static function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) use (
            $json_decode_array_types,
            $json_decode_object_types,
            $json_decode_array_or_object_types
) : UnionType {
            //  mixed json_decode ( string $json [, bool $assoc = FALSE [, int $depth = 512 [, int $options = 0 ]]] )
            //  $options can include JSON_OBJECT_AS_ARRAY in a bitmask
            // TODO: reject `...` operator? (Low priority)
            if (\count($args) < 2) {
                return $json_decode_object_types;
            }
            $result = (new ContextNode($code_base, $context, $args[1]))->getEquivalentPHPScalarValue();
            if (\is_int($result)) {
                // We are already warning about the param type. E.g. var_export($arg, 1) returns a string
                $result = (bool)$result;
            }
            if ($result === true) {
                return $json_decode_array_types;
            }
            if ($result !== false) {
                return $json_decode_array_or_object_types;
            }
            if (\count($args) < 4) {
                return $json_decode_object_types;
            }
            $options_result = (new ContextNode($code_base, $context, $args[3]))->getEquivalentPHPScalarValue();
            if (!\is_int($options_result)) {
                // unable to resolve value. TODO: Support bitmask operators in getEquivalentPHPScalarValue
                return $json_decode_array_or_object_types;
            }
            return ($options_result & JSON_OBJECT_AS_ARRAY) !== 0 ? $json_decode_array_types : $json_decode_object_types;
        };

        $str_replace_types = UnionType::fromFullyQualifiedString('string|string[]');
        $str_array_type = UnionType::fromFullyQualifiedString('string[]');

        $third_argument_string_or_array_handler = static function (
            CodeBase $code_base,
            Context $context,
            Func $unused_function,
            array $args
        ) use (
            $string_union_type,
            $str_replace_types,
            $str_array_type
        ) : UnionType {
            //  mixed json_decode ( string $json [, bool $assoc = FALSE [, int $depth = 512 [, int $options = 0 ]]] )
            //  $options can include JSON_OBJECT_AS_ARRAY in a bitmask
            // TODO: reject `...` operator? (Low priority)
            if (\count($args) < 3) {
                return $str_replace_types;
            }
            $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[2]);
            $has_array = $union_type->hasArray();
            if ($union_type->canCastToUnionType($string_union_type)) {
                return $has_array ? $str_replace_types : $string_union_type;
            }
            return $has_array ? $str_array_type : $str_replace_types;
        };
        $getenv_handler = static function (
            CodeBase $unused_code_base,
            Context $unused_context,
            Func $unused_function,
            array $args
        ) : UnionType {
            if (\count($args) === 0 && Config::get_closest_target_php_version_id() >= 70100) {
                return UnionType::fromFullyQualifiedString('array<string,string>');
            }
            return UnionType::fromFullyQualifiedString('string|false');
        };

        return [
            // commonly used functions where the return type depends on a passed in boolean
            'var_export'                  => $string_if_2_true,
            'print_r'                     => $string_if_2_true_else_true,
            'json_decode'                 => $json_decode_return_type_handler,
            // Functions with dependent return types
            'str_replace'                 => $third_argument_string_or_array_handler,
            'preg_replace'                => $third_argument_string_or_array_handler,
            'preg_replace_callback'       => $third_argument_string_or_array_handler,
            'preg_replace_callback_array' => $third_argument_string_or_array_handler,
            // misc
            'getenv'                      => $getenv_handler,
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
