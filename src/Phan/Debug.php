<?php declare(strict_types=1);
namespace Phan;

use ast\Node;
use ast\Node\Decl;

/**
 * Debug utilities
 */
class Debug
{
    // option for self::astDump
    const AST_DUMP_LINENOS = 1;

    /**
     * Print a lil' something to the console to
     * see if a thing is called
     *
     * @suppress PhanUnreferencedMethod
     */
    public static function mark()
    {
        print "mark\n";
    }

    /**
     * @param string|Node|null $node
     * An AST node
     *
     * Print an AST node
     *
     * @return void
     *
     * @suppress PhanUnreferencedMethod
     */
    public static function printNode($node)
    {
        print self::nodeToString($node);
    }

    /**
     * Print the name of a node to the terminal
     *
     * @suppress PhanUnreferencedMethod
     */
    public static function printNodeName($node, $indent = 0)
    {
        print str_repeat("\t", $indent);
        print self::nodeName($node);
        print "\n";
    }

    /**
     * Print a thing with the given indent level
     *
     * @suppress PhanUnreferencedMethod
     */
    public static function print(string $message, int $indent = 0)
    {
        print str_repeat("\t", $indent);
        print $message . "\n";
    }

    /**
     * @return string
     * The name of the node
     */
    public static function nodeName($node) : string
    {
        if (\is_string($node)) {
            return 'string';
        }

        if (!$node) {
            return 'null';
        }

        return \ast\get_kind_name($node->kind);
    }

    /**
     * @param string|Node|null $node
     * An AST node
     *
     * @param int $indent
     * The indentation level for the string
     *
     * @return string
     * A string representation of an AST node
     */
    public static function nodeToString(
        $node,
        $name = null,
        int $indent = 0
    ) : string {
        $string = str_repeat("\t", $indent);

        if ($name !== null) {
            $string .= "$name => ";
        }

        if (\is_string($node)) {
            return $string . $node . "\n";
        }

        if (!$node) {
            return $string . 'null' . "\n";
        }

        if (!\is_object($node)) {
            return $string . $node . "\n";
        }

        $string .= \ast\get_kind_name($node->kind);

        $string .= ' ['
            . self::astFlagDescription($node->flags ?? 0, $node->kind)
            . ']';

        if (isset($node->lineno)) {
            $string .= ' #' . $node->lineno;
        }

        if ($node instanceof Decl) {
            if (isset($node->endLineno)) {
                $string .= ':' . $node->endLineno;
            }
        }

        if (isset($node->name)) {
            $string .= ' name:' . $node->name;
        }

        $string .= "\n";

        foreach ($node->children ?? [] as $name => $child_node) {
            $string .= self::nodeToString(
                $child_node,
                $name,
                $indent + 1
            );
        }

        return $string;
    }

    /**
     * @return string
     * Get a string representation of AST node flags such as
     * 'ASSIGN_DIV|TYPE_ARRAY'
     * @see self::formatFlags for a similar function also printing the integer flag value.
     */
    public static function astFlagDescription(int $flags, int $kind) : string
    {
        list($exclusive, $combinable) = self::getFlagInfo();
        $flag_names = [];
        if (isset($exclusive[$kind])) {
            $flagInfo = $exclusive[$kind];
            if (isset($flagInfo[$flags])) {
                $flag_names[] = $flagInfo[$flags];
            }
        } else if (isset($combinable[$kind])) {
            $flagInfo = $combinable[$kind];
            foreach ($flagInfo as $flag => $name) {
                if ($flags & $flag) {
                    $flag_names[] = $name;
                }
            }
        }

        return implode('|', $flag_names);
    }

