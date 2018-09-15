<?php declare(strict_types=1);
namespace Phan\AST;

use ast;
use ast\Node;
use Closure;
use Phan\Analysis\PostOrderAnalysisVisitor;
use function implode;

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
    /** @var array<int,Closure(Node):string> */
    private static $closure_map;
    /** @var Closure(Node):string */
    private static $noop;

    // TODO: Make this configurable, copy instance properties to static properties.
    public function __construct()
    {
    }

    /**
     * @param Node|string|int|float $node
     * @return string
     */
    public static function toShortString($node)
    {
        if (!($node instanceof Node)) {
            // TODO: One-line representations for strings, minimal representations for floats, etc.
            return \var_export($node, true);
        }
        return (self::$closure_map[$node->kind] ?? self::$noop)($node);
    }

    /**
     * @return void
     */
    public static function init()
    {
        self::$noop = function (Node $_) : string {
            return '(unknown)';
        };
        self::$closure_map = [
            ast\AST_CLASS_CONST => function (Node $node) : string {
                return self::toShortString($node->children['class']) . '::' . $node->children['const'];
            },
            ast\AST_CONST => function (Node $node) : string {
                return self::toShortString($node->children['name']);
            },
            ast\AST_VAR => function (Node $node) : string {
                $name_node = $node->children['name'];
                return '$' . (is_string($name_node) ? $name_node : ('{' . self::toShortString($name_node) . '}'));
            },
            ast\AST_NAME => function (Node $node) : string {
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
            ast\AST_ARRAY => function (Node $node) : string {
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
            ast\AST_BINARY_OP => function (Node $node) : string {
                return \sprintf(
                    "(%s %s %s)",
                    self::toShortString($node->children['left']),
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags] ?? ' unknown ',
                    self::toShortString($node->children['right'])
                );
            },
            ast\AST_PROP => function (Node $node) : string {
                $prop_node = $node->children['prop'];
                return \sprintf(
                    '%s->%s',
                    self::toShortString($node->children['expr']),
                    $prop_node instanceof Node ? '{' . self::toShortString($prop_node) . '}' : (string)$prop_node
                );
            },
            ast\AST_STATIC_PROP => function (Node $node) : string {
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
