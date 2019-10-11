#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Phan/Bootstrap.php';

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\ResourceType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TrueType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;

/**
 * This extracts the real signature types for commonly used functions from opcache.
 *
 * Note that php 8.0 has TypeError and ArgumentCountError for internal functions,
 * so the return types are much more specific.
 */
class OpcacheFuncInfoParser
{
    const OVERRIDES = [
        'password_hash' => 'false|null|string',  // this was allowed to be false in php 7.1. PHP 8.0 makes this more consistent, and is ?string
        'config_get_hash' => null,  // skip php debug method
        // wrong handling of string for 32-bit
        'mysqli_get_charset' => null,
        'mysqli_get_client_stats' => null,
        'mysqli_insert_id' => null,
        'mysqli_stmt_affected_rows' => null,
        'mysqli_stat' => null,

        // etc.
        'pathinfo' => 'array|string',  // temporary override
        'parse_url' => 'array|false|int|string|null',  // conservative guess
        'pg_result_error_field' => 'false|null|string',
        'set_error_handler' => '?callable',  // probably? Might not work for private methods as arrays.
        'set_socket_blocking' => null,  // this is new
        // not common enough to investigate
        'stream_bucket_append' => null,
        'stream_bucket_new' => null,
        'stream_bucket_prepend' => null,
    ];

    /**
     * @return array<string,UnionType> maps internal function names to their real union types (from Reflection)
     */
    private static function extractInfoFromReflection() : array
    {
        $result = [];
        $function_names = get_defined_functions();
        unset($function_names['user']);
        foreach (array_merge(...array_values($function_names)) as $function_name) {
            $function = new ReflectionFunction($function_name);
            if (!$function->hasReturnType()) {
                continue;
            }
            $return_type = $function->getReturnType();
            $union_type = UnionType::fromReflectionType($return_type);
            $result[$function_name] = $union_type;
        }
        // This works, but methods with return types are uncommon.
        foreach (get_declared_classes() as $class_name) {
            $reflection_class = new ReflectionClass($class_name);
            if (!$reflection_class->isInternal()) {
                continue;
            }
            foreach ($reflection_class->getMethods() as $method) {
                if (!$method->hasReturnType()) {
                    continue;
                }
                $method_name = $class_name . '::' . $method->getName();
                $union_type = UnionType::fromReflectionType($method->getReturnType());
                $result[$method_name] = $union_type;
            }
        }

        return $result;
    }

    /**
     * @return array<string,UnionType> maps internal function names to their real union types (From opcache's signatures)
     */
    private static function extractInfoFromOpcache(string $contents) : array
    {
        $lines = explode("\n", $contents);
        $result = [];
        foreach ($lines as $line) {
            if (preg_match('@^\s*F[01NRXC]\(\s*"(\w+)",\s*(\w+(\s*\|\s*\w+)*)\s*\),@', $line, $matches)) {
                $function_name = $matches[1];
                if (array_key_exists($function_name, self::OVERRIDES)) {
                    $union_type_string = self::OVERRIDES[$function_name];
                    if (!$union_type_string) {
                        continue;
                    }
                    $union_type = UnionType::fromStringInContext($union_type_string, new Context(), Type::FROM_TYPE);
                } else {
                    $flags = array_map('trim', explode('|', $matches[2]));
                    $union_type = self::extractUnionType($flags);
                }
                if (!$union_type->isEmpty()) {
                    $result[$function_name] = $union_type;
                }
            }
        }
        return $result;
    }

