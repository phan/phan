<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\Node;
use Closure;
use Phan\Analysis\PostOrderAnalysisVisitor;

use function implode;
use function is_string;

/**
 * This converts a PHP AST into an approximate string representation.
 * This ignores line numbers and spacing.
 *
 * Eventual goals:
 *
 * 1. Short representations of constants for LSP hover requests.
 * 2. Short representations for errors (e.g. "Error at $x->foo(self::MY_CONST)")
 * 3. Configuration of rendering this.
 *
 * Similar utilities:
 *
 * - https://github.com/tpunt/php-ast-reverter is a pretty printer.
 * - \Phan\Debug::nodeToString() converts nodes to strings.
 */
class ASTReverter
{
    /** @var associative-array<int,Closure(Node):string> this contains maps from node kinds to closures to convert node kinds to strings */
    private static $closure_map;
    /** @var Closure(Node):string this maps unknown node types to strings */
    private static $noop;

    // TODO: Make this configurable, copy instance properties to static properties.
    public function __construct()
    {
    }

    /**
     * Convert $node to a short PHP string representing $node.
     *
     * This does not work for all node kinds, and may be ambiguous.
     *
     * @param Node|string|int|float|bool|null|resource $node
     */
    public static function toShortString($node): string
    {
        if (!($node instanceof Node)) {
            if ($node === null) {
                // use lowercase 'null' instead of 'NULL'
                return 'null';
            }
            if (\is_string($node)) {
                return self::escapeString($node);
            }
            if (\is_resource($node)) {
                return 'resource(' . \get_resource_type($node) . ')';
            }
            // TODO: minimal representations for floats, arrays, etc.
            return \var_export($node, true);
        }
        return (self::$closure_map[$node->kind] ?? self::$noop)($node);
    }

    /**
     * Escapes the inner contents to be suitable for a single-line single or double quoted string
     *
     * @see https://github.com/nikic/PHP-Parser/tree/master/lib/PhpParser/PrettyPrinter/Standard.php
     */
    public static function escapeString(string $string): string
    {
        if (\preg_match('/([\0-\15\16-\37])/', $string)) {
            // Use double quoted strings if this contains newlines, tabs, control characters, etc.
            return '"' . self::escapeInnerString($string, '"') . '"';
        }
        // Otherwise, use single quotes
        return \var_export($string, true);
    }

    /**
     * Escapes the inner contents to be suitable for a single-line double quoted string
     *
     * @see https://github.com/nikic/PHP-Parser/tree/master/lib/PhpParser/PrettyPrinter/Standard.php
     */
    public static function escapeInnerString(string $string, string $quote = null): string
    {
        if (null === $quote) {
            // For doc strings, don't escape newlines
            $escaped = \addcslashes($string, "\t\f\v$\\");
        } else {
            $escaped = \addcslashes($string, "\n\r\t\f\v$" . $quote . "\\");
        }

        // Escape other control characters
        return \preg_replace_callback('/([\0-\10\16-\37])(?=([0-7]?))/', /** @param list<string> $matches */ static function (array $matches): string {
            $oct = \decoct(\ord($matches[1]));
            if ($matches[2] !== '') {
                // If there is a trailing digit, use the full three character form
                return '\\' . \str_pad($oct, 3, '0', \STR_PAD_LEFT);
            }
            return '\\' . $oct;
        }, $escaped);
    }

