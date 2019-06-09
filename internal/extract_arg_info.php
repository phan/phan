#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Phan/Bootstrap.php';

use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
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
        // typos
        'get_parrent_class' => null,
        'get_parent_class' => 'false|string',
        'magecolorallocate' => null,
        'mbereg' => null,
        'mbereg_match' => null,
        'mbereg_replace' => null,
        'mbereg_search' => null,
        'mbereg_search_getregs' => null,
        'mbereg_search_init' => null,
        'mbereg_search_pos' => null,
        'mbereg_search_regs' => null,
        'mbereg_search_setpos' => null,
        'mberegi' => null,
        'mberegi_replace' => null,
        'mbregex_encoding' => null,
        'mbsplit' => null,
    ];

    /**
     * @return array<string,UnionType> maps internal function names to their real union types
     */
    private static function extractInfo(string $contents) : array
    {
        $lines = explode("\n", $contents);
        $result = [];
        foreach ($lines as $line) {
            if (preg_match('@^\s*F[01NRXC]\(\s*"(\w+)",\s*(\w+(\s*\|\s*\w+)+)\s*\),@', $line, $matches)) {
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
     * @param array<int,string> $flags
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
        foreach ($flags as $flag) {
            if ($flag === 'UNKNOWN_INFO' || $flag === 'MAY_BE_ANY') {
                return UnionType::empty();
            }
            $type = $type_lookup[$flag] ?? null;
            if ($type === null) {
                fwrite(STDERR, "Unknown flag \"$flag\"\n");
                return UnionType::empty();
            }
            $result = $result->withType($type);
        }
        return $result->asNormalizedTypes();
    }

    /**
     * Parses the real types to expect for global functions from opcache and returns the result.
     */
    public static function main() : void
    {
        global $argv;
        if (count($argv) !== 2) {
            fwrite(STDERR, "Usage: {$argv[0]} path/to/php-src" . PHP_EOL);
            fwrite(STDERR, "  Extracts the real function return types for a php version from opcache's zend_func_info.c declarations.");
            fwrite(STDERR, "  The real return types are used by Phan to be certain if a type check is redundant or impossible.");
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
        $data = self::extractInfo($contents);
        $inner_contents = '';
        ksort($data);
        foreach ($data as $function_name => $union_type) {
            $inner_contents .= "'$function_name' => '$union_type',\n";
        }
        $inner_contents = rtrim($inner_contents);
        echo <<<EOT
<?php

/**
 * Generated by Phan's internal/extract_arg_info.phpt, from ext/opcache/Optimizer/zend_funct_info.c of php-src.
 */
return [
$inner_contents
];

EOT;
    }
}
OpcacheFuncInfoParser::main();