    /**
     * @param list<string> $flags
     */
    private static function extractUnionType(array $flags) : UnionType
    {
        static $type_lookup = null;
        if ($type_lookup === null) {
            $type_lookup = [
                'MAY_BE_ARRAY' => ArrayType::instance(false),
                'MAY_BE_ARRAY_KEY_ANY' => ArrayType::instance(false),
                'MAY_BE_ARRAY_KEY_LONG' => ArrayType::instance(false),
                'MAY_BE_ARRAY_KEY_STRING' => ArrayType::instance(false),
                'MAY_BE_ARRAY_OF_ANY' => ArrayType::instance(false),
                'MAY_BE_ARRAY_OF_ARRAY' => ArrayType::instance(false),
                'MAY_BE_ARRAY_OF_DOUBLE' => ArrayType::instance(false),
                'MAY_BE_ARRAY_OF_FALSE' => ArrayType::instance(false),
                'MAY_BE_ARRAY_OF_OBJECT' => ArrayType::instance(false),
                'MAY_BE_ARRAY_OF_RESOURCE' => ArrayType::instance(false),
                'MAY_BE_ARRAY_OF_LONG' => ArrayType::instance(false),
                'MAY_BE_ARRAY_OF_NULL' => ArrayType::instance(false),
                'MAY_BE_ARRAY_OF_REF' => ArrayType::instance(false),
                'MAY_BE_ARRAY_OF_STRING' => ArrayType::instance(false),
                'MAY_BE_ARRAY_OF_TRUE' => ArrayType::instance(false),
                'MAY_BE_DOUBLE' => FloatType::instance(false),
                'MAY_BE_FALSE' => FalseType::instance(false),
                'MAY_BE_LONG' => IntType::instance(false),
                'MAY_BE_NULL' => NullType::instance(false),
                'MAY_BE_OBJECT' => ObjectType::instance(false),
                'MAY_BE_RESOURCE' => ResourceType::instance(false),
                'MAY_BE_STRING' => StringType::instance(false),
                'MAY_BE_TRUE' => TrueType::instance(false),
            ];
        }
        $result = UnionType::empty();
        if ($flags === ['MAY_BE_NULL']) {
            return VoidType::instance(false)->asPHPDocUnionType();
        }
        $flags = array_combine($flags, $flags);
        foreach ($flags as $flag) {
            if (in_array($flag, ['UNKNOWN_INFO', 'MAY_BE_ANY', 'zend_range_info'], true)) {
                return UnionType::empty();
            }
            $type = $type_lookup[$flag] ?? null;
            if ($type === null) {
                fwrite(STDERR, "Unknown flag \"$flag\"\n");
                return UnionType::empty();
            }
            $result = $result->withType($type);
        }
        if (isset($flags['MAY_BE_ARRAY'])) {
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument TODO implement https://github.com/phan/phan/issues/3242
            $array_type = self::arrayTypeFromFlags(array_flip($flags));
            $result = $result->withoutType(ArrayType::instance(false))->withUnionType($array_type);
        }
        return $result->asNormalizedTypes();
    }

    /**
     * @param array<string,string> $flag_set
     * @return UnionType of 1 or more ArrayTypes to include
     */
    private static function arrayTypeFromFlags(array $flag_set) : UnionType
    {
        // 1. Convert key types from opcache to Phan's representation
        if (isset($flag_set['MAY_BE_ARRAY_KEY_ANY'])) {
            $key_type = GenericArrayType::KEY_MIXED;
        } else {
            $key_type = GenericArrayType::KEY_EMPTY;
            if (isset($flag_set['MAY_BE_ARRAY_KEY_LONG'])) {
                $key_type |= GenericArrayType::KEY_INT;
            }
            if (isset($flag_set['MAY_BE_ARRAY_KEY_STRING'])) {
                $key_type |= GenericArrayType::KEY_STRING;
            }
            $key_type = $key_type ?: GenericArrayType::KEY_MIXED;
        }
        // 2. Convert value types from opcache to Phan's representation and normalize
        if (!isset($flag_set['MAY_BE_ARRAY_OF_ANY'])) {
            static $element_type_map = null;
            if ($element_type_map === null) {
                $element_type_map = [
                    'MAY_BE_ARRAY_OF_ARRAY' => ArrayType::instance(false),
                    'MAY_BE_ARRAY_OF_DOUBLE' => FloatType::instance(false),
                    'MAY_BE_ARRAY_OF_FALSE' => FalseType::instance(false),
                    'MAY_BE_ARRAY_OF_OBJECT' => ObjectType::instance(false),
                    'MAY_BE_ARRAY_OF_RESOURCE' => ResourceType::instance(false),
                    'MAY_BE_ARRAY_OF_LONG' => IntType::instance(false),
                    'MAY_BE_ARRAY_OF_NULL' => NullType::instance(false),
                    'MAY_BE_ARRAY_OF_STRING' => StringType::instance(false),
                    'MAY_BE_ARRAY_OF_TRUE' => TrueType::instance(false),
                ];
            }
            $possible_types = array_values(array_intersect_key($element_type_map, $flag_set));
            $element_type = UnionType::of($possible_types)->asNormalizedTypes()->asRealUnionType();
        } else {
            $element_type = UnionType::empty();
        }
        // 3. Combine key and value types, or just return a regular array if nothing is known.
        if ($element_type->isEmpty()) {
            if ($key_type === GenericArrayType::KEY_MIXED) {
                return ArrayType::instance(false)->asPHPDocUnionType();
            }
            $element_type = MixedType::instance(false)->asPHPDocUnionType();
        }
        return $element_type->asGenericArrayTypes($key_type);
    }