    /**
     * @return string
     * Get a string representation of AST node flags such as
     * 'ASSIGN_DIV (26)'
     * Source: https://github.com/nikic/php-ast/blob/master/util.php
     */
    public static function formatFlags(int $kind, int $flags) : string {
        list($exclusive, $combinable) = self::getFlagInfo();
        if (isset($exclusive[$kind])) {
            $flagInfo = $exclusive[$kind];
            if (isset($flagInfo[$flags])) {
                return "{$flagInfo[$flags]} ($flags)";
            }
        } else if (isset($combinable[$kind])) {
            $flagInfo = $combinable[$kind];
            $names = [];
            foreach ($flagInfo as $flag => $name) {
                if ($flags & $flag) {
                    $names[] = $name;
                }
            }
            if (!empty($names)) {
                return implode(" | ", $names) . " ($flags)";
            }
        }
        return (string) $flags;
    }


    /**
     * @return void
     * Pretty-printer for debug_backtrace
     *
     * @suppress PhanUnreferencedMethod
     */
    public static function backtrace(int $levels = 0)
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $levels+1);
        foreach ($bt as $level => $context) {
            if (!$level) {
                continue;
            }
            echo "#".($level-1)." {$context['file']}:{$context['line']} {$context['class']} ";
            if (!empty($context['type'])) {
                echo $context['class'].$context['type'];
            }
            echo $context['function'];
            echo "\n";
        }
    }

    /**
     * Dumps abstract syntax tree
     * Source: https://github.com/nikic/php-ast/blob/master/util.php
     */
    public static function astDump($ast, int $options = 0) : string {
        if ($ast instanceof \ast\Node) {
            $result = \ast\get_kind_name($ast->kind);

            if ($options & self::AST_DUMP_LINENOS) {
                $result .= " @ $ast->lineno";
                if ($ast instanceof \ast\Node\Decl && isset($ast->endLineno)) {
                    $result .= "-$ast->endLineno";
                }
            }

            if (\ast\kind_uses_flags($ast->kind)) {
                $result .= "\n    flags: " . self::formatFlags($ast->kind, $ast->flags);
            }
            if ($ast instanceof \ast\Node\Decl && isset($ast->name)) {
                $result .= "\n    name: $ast->name";
            }
            if ($ast instanceof \ast\Node\Decl && isset($ast->docComment)) {
                $result .= "\n    docComment: $ast->docComment";
            }
            foreach ($ast->children as $i => $child) {
                $result .= "\n    $i: " . str_replace("\n", "\n    ", self::astDump($child, $options));
            }
            return $result;
        } else if ($ast === null) {
            return 'null';
        } else if (\is_string($ast)) {
            return "\"$ast\"";
        } else {
            return (string) $ast;
        }
    }

    /**
     * Source: https://github.com/nikic/php-ast/blob/master/util.php
     * @return string[][][]
     * Return value is [string[][] $exclusive, string[][] $combinable]. Maps node id to flag id to name.
     */
    private static function getFlagInfo() : array {
        static $exclusive, $combinable;
        if ($exclusive !== null) {
            return [$exclusive, $combinable];
        }

        $modifiers = [
            \ast\flags\MODIFIER_PUBLIC => 'MODIFIER_PUBLIC',
            \ast\flags\MODIFIER_PROTECTED => 'MODIFIER_PROTECTED',
            \ast\flags\MODIFIER_PRIVATE => 'MODIFIER_PRIVATE',
            \ast\flags\MODIFIER_STATIC => 'MODIFIER_STATIC',
            \ast\flags\MODIFIER_ABSTRACT => 'MODIFIER_ABSTRACT',
            \ast\flags\MODIFIER_FINAL => 'MODIFIER_FINAL',
            \ast\flags\RETURNS_REF => 'RETURNS_REF',
        ];
        $types = [
            \ast\flags\TYPE_NULL => 'TYPE_NULL',
            \ast\flags\TYPE_BOOL => 'TYPE_BOOL',
            \ast\flags\TYPE_LONG => 'TYPE_LONG',
            \ast\flags\TYPE_DOUBLE => 'TYPE_DOUBLE',
            \ast\flags\TYPE_STRING => 'TYPE_STRING',
            \ast\flags\TYPE_ARRAY => 'TYPE_ARRAY',
            \ast\flags\TYPE_OBJECT => 'TYPE_OBJECT',
            \ast\flags\TYPE_CALLABLE => 'TYPE_CALLABLE',
            \ast\flags\TYPE_VOID => 'TYPE_VOID',
            \ast\flags\TYPE_ITERABLE => 'TYPE_ITERABLE',
        ];
        $useTypes = [
            \ast\flags\USE_NORMAL => 'USE_NORMAL',
            \ast\flags\USE_FUNCTION => 'USE_FUNCTION',
            \ast\flags\USE_CONST => 'USE_CONST',
        ];
        $sharedBinaryOps = [
            \ast\flags\BINARY_BITWISE_OR => 'BINARY_BITWISE_OR',
            \ast\flags\BINARY_BITWISE_AND => 'BINARY_BITWISE_AND',
            \ast\flags\BINARY_BITWISE_XOR => 'BINARY_BITWISE_XOR',
            \ast\flags\BINARY_CONCAT => 'BINARY_CONCAT',
            \ast\flags\BINARY_ADD => 'BINARY_ADD',
            \ast\flags\BINARY_SUB => 'BINARY_SUB',
            \ast\flags\BINARY_MUL => 'BINARY_MUL',
            \ast\flags\BINARY_DIV => 'BINARY_DIV',
            \ast\flags\BINARY_MOD => 'BINARY_MOD',
            \ast\flags\BINARY_POW => 'BINARY_POW',
            \ast\flags\BINARY_SHIFT_LEFT => 'BINARY_SHIFT_LEFT',
            \ast\flags\BINARY_SHIFT_RIGHT => 'BINARY_SHIFT_RIGHT',
        ];

        $exclusive = [
            \ast\AST_NAME => [
                \ast\flags\NAME_FQ => 'NAME_FQ',
                \ast\flags\NAME_NOT_FQ => 'NAME_NOT_FQ',
                \ast\flags\NAME_RELATIVE => 'NAME_RELATIVE',
            ],
            \ast\AST_CLASS => [
                \ast\flags\CLASS_ABSTRACT => 'CLASS_ABSTRACT',
                \ast\flags\CLASS_FINAL => 'CLASS_FINAL',
                \ast\flags\CLASS_TRAIT => 'CLASS_TRAIT',
                \ast\flags\CLASS_INTERFACE => 'CLASS_INTERFACE',
                \ast\flags\CLASS_ANONYMOUS => 'CLASS_ANONYMOUS',
            ],
            \ast\AST_PARAM => [
                \ast\flags\PARAM_REF => 'PARAM_REF',
                \ast\flags\PARAM_VARIADIC => 'PARAM_VARIADIC',
            ],
            \ast\AST_TYPE => $types,
            \ast\AST_CAST => $types,
            \ast\AST_UNARY_OP => [
                \ast\flags\UNARY_BOOL_NOT => 'UNARY_BOOL_NOT',
                \ast\flags\UNARY_BITWISE_NOT => 'UNARY_BITWISE_NOT',
                \ast\flags\UNARY_MINUS => 'UNARY_MINUS',
                \ast\flags\UNARY_PLUS => 'UNARY_PLUS',
                \ast\flags\UNARY_SILENCE => 'UNARY_SILENCE',
            ],
            \ast\AST_BINARY_OP => $sharedBinaryOps + [
                \ast\flags\BINARY_BOOL_AND => 'BINARY_BOOL_AND',
                \ast\flags\BINARY_BOOL_OR => 'BINARY_BOOL_OR',
                \ast\flags\BINARY_BOOL_XOR => 'BINARY_BOOL_XOR',
                \ast\flags\BINARY_IS_IDENTICAL => 'BINARY_IS_IDENTICAL',
                \ast\flags\BINARY_IS_NOT_IDENTICAL => 'BINARY_IS_NOT_IDENTICAL',
                \ast\flags\BINARY_IS_EQUAL => 'BINARY_IS_EQUAL',
                \ast\flags\BINARY_IS_NOT_EQUAL => 'BINARY_IS_NOT_EQUAL',
                \ast\flags\BINARY_IS_SMALLER => 'BINARY_IS_SMALLER',
                \ast\flags\BINARY_IS_SMALLER_OR_EQUAL => 'BINARY_IS_SMALLER_OR_EQUAL',
                \ast\flags\BINARY_IS_GREATER => 'BINARY_IS_GREATER',
                \ast\flags\BINARY_IS_GREATER_OR_EQUAL => 'BINARY_IS_GREATER_OR_EQUAL',
                \ast\flags\BINARY_SPACESHIP => 'BINARY_SPACESHIP',
                \ast\flags\BINARY_COALESCE => 'BINARY_COALESCE',
            ],
            \ast\AST_ASSIGN_OP => $sharedBinaryOps + [
                // Old version 10 flags
                \ast\flags\ASSIGN_BITWISE_OR => 'ASSIGN_BITWISE_OR',
                \ast\flags\ASSIGN_BITWISE_AND => 'ASSIGN_BITWISE_AND',
                \ast\flags\ASSIGN_BITWISE_XOR => 'ASSIGN_BITWISE_XOR',
                \ast\flags\ASSIGN_CONCAT => 'ASSIGN_CONCAT',
                \ast\flags\ASSIGN_ADD => 'ASSIGN_ADD',
                \ast\flags\ASSIGN_SUB => 'ASSIGN_SUB',
                \ast\flags\ASSIGN_MUL => 'ASSIGN_MUL',
                \ast\flags\ASSIGN_DIV => 'ASSIGN_DIV',
                \ast\flags\ASSIGN_MOD => 'ASSIGN_MOD',
                \ast\flags\ASSIGN_POW => 'ASSIGN_POW',
                \ast\flags\ASSIGN_SHIFT_LEFT => 'ASSIGN_SHIFT_LEFT',
                \ast\flags\ASSIGN_SHIFT_RIGHT => 'ASSIGN_SHIFT_RIGHT',
            ],
            \ast\AST_MAGIC_CONST => [
                \ast\flags\MAGIC_LINE => 'MAGIC_LINE',
                \ast\flags\MAGIC_FILE => 'MAGIC_FILE',
                \ast\flags\MAGIC_DIR => 'MAGIC_DIR',
                \ast\flags\MAGIC_NAMESPACE => 'MAGIC_NAMESPACE',
                \ast\flags\MAGIC_FUNCTION => 'MAGIC_FUNCTION',
                \ast\flags\MAGIC_METHOD => 'MAGIC_METHOD',
                \ast\flags\MAGIC_CLASS => 'MAGIC_CLASS',
                \ast\flags\MAGIC_TRAIT => 'MAGIC_TRAIT',
            ],
            \ast\AST_USE => $useTypes,
            \ast\AST_GROUP_USE => $useTypes,
            \ast\AST_USE_ELEM => $useTypes,
            \ast\AST_INCLUDE_OR_EVAL => [
                \ast\flags\EXEC_EVAL => 'EXEC_EVAL',
                \ast\flags\EXEC_INCLUDE => 'EXEC_INCLUDE',
                \ast\flags\EXEC_INCLUDE_ONCE => 'EXEC_INCLUDE_ONCE',
                \ast\flags\EXEC_REQUIRE => 'EXEC_REQUIRE',
                \ast\flags\EXEC_REQUIRE_ONCE => 'EXEC_REQUIRE_ONCE',
            ],
            \ast\AST_ARRAY => [
                \ast\flags\ARRAY_SYNTAX_LIST => 'ARRAY_SYNTAX_LIST',
                \ast\flags\ARRAY_SYNTAX_LONG => 'ARRAY_SYNTAX_LONG',
                \ast\flags\ARRAY_SYNTAX_SHORT => 'ARRAY_SYNTAX_SHORT',
            ],
        ];

        $combinable = [];
        $combinable[\ast\AST_METHOD] = $combinable[\ast\AST_FUNC_DECL] = $combinable[\ast\AST_CLOSURE]
            = $combinable[\ast\AST_PROP_DECL] = $combinable[\ast\AST_CLASS_CONST_DECL]
            = $combinable[\ast\AST_TRAIT_ALIAS] = $modifiers;

        return [$exclusive, $combinable];
    }
}
