<?php declare(strict_types=1);
namespace Phan\Plugin\Internal;

use Phan\CodeBase;
use Phan\Analysis\ArgumentType;
use Phan\Analysis\PostOrderAnalysisVisitor;
use Phan\AST\UnionTypeVisitor;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Type\ClosureType;
use Phan\Language\UnionType;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeFunctionCallCapability;
use Phan\PluginV2\ReturnTypeOverrideCapability;
use ast;
use ast\Node;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * This internal plugin will warn if calls to internal string functions
 * or regex function appear to have arguments in the wrong order,
 *
 * e.g. explode($var, ':') or strpos(':', $x)
 */
final class StringFunctionPlugin extends PluginV2 implements
    AnalyzeFunctionCallCapability {

    /**
     * @param Node|int|string $arg_array_node
     * @return ?array
     */
    private static function extractArrayArgs($arg_array_node) {
        if (($arg_array_node instanceof Node) && $arg_array_node->kind === \ast\AST_ARRAY) {
            $arguments = [];
            // TODO: Sanity check keys.
            foreach ($arg_array_node->children as $child) {
                $arguments[] = $child->children['value'];
            }
            return $arguments;
        } else {
            return null;
        }
    }

    /**
     * @param Node|string|float|int $arg
     * @return bool true if the expression is simple to look up.
     */
    private static function isSimpleExpression($arg) : bool
    {
        if (\is_scalar($arg)) {
            return true;
        }
        if ($arg instanceof Node) {
            $kind = $arg->kind;
            if ($kind === ast\AST_CONST || $kind === ast\AST_CLASS_CONST) {
                return true;
            }
            if ($kind === ast\AST_BINARY_OP) {
                // E.g. flags == BINARY_CONCAT
                return self::isSimpleExpression($arg->children['left']) && self::isSimpleExpression($arg->children['right']);
            } else if ($kind === ast\AST_ARRAY) {
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
     * @return \Closure[]
     */
    private static function getAnalyzeFunctionCallClosuresStatic(CodeBase $code_base) : array
    {
        $make_order_warner = static function(int $expected_const_pos, int $expected_variable_pos) : \Closure{
            $expected_arg_count = 1 + (int)max($expected_const_pos, $expected_variable_pos);
            /**
             * @return void
             */
            return static function(
                CodeBase $code_base,
                Context $context,
                Func $function,
                array $args
            ) use ($expected_const_pos, $expected_variable_pos, $expected_arg_count) {
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
        $variable_should_be_first_callback = $make_order_warner(1, 0);
        $variable_should_be_second_callback = $make_order_warner(0, 1);
        $variable_should_be_third_callback = $make_order_warner(0, 2);
        $variable_should_be_first_not_third_callback = $make_order_warner(2, 0);

        return [
            // Start of string functions from https://secure.php.net/manual/en/ref.strings.php
            'addcslashes'               => $variable_should_be_first_callback,
            'addslashes'                => $variable_should_be_first_callback,
            'chunk_split'               => $variable_should_be_first_not_third_callback,
            'convert_cyr_string'        => $variable_should_be_first_callback,
            'crypt'                     => $variable_should_be_first_callback,
            'explode'                   => $variable_should_be_second_callback,
            'htmlspecialchars_decode'   => $variable_should_be_first_callback,
            'htmlspecialchars_encode'   => $variable_should_be_first_callback,
            'implode'                   => $variable_should_be_second_callback,
            'join'                      => $variable_should_be_second_callback,
            'ltrim'                     => $variable_should_be_first_callback,
            'md5_file'                  => $variable_should_be_first_callback,
            'md5'                       => $variable_should_be_first_callback,
            'metaphone'                 => $variable_should_be_first_callback,
            'money_format'              => $variable_should_be_first_callback,
            'nl2br'                     => $variable_should_be_first_callback,
            'number_format'             => $variable_should_be_first_callback,
            'rtrim'                     => $variable_should_be_first_callback,
            'sha1_file'                 => $variable_should_be_first_callback,
            'sha1'                      => $variable_should_be_first_callback,
            'sscanf'                    => $variable_should_be_first_callback,
            'strchr'                    => $variable_should_be_first_callback,
            'strcspn'                   => $variable_should_be_first_callback,
            'str_getcsv'                => $variable_should_be_first_callback,
            'stripos'                   => $variable_should_be_first_callback,
            'strip_tags'                => $variable_should_be_first_callback,
            'str_ireplace'              => $variable_should_be_third_callback,
            'stristr'                   => $variable_should_be_first_callback,
            'str_pad'                   => $variable_should_be_first_callback,
            'strpbrk'                   => $variable_should_be_first_callback,
            'strpos'                    => $variable_should_be_first_callback,
            'str_replace'               => $variable_should_be_third_callback,
            'strripos'                  => $variable_should_be_first_callback,
            'strrpos'                   => $variable_should_be_first_callback,
            'str_split'                 => $variable_should_be_first_callback,
            'strspn'                    => $variable_should_be_first_callback,
            'strstr'                    => $variable_should_be_first_callback,
            'strtr'                     => $variable_should_be_first_callback,
            'str_word_count'            => $variable_should_be_first_callback,
            'substr_count'              => $variable_should_be_first_callback,
            'substr_replace'            => $variable_should_be_first_callback,
            'trim'                      => $variable_should_be_first_callback,
            'ucwords'                   => $variable_should_be_first_callback,
            'wordwrap'                  => $variable_should_be_first_callback,
            // End of string functions from https://secure.php.net/manual/en/ref.strings.php

            // Start of PCRE preg_* functions
            'preg_filter'               => $variable_should_be_third_callback,
            'preg_grep'                 => $variable_should_be_second_callback,
            'preg_match_all'            => $variable_should_be_second_callback,
            'preg_match'                => $variable_should_be_second_callback,
            'preg_quote'                => $variable_should_be_first_callback,
            'preg_replace_callback'     => $variable_should_be_second_callback,
            'preg_replace'              => $variable_should_be_third_callback,
            'preg_split'                => $variable_should_be_second_callback,
            // End of PCRE preg_* functions

        ];
    }

    /**
     * @return \Closure[]
     * @override
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $analyzers = null;
        if ($analyzers === null) {
            $analyzers = self::getAnalyzeFunctionCallClosuresStatic($code_base);
        }
        return $analyzers;
    }
}