    /**
     * Parses the real types to expect for global functions from opcache and returns the result.
     */
    public static function main() : void
    {
        global $argv;
        if (count($argv) !== 2) {
            fwrite(STDERR, "Usage: {$argv[0]} path/to/php-src" . PHP_EOL);
            fwrite(STDERR, "  Extracts the real function return types for a php version from opcache's zend_func_info.c declarations." . PHP_EOL);
            fwrite(STDERR, "  The real return types are used by Phan to be certain if a type check is redundant or impossible." . PHP_EOL);
            exit(1);
        }

        $func_info_path = $argv[1] . "/ext/opcache/Optimizer/zend_func_info.c";
        if (!file_exists($func_info_path)) {
            fwrite(STDERR, "Could not find $func_info_path\n");
            exit(1);
        }
        $contents = file_get_contents($func_info_path);
        if (!$contents) {
            fwrite(STDERR, "Could not read contents of $func_info_path\n");
            exit(1);
        }
        $code_base = require(dirname(__DIR__) . '/src/codebase.php');
        $opcache_data = self::extractInfoFromOpcache($contents);
        $reflection_data = self::extractInfoFromReflection();
        self::checkOpcacheAndReflectionAreConsistent($code_base, $reflection_data, $opcache_data);

        // NOTE: Reflection is often updated before zend_func_info.c gets updated,
        // so union types in reflection take priority.
        $data = array_merge($opcache_data, $reflection_data);

        $inner_contents = '';

        require_once __DIR__ . '/lib/IncompatibleSignatureDetectorBase.php';
        IncompatibleSignatureDetectorBase::sortSignatureMap($data);

        foreach ($data as $function_name => $union_type) {
            $inner_contents .= "'$function_name' => '$union_type',\n";
        }
        $inner_contents = rtrim($inner_contents);
        echo <<<EOT
<?php
declare(strict_types=1);

/**
 * This lists all of the possible real return types of various global functions.
 * This is useful because php won't provide many of these until php 8,
 * and even then won't be able to represent types such as string|false.
 *
 * This is conservative to avoid false positives, and includes types returned for all possible failure modes
 * (invalid arguments/argument counts, spurious errors, etc.)
 *
 * Generated by Phan's internal/extract_arg_info.php, from ext/opcache/Optimizer/zend_func_info.c of php-src.
 */
return [
$inner_contents
];

EOT;
    }

    /**
     * @param array<string,UnionType> $reflection_data
     * @param array<string,UnionType> $opcache_data
     */
    private static function checkOpcacheAndReflectionAreConsistent(CodeBase $code_base, array $reflection_data, array $opcache_data) : void
    {
        foreach ($opcache_data as $function_name => $opcache_type) {
            $reflection_type = $reflection_data[$function_name] ?? null;
            if (!$reflection_type) {
                continue;
            }
            if (!$opcache_type->canStrictCastToUnionType($code_base, $reflection_type)) {
                fwrite(STDERR, "Error for $function_name: Opcache infers the type is $opcache_type but reflection infers that the type is $reflection_type (check if the corresponding php versions are the same)\n");
            } else {
                if ($opcache_type->isEqualTo($reflection_type)) {
                    fwrite(STDERR, "$function_name: Opcache duplicates the reflection type $opcache_type\n");
                }
                // fwrite(STDERR, "$function_name: Opcache infers the type is $opcache_type and reflection infers that the type is $reflection_type\n");
            }
        }
    }
}
OpcacheFuncInfoParser::main();
