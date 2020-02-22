<?php

declare(strict_types=1);

namespace Phan;

use ast;
use ast\flags;
use ast\Node;
use LogicException;
use Phan\Analysis\BlockExitStatusChecker;
use Phan\AST\Parser;
use Phan\AST\TolerantASTConverter\Shim;

// Provides AST_ARROW_FUNC and other new node kinds that aren't in php-ast 1.0.1
Shim::load();

/**
 * Debug utilities
 *
 * Mostly utilities for printing representations of AST nodes.
 * Also see `Debug/`
 */
class Debug
{
    // option for self::astDump
    public const AST_DUMP_LINENOS = 1;

    /**
     * Print a lil' something to the console to
     * see if a thing is called
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public static function mark(): void
    {
        print "mark\n";
    }

    /**
     * Print an AST node
     *
     * @param string|int|float|Node|null $node
     * An AST node
     * @suppress PhanUnreferencedPublicMethod
     */
    public static function printNode($node): void
    {
        print self::nodeToString($node);
    }

    /**
     * Print the name of a node to the terminal
     *
     * @suppress PhanUnreferencedPublicMethod
     * @param Node|string|null $node
     * @param int $indent
     */
    public static function printNodeName($node, int $indent = 0): void
    {
        print \str_repeat("\t", $indent);
        print self::nodeName($node);
        print "\n";
    }

    /**
     * Print $message with the given indent level
     * @suppress PhanUnreferencedPublicMethod
     */
    public static function print(string $message, int $indent = 0): void
    {
        print \str_repeat("\t", $indent);
        print $message . "\n";
    }

    /**
     * Return the name of a node
     *
     * @param Node|string|null $node
     * @return string The name of the node
     */
    public static function nodeName($node): string
    {
        if (\is_string($node)) {
            return "string";
        }

        if (!$node) {
            return 'null';
        }

        $kind = $node->kind;
        if (\is_string($kind)) {
            // For placeholders created by tolerant-php-parser-to-php-ast
            return "string ($kind)";
        }
        try {
            return Parser::getKindName($kind);
        } catch (LogicException $_) {
            return "UNKNOWN_KIND($kind)";
        }
    }

    /**
     * Convert an AST node to a compact string representation of that node.
     *
     * @param string|int|float|Node|null $node
     * An AST node
     *
     * @param int|float|string|null $name
     * The name of the node (if this node has a parent)
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
    ): string {
        $string = \str_repeat("\t", $indent);

        if ($name !== null) {
            $string .= "$name => ";
        }

        if (\is_string($node)) {
            return $string . $node . "\n";
        }

        if ($node === null) {
            return $string . 'null' . "\n";
        }

        if (!\is_object($node)) {
            return $string . (\is_array($node) ? \json_encode($node) : $node) . "\n";
        }
        $kind = $node->kind;

        $string .= self::nodeName($node);

        $string .= ' ['
            . (\is_int($kind) ? self::astFlagDescription($node->flags ?? 0, $kind) : 'unknown')
            . ']';

        if (isset($node->lineno)) {
            $string .= ' #' . $node->lineno;
        }

        $end_lineno = $node->endLineno ?? null;
        if (!\is_null($end_lineno)) {
            $string .= ':' . $end_lineno;
        }

        $string .= "\n";

        foreach ($node->children as $name => $child_node) {
            if (\is_string($name) && \strncmp($name, 'phan', 4) === 0) {
                // Dynamic property added by Phan
                continue;
            }
            $string .= self::nodeToString(
                $child_node,
                $name,
                $indent + 1
            );
        }

        return $string;
    }

    /**
     * Computes a string representation of AST node flags such as
     * 'ASSIGN_DIV|TYPE_ARRAY'
     * @see self::formatFlags() for a similar function also printing the integer flag value.
     */
    public static function astFlagDescription(int $flags, int $kind): string
    {
        [$exclusive, $combinable] = self::getFlagInfo();
        $flag_names = [];
        if (isset($exclusive[$kind])) {
            $flag_info = $exclusive[$kind];
            if (isset($flag_info[$flags])) {
                $flag_names[] = $flag_info[$flags];
            }
        } elseif (isset($combinable[$kind])) {
            $flag_info = $combinable[$kind];
            foreach ($flag_info as $flag => $name) {
                if ($flags & $flag) {
                    $flag_names[] = $name;
                }
            }
        }

        return \implode('|', $flag_names);
    }

