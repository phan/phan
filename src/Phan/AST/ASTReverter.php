<?php declare(strict_types=1);

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
    /** @var array<int,Closure(Node):string> this contains closures to convert node kinds to strings */
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
     * @param Node|string|int|float|bool|null $node
     * @return string
     */
    public static function toShortString($node)
    {
        if (!($node instanceof Node)) {
            if ($node === null) {
                // use lowercase 'null' instead of 'NULL'
                return 'null';
            }
            if (\is_string($node)) {
                return self::escapeString($node);
            }
            // TODO: minimal representations for floats, etc.
            return \var_export($node, true);
        }
        return (self::$closure_map[$node->kind] ?? self::$noop)($node);
    }

    /**
     * Escapes the inner contents to be suitable for a single-line single or double quoted string
     *
     * @see https://github.com/nikic/PHP-Parser/tree/master/lib/PhpParser/PrettyPrinter/Standard.php
     */
    public static function escapeString(string $string) : string
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
    public static function escapeInnerString(string $string, string $quote = null) : string
    {
        if (null === $quote) {
            // For doc strings, don't escape newlines
            $escaped = \addcslashes($string, "\t\f\v$\\");
        } else {
            $escaped = \addcslashes($string, "\n\r\t\f\v$" . $quote . "\\");
        }

        // Escape other control characters
        return \preg_replace_callback('/([\0-\10\16-\37])(?=([0-7]?))/', /** @param array<int,string> $matches */ static function (array $matches) : string {
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
     *
     * @return void
     */
    public static function init()
    {
        self::$noop = static function (Node $_) : string {
            return '(unknown)';
        };
        self::$closure_map = [
            ast\AST_CLASS_CONST => static function (Node $node) : string {
                return self::toShortString($node->children['class']) . '::' . $node->children['const'];
            },
            ast\AST_CONST => static function (Node $node) : string {
                return self::toShortString($node->children['name']);
            },
            ast\AST_VAR => static function (Node $node) : string {
                $name_node = $node->children['name'];
                return '$' . (is_string($name_node) ? $name_node : ('{' . self::toShortString($name_node) . '}'));
            },
            ast\AST_DIM => static function (Node $node) : string {
                $expr_str = self::toShortString($node->children['expr']);
                if ($expr_str === '(unknown)') {
                    return  '(unknown)';
                }

                $dim_str = self::toShortString($node->children['dim']);
                return "${expr_str}[${dim_str}]";
            },
            ast\AST_NAME => static function (Node $node) : string {
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
            ast\AST_ARRAY => static function (Node $node) : string {
                $parts = [];
                foreach ($node->children as $elem) {
                    if (!$elem) {
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
            ast\AST_BINARY_OP => static function (Node $node) : string {
                return \sprintf(
                    "(%s %s %s)",
                    self::toShortString($node->children['left']),
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags] ?? ' unknown ',
                    self::toShortString($node->children['right'])
                );
            },
            ast\AST_PROP => static function (Node $node) : string {
                $prop_node = $node->children['prop'];
                return \sprintf(
                    '%s->%s',
                    self::toShortString($node->children['expr']),
                    $prop_node instanceof Node ? '{' . self::toShortString($prop_node) . '}' : (string)$prop_node
                );
            },
            ast\AST_STATIC_PROP => static function (Node $node) : string {
                $prop_node = $node->children['prop'];
                return \sprintf(
                    '%s::$%s',
                    self::toShortString($node->children['class']),
                    $prop_node instanceof Node ? '{' . self::toShortString($prop_node) . '}' : (string)$prop_node
                );
            },
        ];
    }
}
ASTReverter::init();
