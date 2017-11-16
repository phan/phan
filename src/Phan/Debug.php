<?php declare(strict_types=1);
namespace Phan;

use ast\Node;
use ast\flags;

/**
 * Debug utilities
 *
 * Mostly utilities for printing representations of AST nodes.
 * Also see `Debug/`
 */
class Debug
{
    // option for self::astDump
    const AST_DUMP_LINENOS = 1;

    /**
     * Print a lil' something to the console to
     * see if a thing is called
     *
     * @suppress PhanUnreferencedPublicMethod
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
     * @suppress PhanUnreferencedPublicMethod
     */
    public static function printNode($node)
    {
        print self::nodeToString($node);
    }

    /**
     * Print the name of a node to the terminal
     *
     * @suppress PhanUnreferencedPublicMethod
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
     * @suppress PhanUnreferencedPublicMethod
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

        $endLineno = $node->endLineno ?? null;
        if (!\is_null($endLineno)) {
            $string .= ':' . $endLineno;
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
        } elseif (isset($combinable[$kind])) {
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
    public static function formatFlags(int $kind, int $flags) : string
    {
        list($exclusive, $combinable) = self::getFlagInfo();
        if (isset($exclusive[$kind])) {
            $flagInfo = $exclusive[$kind];
            if (isset($flagInfo[$flags])) {
                return "{$flagInfo[$flags]} ($flags)";
            }
        } elseif (isset($combinable[$kind])) {
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
     * @suppress PhanUnreferencedPublicMethod
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
    public static function astDump($ast, int $options = 0) : string
    {
        if ($ast instanceof \ast\Node) {
            $result = \ast\get_kind_name($ast->kind);

            if ($options & self::AST_DUMP_LINENOS) {
                $result .= " @ $ast->lineno";
                $endLineno = $ast->endLineno ?? null;
                if (!\is_null($endLineno)) {
                    $result .= "-$endLineno";
                }
            }

            if (\ast\kind_uses_flags($ast->kind) || $ast->flags != 0) {
                $result .= "\n    flags: " . self::formatFlags($ast->kind, $ast->flags);
            }
            foreach ($ast->children as $i => $child) {
                $result .= "\n    $i: " . str_replace("\n", "\n    ", self::astDump($child, $options));
            }
            return $result;
        } elseif ($ast === null) {
            return 'null';
        } elseif (\is_string($ast)) {
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
    private static function getFlagInfo() : array
    {
        static $exclusive, $combinable;
        if ($exclusive !== null) {
            return [$exclusive, $combinable];
        }

        $modifiers = [
            flags\MODIFIER_PUBLIC => 'MODIFIER_PUBLIC',
            flags\MODIFIER_PROTECTED => 'MODIFIER_PROTECTED',
            flags\MODIFIER_PRIVATE => 'MODIFIER_PRIVATE',
            flags\MODIFIER_STATIC => 'MODIFIER_STATIC',
            flags\MODIFIER_ABSTRACT => 'MODIFIER_ABSTRACT',
            flags\MODIFIER_FINAL => 'MODIFIER_FINAL',
            flags\FUNC_RETURNS_REF => 'FUNC_RETURNS_REF',
            flags\FUNC_GENERATOR => 'FUNC_GENERATOR',
        ];
        $types = [
            flags\TYPE_NULL => 'TYPE_NULL',
            flags\TYPE_BOOL => 'TYPE_BOOL',
            flags\TYPE_LONG => 'TYPE_LONG',
            flags\TYPE_DOUBLE => 'TYPE_DOUBLE',
            flags\TYPE_STRING => 'TYPE_STRING',
            flags\TYPE_ARRAY => 'TYPE_ARRAY',
            flags\TYPE_OBJECT => 'TYPE_OBJECT',
            flags\TYPE_CALLABLE => 'TYPE_CALLABLE',
            flags\TYPE_VOID => 'TYPE_VOID',
            flags\TYPE_ITERABLE => 'TYPE_ITERABLE',
        ];
        $useTypes = [
            flags\USE_NORMAL => 'USE_NORMAL',
            flags\USE_FUNCTION => 'USE_FUNCTION',
            flags\USE_CONST => 'USE_CONST',
        ];
        $sharedBinaryOps = [
            flags\BINARY_BITWISE_OR => 'BINARY_BITWISE_OR',
            flags\BINARY_BITWISE_AND => 'BINARY_BITWISE_AND',
            flags\BINARY_BITWISE_XOR => 'BINARY_BITWISE_XOR',
            flags\BINARY_CONCAT => 'BINARY_CONCAT',
            flags\BINARY_ADD => 'BINARY_ADD',
            flags\BINARY_SUB => 'BINARY_SUB',
            flags\BINARY_MUL => 'BINARY_MUL',
            flags\BINARY_DIV => 'BINARY_DIV',
            flags\BINARY_MOD => 'BINARY_MOD',
            flags\BINARY_POW => 'BINARY_POW',
            flags\BINARY_SHIFT_LEFT => 'BINARY_SHIFT_LEFT',
            flags\BINARY_SHIFT_RIGHT => 'BINARY_SHIFT_RIGHT',
        ];

        $exclusive = [
            \ast\AST_NAME => [
                flags\NAME_FQ => 'NAME_FQ',
                flags\NAME_NOT_FQ => 'NAME_NOT_FQ',
                flags\NAME_RELATIVE => 'NAME_RELATIVE',
            ],
            \ast\AST_CLASS => [
                flags\CLASS_ABSTRACT => 'CLASS_ABSTRACT',
                flags\CLASS_FINAL => 'CLASS_FINAL',
                flags\CLASS_TRAIT => 'CLASS_TRAIT',
                flags\CLASS_INTERFACE => 'CLASS_INTERFACE',
                flags\CLASS_ANONYMOUS => 'CLASS_ANONYMOUS',
            ],
            \ast\AST_PARAM => [
                flags\PARAM_REF => 'PARAM_REF',
                flags\PARAM_VARIADIC => 'PARAM_VARIADIC',
            ],
            \ast\AST_TYPE => $types,
            \ast\AST_CAST => $types,
            \ast\AST_UNARY_OP => [
                flags\UNARY_BOOL_NOT => 'UNARY_BOOL_NOT',
                flags\UNARY_BITWISE_NOT => 'UNARY_BITWISE_NOT',
                flags\UNARY_MINUS => 'UNARY_MINUS',
                flags\UNARY_PLUS => 'UNARY_PLUS',
                flags\UNARY_SILENCE => 'UNARY_SILENCE',
            ],
            \ast\AST_BINARY_OP => $sharedBinaryOps + [
                flags\BINARY_BOOL_AND => 'BINARY_BOOL_AND',
                flags\BINARY_BOOL_OR => 'BINARY_BOOL_OR',
                flags\BINARY_BOOL_XOR => 'BINARY_BOOL_XOR',
                flags\BINARY_IS_IDENTICAL => 'BINARY_IS_IDENTICAL',
                flags\BINARY_IS_NOT_IDENTICAL => 'BINARY_IS_NOT_IDENTICAL',
                flags\BINARY_IS_EQUAL => 'BINARY_IS_EQUAL',
                flags\BINARY_IS_NOT_EQUAL => 'BINARY_IS_NOT_EQUAL',
                flags\BINARY_IS_SMALLER => 'BINARY_IS_SMALLER',
                flags\BINARY_IS_SMALLER_OR_EQUAL => 'BINARY_IS_SMALLER_OR_EQUAL',
                flags\BINARY_IS_GREATER => 'BINARY_IS_GREATER',
                flags\BINARY_IS_GREATER_OR_EQUAL => 'BINARY_IS_GREATER_OR_EQUAL',
                flags\BINARY_SPACESHIP => 'BINARY_SPACESHIP',
                flags\BINARY_COALESCE => 'BINARY_COALESCE',
            ],
            \ast\AST_ASSIGN_OP => $sharedBinaryOps + [
                // Old version 10 flags
                flags\ASSIGN_BITWISE_OR => 'ASSIGN_BITWISE_OR',
                flags\ASSIGN_BITWISE_AND => 'ASSIGN_BITWISE_AND',
                flags\ASSIGN_BITWISE_XOR => 'ASSIGN_BITWISE_XOR',
                flags\ASSIGN_CONCAT => 'ASSIGN_CONCAT',
                flags\ASSIGN_ADD => 'ASSIGN_ADD',
                flags\ASSIGN_SUB => 'ASSIGN_SUB',
                flags\ASSIGN_MUL => 'ASSIGN_MUL',
                flags\ASSIGN_DIV => 'ASSIGN_DIV',
                flags\ASSIGN_MOD => 'ASSIGN_MOD',
                flags\ASSIGN_POW => 'ASSIGN_POW',
                flags\ASSIGN_SHIFT_LEFT => 'ASSIGN_SHIFT_LEFT',
                flags\ASSIGN_SHIFT_RIGHT => 'ASSIGN_SHIFT_RIGHT',
            ],
            \ast\AST_MAGIC_CONST => [
                flags\MAGIC_LINE => 'MAGIC_LINE',
                flags\MAGIC_FILE => 'MAGIC_FILE',
                flags\MAGIC_DIR => 'MAGIC_DIR',
                flags\MAGIC_NAMESPACE => 'MAGIC_NAMESPACE',
                flags\MAGIC_FUNCTION => 'MAGIC_FUNCTION',
                flags\MAGIC_METHOD => 'MAGIC_METHOD',
                flags\MAGIC_CLASS => 'MAGIC_CLASS',
                flags\MAGIC_TRAIT => 'MAGIC_TRAIT',
            ],
            \ast\AST_USE => $useTypes,
            \ast\AST_GROUP_USE => $useTypes,
            \ast\AST_USE_ELEM => $useTypes,
            \ast\AST_INCLUDE_OR_EVAL => [
                flags\EXEC_EVAL => 'EXEC_EVAL',
                flags\EXEC_INCLUDE => 'EXEC_INCLUDE',
                flags\EXEC_INCLUDE_ONCE => 'EXEC_INCLUDE_ONCE',
                flags\EXEC_REQUIRE => 'EXEC_REQUIRE',
                flags\EXEC_REQUIRE_ONCE => 'EXEC_REQUIRE_ONCE',
            ],
            \ast\AST_ARRAY => [
                flags\ARRAY_SYNTAX_LIST => 'ARRAY_SYNTAX_LIST',
                flags\ARRAY_SYNTAX_LONG => 'ARRAY_SYNTAX_LONG',
                flags\ARRAY_SYNTAX_SHORT => 'ARRAY_SYNTAX_SHORT',
            ],
            \ast\AST_CLOSURE_VAR => [
                flags\CLOSURE_USE_REF => 'CLOSURE_USE_REF',
            ]
        ];

        $combinable = [];
        $combinable[\ast\AST_METHOD] = $combinable[\ast\AST_FUNC_DECL] = $combinable[\ast\AST_CLOSURE]
            = $combinable[\ast\AST_PROP_DECL] = $combinable[\ast\AST_CLASS_CONST_DECL]
            = $combinable[\ast\AST_TRAIT_ALIAS] = $modifiers;

        return [$exclusive, $combinable];
    }
}
