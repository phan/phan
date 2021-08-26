<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Closure;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\CallableArrayType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\LiteralIntType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TrueType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\ReturnTypeOverrideCapability;

use function count;
use function is_int;
use function is_string;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * This internal plugin will infer return types based on argument types and counts.
 *
 * TODO: Make these have corresponding real type sets in the union type.
 */
final class DependentReturnTypeOverridePlugin extends PluginV3 implements
    ReturnTypeOverrideCapability
{
    /**
     * A static method to compute the return type override methods
     * @param CodeBase $code_base @phan-unused-param
     * @return array<string,\Closure>
     * @phan-return array<string, Closure(CodeBase,Context,Func,array):UnionType>
     * @internal
     */
    public static function getReturnTypeOverridesStatic(CodeBase $code_base): array
    {
        $string_union_type = StringType::instance(false)->asPHPDocUnionType();
        $string_union_type_real = StringType::instance(false)->asRealUnionType();
        $string_union_type_with_false_in_real = UnionType::fromFullyQualifiedPHPDocAndRealString('string', 'string|false');
        $string_union_type_with_null_in_real = UnionType::fromFullyQualifiedPHPDocAndRealString('string', '?string');
        $true_union_type = TrueType::instance(false)->asPHPDocUnionType();
        $string_or_true_union_type = $string_union_type->withUnionType($true_union_type);
        $void_union_type = VoidType::instance(false)->asPHPDocUnionType();
        $nullable_string_union_type = StringType::instance(true)->asPHPDocUnionType();
        $float_union_type = FloatType::instance(false)->asPHPDocUnionType();
        $string_or_float_union_type = $string_union_type->withUnionType($float_union_type);

        /**
         * @phan-return Closure(CodeBase,Context,Func,array):UnionType
         */
        $make_dependent_type_method = static function (int $expected_bool_pos, UnionType $type_if_true, UnionType $type_if_false, UnionType $type_if_unknown): Closure {
            /**
             * @param Func $function @phan-unused-param
             * @param list<Node|int|float|string> $args
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
            ): UnionType {
                if (count($args) <= $expected_bool_pos) {
                    return $type_if_false;
                }
                $result = (new ContextNode($code_base, $context, $args[$expected_bool_pos]))->getEquivalentPHPScalarValue();
                if (is_int($result)) {
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

        /**
         * @param Func $function @phan-unused-param
         * @param list<Node|int|float|string> $args
         */
        $bcdiv_callback = static function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) use (
            $nullable_string_union_type,
            $string_union_type
        ): UnionType {
            //PHP 8 will throw a DivisionByZero error instead of returning null
            if (Config::get_closest_target_php_version_id() >= 80000) {
                return $string_union_type;
            }
            if (count($args) <= 1) {
                return $nullable_string_union_type;
            }
            $result = (new ContextNode($code_base, $context, $args[1]))->getEquivalentPHPScalarValue();
            // @phan-suppress-next-line PhanSuspiciousTruthyString
            if (\is_numeric($result) && $result) {
                return $string_union_type;
            }
            return $nullable_string_union_type;
        };
        /**
         * @phan-return Closure(CodeBase,Context,Func,array):UnionType
         */
        $make_arg_existence_dependent_type_method = static function (int $arg_pos, string $type_if_exists_string, string $type_if_missing_string): Closure {
            $type_if_exists = UnionType::fromFullyQualifiedPHPDocString($type_if_exists_string);
            $type_if_missing = UnionType::fromFullyQualifiedPHPDocString($type_if_missing_string);
            /**
             * @param list<Node|int|float|string> $args
             */
            return static function (
                CodeBase $unused_code_base,
                Context $unused_context,
                Func $unused_function,
                array $args
            ) use (
                $arg_pos,
                $type_if_exists,
                $type_if_missing
            ): UnionType {
                return isset($args[$arg_pos]) ? $type_if_exists : $type_if_missing;
            };
        };

        $json_decode_array_types = UnionType::fromFullyQualifiedPHPDocString('array|string|float|int|bool|null');
        $json_decode_object_types = UnionType::fromFullyQualifiedPHPDocString('\stdClass|list<mixed>|string|float|int|bool|null');
        $json_decode_array_or_object_types = UnionType::fromFullyQualifiedPHPDocString('\stdClass|array|string|float|int|bool|null');

        $string_if_2_true           = $make_dependent_type_method(1, $string_union_type, $void_union_type, $nullable_string_union_type);
        $string_if_2_true_else_true = $make_dependent_type_method(1, $string_union_type, $true_union_type, $string_or_true_union_type);

        /**
         * @param Func $function @phan-unused-param
         * @param list<Node|int|float|string> $args
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
        ): UnionType {
            //  mixed json_decode ( string $json [, bool $assoc = FALSE [, int $depth = 512 [, int $options = 0 ]]] )
            //  $options can include JSON_OBJECT_AS_ARRAY in a bitmask
            // TODO: reject `...` operator? (Low priority)
            if (count($args) < 2) {
                return $json_decode_object_types;
            }
            $result = (new ContextNode($code_base, $context, $args[1]))->getEquivalentPHPScalarValue();
            if (is_int($result)) {
                // We are already warning about the param type. E.g. var_export($arg, 1) returns a string
                $result = (bool)$result;
            }
            if ($result === true) {
                return $json_decode_array_types;
            }
            if ($result !== false) {
                return $json_decode_array_or_object_types;
            }
            if (count($args) < 4) {
                return $json_decode_object_types;
            }
            $options_result = (new ContextNode($code_base, $context, $args[3]))->getEquivalentPHPScalarValue();
            if (!is_int($options_result)) {
                // unable to resolve value. TODO: Support bitmask operators in getEquivalentPHPScalarValue
                return $json_decode_array_or_object_types;
            }
            return ($options_result & \JSON_OBJECT_AS_ARRAY) !== 0 ? $json_decode_array_types : $json_decode_object_types;
        };

        $str_replace_types = UnionType::fromFullyQualifiedPHPDocString('string|string[]');
        $str_array_type = UnionType::fromFullyQualifiedPHPDocString('string[]');

        /**
         * @param list<Node|int|float|string> $args
         */
        $third_argument_string_or_array_handler = static function (
            CodeBase $code_base,
            Context $context,
            Func $unused_function,
            array $args
        ) use (
            $string_union_type,
            $str_replace_types,
            $str_array_type
        ): UnionType {
            //  mixed json_decode ( string $json [, bool $assoc = FALSE [, int $depth = 512 [, int $options = 0 ]]] )
            //  $options can include JSON_OBJECT_AS_ARRAY in a bitmask
            // TODO: reject `...` operator? (Low priority)
            if (count($args) < 3) {
                return $str_replace_types;
            }
            $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[2]);
            $has_array = $union_type->hasArray();
            if ($union_type->canCastToUnionType($string_union_type, $code_base)) {
                return $has_array ? $str_replace_types : $string_union_type;
            }
            return $has_array ? $str_array_type : $str_replace_types;
        };
        $string_or_false = UnionType::fromFullyQualifiedRealString('string|false');
        /**
         * @param list<Node|int|float|string> $args
         */
        $getenv_handler = static function (
            CodeBase $unused_code_base,
            Context $unused_context,
            Func $unused_function,
            array $args
        ) use ($string_or_false): UnionType {
            if (count($args) === 0 && Config::get_closest_target_php_version_id() >= 70100) {
                return UnionType::fromFullyQualifiedPHPDocString('array<string,string>');
            }
            return $string_or_false;
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $substr_handler = static function (
            CodeBase $unused_code_base,
            Context $unused_context,
            Func $unused_function,
            array $args
        ) use (
            $string_or_false,
            $string_union_type_with_false_in_real,
            $string_union_type_real
        ): UnionType {
            if (Config::get_closest_target_php_version_id() >= 80000) {
                if (Config::get_closest_minimum_target_php_version_id() >= 80000) {
                    // Avoid false positive PhanRedundantCondition in projects that need to support php versions before 8.0
                    return $string_union_type_real;
                }
                // Avoid false positives with strict type checking and assume phpdoc type of string.
                return $string_union_type_with_false_in_real;
            }
            if (count($args) >= 2 && is_int($args[1]) && $args[1] <= 0) {
                // Cut down on false positive warnings about substr($str, 0, $len) possibly being false
                return $string_union_type_with_false_in_real;
            }
            return $string_or_false;
        };
        $real_int_type = IntType::instance(false)->asRealUnionType();
        /**
         * @param list<Node|int|float|string> $args
         */
        $count_handler = static function (
            CodeBase $code_base,
            Context $context,
            Func $unused_function,
            array $args
        ) use ($real_int_type): UnionType {
            if (count($args) < 1 || count($args) >= 3) {
                return NullType::instance(false)->asRealUnionType();
            }
            if (count($args) !== 1) {
                return $real_int_type;
            }
            $arg_union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $arg_types = $arg_union_type->getRealTypeSet();
            if (count($arg_types) !== 1) {
                return $real_int_type;
            }
            $arg = \reset($arg_types);
            if ($arg instanceof ArrayShapeType) {
                foreach ($arg->getFieldTypes() as $field_type) {
                    if ($field_type->isPossiblyUndefined()) {
                        return $real_int_type;
                    }
                }
                return LiteralIntType::instanceForValue(count($arg->getFieldTypes()), false)->asRealUnionType();
            } elseif ($arg instanceof CallableArrayType) {
                return LiteralIntType::instanceForValue(2, false)->asRealUnionType();
            }

            return $real_int_type;
        };

        $parse_url_handler = $make_arg_existence_dependent_type_method(
            1,
            'string|int|null|false',
            'array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string}|false'
        );

        /**
         * @param list<Node|int|float|string> $args
         */
        $dirname_handler = static function (
            CodeBase $code_base,
            Context $context,
            Func $unused_function,
            array $args
        ) use (
            $string_union_type_with_null_in_real
        ): UnionType {
            if (count($args) !== 1) {
                if (count($args) !== 2) {
                    // Cut down on false positive warnings about substr($str, 0, $len) possibly being false
                    return $string_union_type_with_null_in_real;
                }
                $levels = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1])->asSingleScalarValueOrNull();
                if (!is_int($levels)) {
                    return $string_union_type_with_null_in_real;
                }
                if ($levels <= 0) {
                    // TODO: Could warn but not common
                    return NullType::instance(false)->asPHPDocUnionType();
                }
            } else {
                $levels = 1;
            }
            $arg = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0])->asSingleScalarValueOrNull();
            if (!is_string($arg)) {
                return $string_union_type_with_null_in_real;
            }

            $result = \dirname($arg, $levels);
            return Type::fromObject($result)->asPHPDocUnionType();
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $explode_handler = static function (
            CodeBase $code_base,
            Context $context,
            Func $unused_function,
            array $args
        ): UnionType {
            $is_php8 = Config::get_closest_target_php_version_id() >= 80000;
            if (count($args) > 2) {
                $limit = $args[2];
                if ($limit instanceof Node) {
                    $limit = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $limit)->asSingleScalarValueOrNullOrSelf();
                }
                if (\is_object($limit) || $limit < 0) {
                    // PHP will only ever return an empty list if there is a negative limit for explode().
                    return UnionType::fromFullyQualifiedPHPDocAndRealString(
                        'list<string>',
                        $is_php8 ? 'list<string>' : '?list<string>'
                    );
                }
            }
            return UnionType::fromFullyQualifiedPHPDocAndRealString(
                'non-empty-list<string>',
                $is_php8 ? 'non-empty-list<string>' : '?non-empty-list<string>'
            );
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $one_or_two_string_handler = static function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ): UnionType {
            if (Config::get_closest_target_php_version_id() >= 80000) {
                return StringType::instance(false)->asRealUnionType();
            }
            if (count($args) >= 1 && count($args) <= 2) {
                if (UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0])->getRealUnionType()->isNonNullStringType()) {
                    if (!isset($args[1]) || is_string($args[1]) || UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1])->getRealUnionType()->isNonNullStringType()) {
                        return StringType::instance(false)->asRealUnionType();
                    }
                }
            }
            return $function->getUnionType();
        };

        // TODO: Handle flags of preg_split.
        return [
            // commonly used functions where the return type depends on a passed in boolean
            'var_export'                  => $string_if_2_true,
            'print_r'                     => $string_if_2_true_else_true,
            'json_decode'                 => $json_decode_return_type_handler,
            'count'                       => $count_handler,
            // Functions with dependent return types
            'str_replace'                 => $third_argument_string_or_array_handler,
            'preg_replace'                => $third_argument_string_or_array_handler,
            'preg_replace_callback'       => $third_argument_string_or_array_handler,
            'preg_replace_callback_array' => $third_argument_string_or_array_handler,
            'microtime'                   => $make_dependent_type_method(0, $float_union_type, $string_union_type, $string_or_float_union_type),
            // misc
            'getenv'                      => $getenv_handler,
            'version_compare'             => $make_arg_existence_dependent_type_method(2, 'bool', 'int'),
            'pathinfo'                    => $make_arg_existence_dependent_type_method(1, 'string', 'array{dirname:string,basename:string,extension?:string,filename:string}'),
            'parse_url'                   => $parse_url_handler,
            'substr'                      => $substr_handler,
            'dirname'                     => $dirname_handler,
            'basename'                    => self::makeStringFunctionHandler('basename'),
            'bcdiv'                       => $bcdiv_callback,
            'explode'                     => $explode_handler,
            'trim'                        => $one_or_two_string_handler,
            'ltrim'                       => $one_or_two_string_handler,
            'rtrim'                       => $one_or_two_string_handler,
        ];
    }

    /**
     * @param callable(string):string $callable a function that acts on strings.
     */
    private static function makeStringFunctionHandler(callable $callable): Closure
    {
        $string_union_type = StringType::instance(false)->asPHPDocUnionType();
        /**
         * @param list<Node|int|float|string> $args
         */
        return static function (
            CodeBase $code_base,
            Context $context,
            Func $unused_function,
            array $args
        ) use (
            $string_union_type,
            $callable
        ): UnionType {
            if (count($args) !== 1) {
                // Cut down on false positive warnings about substr($str, 0, $len) possibly being false
                return $string_union_type;
            }
            $arg = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0])->asSingleScalarValueOrNull();
            if (!is_string($arg)) {
                return $string_union_type;
            }

            $result = $callable($arg);
            return Type::fromObject($result)->asPHPDocUnionType();
        };
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