    /**
     * Static initializer.
     */
    public static function init(): void
    {
        self::$noop = static function (Node $_): string {
            return '(unknown)';
        };
        self::$closure_map = [
            /**
             * @suppress PhanAccessClassConstantInternal
             */
            ast\AST_TYPE => static function (Node $node): string {
                return PostOrderAnalysisVisitor::AST_CAST_FLAGS_LOOKUP[$node->flags];
            },
            ast\AST_NULLABLE_TYPE => static function (Node $node): string {
                return '?' . self::toShortString($node->children['type']);
            },
            ast\AST_POST_INC => static function (Node $node): string {
                return self::formatIncDec('%s++', $node->children['var']);
            },
            ast\AST_PRE_INC => static function (Node $node): string {
                return self::formatIncDec('++%s', $node->children['var']);
            },
            ast\AST_POST_DEC => static function (Node $node): string {
                return self::formatIncDec('%s--', $node->children['var']);
            },
            ast\AST_PRE_DEC => static function (Node $node): string {
                return self::formatIncDec('--%s', $node->children['var']);
            },
            ast\AST_ARG_LIST => static function (Node $node): string {
                return '(' . implode(', ', \array_map('self::toShortString', $node->children)) . ')';
            },
            ast\AST_EXPR_LIST => static function (Node $node): string {
                return implode(', ', \array_map('self::toShortString', $node->children));
            },
            ast\AST_CLASS_CONST => static function (Node $node): string {
                return self::toShortString($node->children['class']) . '::' . $node->children['const'];
            },
            ast\AST_CLASS_NAME => static function (Node $node): string {
                return self::toShortString($node->children['class']) . '::class';
            },
            ast\AST_MAGIC_CONST => static function (Node $node): string {
                return UnionTypeVisitor::MAGIC_CONST_NAME_MAP[$node->flags] ?? '(unknown)';
            },
            ast\AST_CONST => static function (Node $node): string {
                return self::toShortString($node->children['name']);
            },
            ast\AST_VAR => static function (Node $node): string {
                $name_node = $node->children['name'];
                return '$' . (is_string($name_node) ? $name_node : ('{' . self::toShortString($name_node) . '}'));
            },
            ast\AST_DIM => static function (Node $node): string {
                $expr_str = self::toShortString($node->children['expr']);
                if ($expr_str === '(unknown)') {
                    return  '(unknown)';
                }

                $dim = $node->children['dim'];
                if ($dim !== null) {
                    $dim_str = self::toShortString($dim);
                } else {
                    $dim_str = '';
                }
                if ($node->flags & ast\flags\DIM_ALTERNATIVE_SYNTAX) {
                    return "${expr_str}{{$dim_str}}";
                }
                return "${expr_str}[$dim_str]";
            },
            ast\AST_NAME => static function (Node $node): string {
                $result = $node->children['name'];
                switch ($node->flags) {
                    case ast\flags\NAME_FQ:
                        return '\\' . $result;
                    case ast\flags\NAME_RELATIVE:
                        return 'namespace\\' . $result;
                    default:
                        return (string)$result;
                }
            },
            ast\AST_ARRAY => static function (Node $node): string {
                $parts = [];
                foreach ($node->children as $elem) {
                    if (!$elem instanceof Node) {
                        // Should always either be a Node or null.
                        $parts[] = '';
                        continue;
                    }
                    $part = self::toShortString($elem->children['value']);
                    $key_node = $elem->children['key'];
                    if ($key_node !== null) {
                        $part = self::toShortString($key_node) . '=>' . $part;
                    }
                    $parts[] = $part;
                }
                $string = implode(',', $parts);
                switch ($node->flags) {
                    case ast\flags\ARRAY_SYNTAX_SHORT:
                    case ast\flags\ARRAY_SYNTAX_LONG:
                    default:
                        return "[$string]";
                    case ast\flags\ARRAY_SYNTAX_LIST:
                        return "list($string)";
                }
            },
            /** @suppress PhanAccessClassConstantInternal */
            ast\AST_BINARY_OP => static function (Node $node): string {
                return \sprintf(
                    "(%s %s %s)",
                    self::toShortString($node->children['left']),
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags] ?? 'unknown',
                    self::toShortString($node->children['right'])
                );
            },
            ast\AST_ASSIGN => static function (Node $node): string {
                return \sprintf(
                    "(%s = %s)",
                    self::toShortString($node->children['var']),
                    self::toShortString($node->children['expr'])
                );
            },
            /** @suppress PhanAccessClassConstantInternal */
            ast\AST_ASSIGN_OP => static function (Node $node): string {
                return \sprintf(
                    "(%s %s= %s)",
                    self::toShortString($node->children['var']),
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags] ?? 'unknown',
                    self::toShortString($node->children['expr'])
                );
            },
            ast\AST_UNARY_OP => static function (Node $node): string {
                $operation_name = PostOrderAnalysisVisitor::NAME_FOR_UNARY_OP[$node->flags] ?? null;
                if (!$operation_name) {
                    return '(unknown)';
                }
                $expr = $node->children['expr'];
                $expr_text = self::toShortString($expr);
                if (($expr->kind ?? null) !== ast\AST_UNARY_OP) {
                    return $operation_name . $expr_text;
                }
                return \sprintf("%s(%s)", $operation_name, $expr_text);
            },
            ast\AST_PROP => static function (Node $node): string {
                $prop_node = $node->children['prop'];
                return \sprintf(
                    '%s->%s',
                    self::toShortString($node->children['expr']),
                    $prop_node instanceof Node ? '{' . self::toShortString($prop_node) . '}' : (string)$prop_node
                );
            },
            ast\AST_STATIC_CALL => static function (Node $node): string {
                $method_node = $node->children['method'];
                return \sprintf(
                    '%s::%s%s',
                    self::toShortString($node->children['class']),
                    is_string($method_node) ? $method_node : self::toShortString($method_node),
                    self::toShortString($node->children['args'])
                );
            },
            ast\AST_METHOD_CALL => static function (Node $node): string {
                $method_node = $node->children['method'];
                return \sprintf(
                    '%s->%s%s',
                    self::toShortString($node->children['expr']),
                    is_string($method_node) ? $method_node : self::toShortString($method_node),
                    self::toShortString($node->children['args'])
                );
            },
            ast\AST_STATIC_PROP => static function (Node $node): string {
                $prop_node = $node->children['prop'];
                return \sprintf(
                    '%s::$%s',
                    self::toShortString($node->children['class']),
                    $prop_node instanceof Node ? '{' . self::toShortString($prop_node) . '}' : (string)$prop_node
                );
            },
            ast\AST_INSTANCEOF => static function (Node $node): string {
                return \sprintf(
                    '(%s instanceof %s)',
                    self::toShortString($node->children['expr']),
                    self::toShortString($node->children['class'])
                );
            },
            ast\AST_CAST => static function (Node $node): string {
                return \sprintf(
                    '(%s)(%s)',
                    // @phan-suppress-next-line PhanAccessClassConstantInternal
                    PostOrderAnalysisVisitor::AST_CAST_FLAGS_LOOKUP[$node->flags] ?? 'unknown',
                    self::toShortString($node->children['expr'])
                );
            },
            ast\AST_CALL => static function (Node $node): string {
                return \sprintf(
                    '%s%s',
                    self::toShortString($node->children['expr']),
                    self::toShortString($node->children['args'])
                );
            },
            ast\AST_NEW => static function (Node $node): string {
                return \sprintf(
                    'new %s%s',
                    self::toShortString($node->children['class']),
                    self::toShortString($node->children['args'])
                );
            },
            ast\AST_CONDITIONAL => static function (Node $node): string {
                ['cond' => $cond, 'true' => $true, 'false' => $false] = $node->children;
                if ($true !== null) {
                    return \sprintf('(%s ? %s : %s)', self::toShortString($cond), self::toShortString($true), self::toShortString($false));
                }
                return \sprintf('(%s ?: %s)', self::toShortString($cond), self::toShortString($false));
            },
            ast\AST_ISSET => static function (Node $node): string {
                return \sprintf(
                    'isset(%s)',
                    self::toShortString($node->children['var'])
                );
            },
            ast\AST_EMPTY => static function (Node $node): string {
                return \sprintf(
                    'empty(%s)',
                    self::toShortString($node->children['expr'])
                );
            },
        ];
    }

    /**
     * @param Node|string|int|float $node
     */
    private static function formatIncDec(string $format, $node): string
    {
        $str = self::toShortString($node);
        if (!($node instanceof Node && $node->kind === ast\AST_VAR)) {
            $str = '(' . $str . ')';
        }
        // @phan-suppress-next-line PhanPluginPrintfVariableFormatString
        return \sprintf($format, $str);
    }
}
ASTReverter::init();
