<?php declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast;
use ast\Node;
use Closure;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * This internal plugin will warn if calls to internal string functions
 * or regex function appear to have arguments in the wrong order,
 *
 * e.g. explode($var, ':') or strpos(':', $x)
 */
final class StringFunctionPlugin extends PluginV3 implements
    AnalyzeFunctionCallCapability
{
    /**
     * @param Node|string|float|int|null $arg
     * @return bool true if the expression is simple to look up.
     */
    private static function isSimpleExpression($arg) : bool
    {
        if (\is_scalar($arg)) {
            return true;
        }
        if ($arg === null) {
            return true;
        }
        if ($arg instanceof Node) {
            $kind = $arg->kind;
            if (\in_array($kind, [ast\AST_CONST, ast\AST_CLASS_CONST, ast\AST_CLASS_NAME], true)) {
                return true;
            }
            if ($kind === ast\AST_BINARY_OP) {
                // E.g. flags == BINARY_CONCAT
                return self::isSimpleExpression($arg->children['left']) && self::isSimpleExpression($arg->children['right']);
            } elseif ($kind === ast\AST_ARRAY) {
                foreach ($arg->children as $child) {
                    if (!($child instanceof Node)) {
                        continue;
                    }
                    if (!self::isSimpleExpression($child->children['key'])) {
                        return false;
                    }
                    if (!self::isSimpleExpression($child->children['value'])) {
                        return false;
                    }
                }
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string,Closure(CodeBase,Context,FunctionInterface,array,?Node):void>
     */
    private static function getAnalyzeFunctionCallClosuresStatic() : array
    {
        $make_order_warner = static function (int $expected_const_pos, int $expected_variable_pos) : Closure {
            $expected_arg_count = 1 + (int)\max($expected_const_pos, $expected_variable_pos);
            /**
             * @param list<Node|int|float|string> $args
             */
            return static function (
                CodeBase $code_base,
                Context $context,
                FunctionInterface $function,
                array $args,
                ?Node $_
            ) use (
                $expected_const_pos,
                $expected_variable_pos,
                $expected_arg_count
) : void {
                if (\count($args) < $expected_arg_count) {
                    return;
                }
                if (!self::isSimpleExpression($args[$expected_const_pos])) {
                    if (self::isSimpleExpression($args[$expected_variable_pos])) {
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::ParamSuspiciousOrder,
                            $context->getLineNumberStart(),
                            $expected_const_pos + 1,
                            $function->getFQSEN(),
                            $expected_variable_pos + 1
                        );
                    }
                }
            };
        };
        $var_1_const_2 = $make_order_warner(1, 0);
        $var_2_const_1 = $make_order_warner(0, 1);
        $var_3_const_1 = $make_order_warner(0, 2);
        $var_1_const_3 = $make_order_warner(2, 0);

        return [
            // Start of string functions from https://secure.php.net/manual/en/ref.strings.php
            'addcslashes'               => $var_1_const_2,
            'addslashes'                => $var_1_const_2,
            'chunk_split'               => $var_1_const_3,
            'convert_cyr_string'        => $var_1_const_2,
            'crypt'                     => $var_1_const_2,
            'explode'                   => $var_2_const_1,
            'htmlspecialchars_decode'   => $var_1_const_2,
            'htmlspecialchars_encode'   => $var_1_const_2,
            'ltrim'                     => $var_1_const_2,
            'md5_file'                  => $var_1_const_2,
            'md5'                       => $var_1_const_2,
            'metaphone'                 => $var_1_const_2,
            'mb_convert_case'           => $var_1_const_3,
            'mb_convert_encoding'       => $var_1_const_2,
            'mb_convert_kana'           => $var_1_const_2,
            'mb_convert_variables'      => $var_3_const_1,
            'mb_detect_encoding'        => $var_1_const_2,
            'mb_encoding_mimeheader'    => $var_1_const_2,
            'mb_ereg_match'             => $var_2_const_1,
            'mb_ereg_replace_callback'  => $var_3_const_1,
            'mb_ereg_replace'           => $var_3_const_1,
            'mb_ereg_search_init'       => $var_1_const_2,
            'mb_ereg_search_pos'        => $var_1_const_2,
            'mb_ereg'                   => $var_2_const_1,
            'mb_eregi_replace'          => $var_3_const_1,
            'mb_eregi'                  => $var_2_const_1,
            'mb_split'                  => $var_2_const_1,
            'mb_strcut'                 => $var_1_const_2,
            'mb_strimwidth'             => $var_1_const_2,
            'mb_stripos'                => $var_1_const_2,
            'mb_stristr'                => $var_1_const_2,
            'mb_strlen'                 => $var_1_const_2,
            'mb_strpos'                 => $var_1_const_2,
            'mb_strrchr'                => $var_1_const_2,  // what about mb_strchr?
            'mb_strrichr'               => $var_1_const_2,
            'mb_strripos'               => $var_1_const_2,
            'mb_strtolower'             => $var_1_const_2,
            'mb_strtoupper'             => $var_1_const_2,
            'mb_strwidth'               => $var_1_const_2,
            'mb_substr_count'           => $var_1_const_2,
            'mb_substr'                 => $var_1_const_2,
            'money_format'              => $var_2_const_1,
            'nl2br'                     => $var_1_const_2,
            'number_format'             => $var_1_const_2,
            'rtrim'                     => $var_1_const_2,
            'sha1_file'                 => $var_1_const_2,
            'sha1'                      => $var_1_const_2,
            'sscanf'                    => $var_1_const_2,
            'strchr'                    => $var_1_const_2,
            'strcspn'                   => $var_1_const_2,
            'str_getcsv'                => $var_1_const_2,
            'stripos'                   => $var_1_const_2,
            'strip_tags'                => $var_1_const_2,
            'str_ireplace'              => $var_3_const_1,
            'stristr'                   => $var_1_const_2,
            'str_pad'                   => $var_1_const_2,
            'strpbrk'                   => $var_1_const_2,
            'strpos'                    => $var_1_const_2,
            'str_replace'               => $var_3_const_1,
            'strripos'                  => $var_1_const_2,
            'strrpos'                   => $var_1_const_2,
            'str_split'                 => $var_1_const_2,
            'strspn'                    => $var_1_const_2,
            'strstr'                    => $var_1_const_2,
            'strtr'                     => $var_1_const_2,
            'str_word_count'            => $var_1_const_2,
            'substr_count'              => $var_1_const_2,
            'substr_replace'            => $var_1_const_2,
            'trim'                      => $var_1_const_2,
            'ucwords'                   => $var_1_const_2,
            'wordwrap'                  => $var_1_const_2,
            // End of string functions from https://secure.php.net/manual/en/ref.strings.php

            // Start of PCRE preg_* functions
            'preg_filter'               => $var_3_const_1,
            'preg_grep'                 => $var_2_const_1,
            'preg_match_all'            => $var_2_const_1,
            'preg_match'                => $var_2_const_1,
            'preg_quote'                => $var_1_const_2,
            'preg_replace_callback'     => $var_2_const_1,
            'preg_replace'              => $var_3_const_1,
            'preg_split'                => $var_2_const_1,
            // End of PCRE preg_* functions

        ];
    }

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @return array<string,Closure(CodeBase,Context,FunctionInterface,array,?Node):void>
     * @override
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $analyzers = null;
        if ($analyzers === null) {
            $analyzers = self::getAnalyzeFunctionCallClosuresStatic();
        }
        return $analyzers;
    }
}