    /**
     * @return string
     * Get a string representation of AST node flags such as
     * 'ASSIGN_DIV (26)'
     * Source: https://github.com/nikic/php-ast/blob/master/util.php
     */
    public static function formatFlags(int $kind, int $flags): string
    {
        [$exclusive, $combinable] = self::getFlagInfo();
        if (isset($exclusive[$kind])) {
            $flag_info = $exclusive[$kind];
            if (isset($flag_info[$flags])) {
                return "{$flag_info[$flags]} ($flags)";
            }
        } elseif (isset($combinable[$kind])) {
            $flag_info = $combinable[$kind];
            $names = [];
            foreach ($flag_info as $flag => $name) {
                if ($flags & $flag) {
                    $names[] = $name;
                }
            }
            if (\count($names) > 0) {
                return \implode(" | ", $names) . " ($flags)";
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
    public static function backtrace(int $levels = 0): void
    {
        $bt = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, $levels + 1);
        foreach ($bt as $level => $context) {
            if (!$level) {
                continue;
            }
            $file = $context['file'] ?? 'unknown';
            $line = $context['line'] ?? 1;
            $class = $context['class'] ?? 'global';
            $function = $context['function'] ?? '';

            echo "#" . ($level - 1) . " $file:$line $class ";
            if (isset($context['type'])) {
                echo $context['class'] . $context['type'];
            }
            echo $function;
            echo "\n";
        }
    }

    /**
     * Dumps abstract syntax tree
     * Source: https://github.com/nikic/php-ast/blob/master/util.php
     * @param Node|string|int|float|null $ast
     * @param int $options (self::AST_DUMP_*)
     */
    public static function astDump($ast, int $options = 0): string
    {
        if ($ast instanceof Node) {
            $result = Parser::getKindName($ast->kind);

            if ($options & self::AST_DUMP_LINENOS) {
                $result .= " @ $ast->lineno";
                $end_lineno = $ast->endLineno ?? null;
                if (!\is_null($end_lineno)) {
                    $result .= "-$end_lineno";
                }
            }

            if (ast\kind_uses_flags($ast->kind)) {
                $flags_without_phan_additions = $ast->flags & ~BlockExitStatusChecker::STATUS_BITMASK;
                if ($flags_without_phan_additions !== 0) {
                    $result .= "\n    flags: " . self::formatFlags($ast->kind, $flags_without_phan_additions);
                }
            }
            foreach ($ast->children as $i => $child) {
                $result .= "\n    $i: " . \str_replace("\n", "\n    ", self::astDump($child, $options));
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
     *
     * Returns the information necessary to map the node id to the flag id to the name.
     *
     * @return array{0:associative-array<int,array<int,string>>,1:associative-array<int,array<int,string>>}
     * Returns [string[][] $exclusive, string[][] $combinable].
     */
    private static function getFlagInfo(): array
    {
        // TODO: Use AST's built in flag info if available.
        static $exclusive, $combinable;
        // Write this in a way that lets Phan infer the value of $combinable at the end.
        if ($exclusive === null) {
            $function_modifiers = [
                flags\MODIFIER_PUBLIC => 'MODIFIER_PUBLIC',
                flags\MODIFIER_PROTECTED => 'MODIFIER_PROTECTED',
                flags\MODIFIER_PRIVATE => 'MODIFIER_PRIVATE',
                flags\MODIFIER_STATIC => 'MODIFIER_STATIC',
                flags\MODIFIER_ABSTRACT => 'MODIFIER_ABSTRACT',
                flags\MODIFIER_FINAL => 'MODIFIER_FINAL',
                flags\FUNC_RETURNS_REF => 'FUNC_RETURNS_REF',
                flags\FUNC_GENERATOR => 'FUNC_GENERATOR',
            ];
            $property_modifiers = [
                flags\MODIFIER_PUBLIC => 'MODIFIER_PUBLIC',
                flags\MODIFIER_PROTECTED => 'MODIFIER_PROTECTED',
                flags\MODIFIER_PRIVATE => 'MODIFIER_PRIVATE',
                flags\MODIFIER_STATIC => 'MODIFIER_STATIC',
                flags\MODIFIER_ABSTRACT => 'MODIFIER_ABSTRACT',
                flags\MODIFIER_FINAL => 'MODIFIER_FINAL',
            ];
            $types = [
                flags\TYPE_NULL => 'TYPE_NULL',
                flags\TYPE_FALSE => 'TYPE_FALSE',
                flags\TYPE_BOOL => 'TYPE_BOOL',
                flags\TYPE_LONG => 'TYPE_LONG',
                flags\TYPE_DOUBLE => 'TYPE_DOUBLE',
                flags\TYPE_STRING => 'TYPE_STRING',
                flags\TYPE_ARRAY => 'TYPE_ARRAY',
                flags\TYPE_OBJECT => 'TYPE_OBJECT',
                flags\TYPE_CALLABLE => 'TYPE_CALLABLE',
                flags\TYPE_VOID => 'TYPE_VOID',
                flags\TYPE_ITERABLE => 'TYPE_ITERABLE',
                flags\TYPE_STATIC => 'TYPE_STATIC',
            ];
            $use_types = [
                flags\USE_NORMAL => 'USE_NORMAL',
                flags\USE_FUNCTION => 'USE_FUNCTION',
                flags\USE_CONST => 'USE_CONST',
            ];
            $shared_binary_ops = [
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
                flags\BINARY_COALESCE => 'BINARY_COALESCE',
            ];

            $exclusive = [
                ast\AST_NAME => [
                    flags\NAME_FQ => 'NAME_FQ',
                    flags\NAME_NOT_FQ => 'NAME_NOT_FQ',
                    flags\NAME_RELATIVE => 'NAME_RELATIVE',
                ],
                ast\AST_CLASS => [
                    flags\CLASS_ABSTRACT => 'CLASS_ABSTRACT',
                    flags\CLASS_FINAL => 'CLASS_FINAL',
                    flags\CLASS_TRAIT => 'CLASS_TRAIT',
                    flags\CLASS_INTERFACE => 'CLASS_INTERFACE',
                    flags\CLASS_ANONYMOUS => 'CLASS_ANONYMOUS',
                ],
                ast\AST_TYPE => $types,
                ast\AST_CAST => $types,
                ast\AST_UNARY_OP => [
                    flags\UNARY_BOOL_NOT => 'UNARY_BOOL_NOT',
                    flags\UNARY_BITWISE_NOT => 'UNARY_BITWISE_NOT',
                    flags\UNARY_MINUS => 'UNARY_MINUS',
                    flags\UNARY_PLUS => 'UNARY_PLUS',
                    flags\UNARY_SILENCE => 'UNARY_SILENCE',
                ],
                ast\AST_BINARY_OP => $shared_binary_ops + [
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
                ],
                ast\AST_ASSIGN_OP => $shared_binary_ops,
                ast\AST_MAGIC_CONST => [
                    flags\MAGIC_LINE => 'MAGIC_LINE',
                    flags\MAGIC_FILE => 'MAGIC_FILE',
                    flags\MAGIC_DIR => 'MAGIC_DIR',
                    flags\MAGIC_NAMESPACE => 'MAGIC_NAMESPACE',
                    flags\MAGIC_FUNCTION => 'MAGIC_FUNCTION',
                    flags\MAGIC_METHOD => 'MAGIC_METHOD',
                    flags\MAGIC_CLASS => 'MAGIC_CLASS',
                    flags\MAGIC_TRAIT => 'MAGIC_TRAIT',
                ],
                ast\AST_USE => $use_types,
                ast\AST_GROUP_USE => $use_types,
                ast\AST_USE_ELEM => $use_types,
                ast\AST_INCLUDE_OR_EVAL => [
                    flags\EXEC_EVAL => 'EXEC_EVAL',
                    flags\EXEC_INCLUDE => 'EXEC_INCLUDE',
                    flags\EXEC_INCLUDE_ONCE => 'EXEC_INCLUDE_ONCE',
                    flags\EXEC_REQUIRE => 'EXEC_REQUIRE',
                    flags\EXEC_REQUIRE_ONCE => 'EXEC_REQUIRE_ONCE',
                ],
                ast\AST_ARRAY => [
                    flags\ARRAY_SYNTAX_LIST => 'ARRAY_SYNTAX_LIST',
                    flags\ARRAY_SYNTAX_LONG => 'ARRAY_SYNTAX_LONG',
                    flags\ARRAY_SYNTAX_SHORT => 'ARRAY_SYNTAX_SHORT',
                ],
                ast\AST_ARRAY_ELEM => [
                    flags\ARRAY_ELEM_REF => 'ARRAY_ELEM_REF',
                ],
                ast\AST_CLOSURE_VAR => [
                    flags\CLOSURE_USE_REF => 'CLOSURE_USE_REF',
                ],
            ];

            $combinable = [
                ast\AST_METHOD => $function_modifiers,
                ast\AST_FUNC_DECL => $function_modifiers,
                ast\AST_CLOSURE => $function_modifiers,
                ast\AST_ARROW_FUNC => $function_modifiers,
                ast\AST_CLASS_CONST_DECL => [
                    flags\MODIFIER_PUBLIC => 'MODIFIER_PUBLIC',
                    flags\MODIFIER_PROTECTED => 'MODIFIER_PROTECTED',
                    flags\MODIFIER_PRIVATE => 'MODIFIER_PRIVATE',
                ],
                ast\AST_PROP_GROUP => $property_modifiers,
                ast\AST_TRAIT_ALIAS => $property_modifiers,
                ast\AST_DIM => [
                    flags\DIM_ALTERNATIVE_SYNTAX => 'DIM_ALTERNATIVE_SYNTAX',
                ],
                ast\AST_CONDITIONAL => [
                    flags\PARENTHESIZED_CONDITIONAL => 'PARENTHESIZED_CONDITIONAL',
                ],
                ast\AST_PARAM => [
                    flags\PARAM_REF => 'PARAM_REF',
                    flags\PARAM_VARIADIC => 'PARAM_VARIADIC',
                ],
            ];
        }

        return [$exclusive, $combinable];
    }

    /**
     * Print a message with the file and line.
     * @suppress PhanUnreferencedPublicMethod added for debugging
     */
    public static function debugLog(string $message): void
    {
        $frame = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS)[0];
        \fprintf(\STDERR, "%s:%d %s\n", $frame['file'] ?? 'unknown', $frame['line'] ?? 0, $message);
    }
}
