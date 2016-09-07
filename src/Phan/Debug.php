<?php declare(strict_types=1);
namespace Phan;

use ast\Node;

/**
 * Debug utilities
 */
class Debug
{

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
     * @return null
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
        if (is_string($node)) {
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

        if (is_string($node)) {
            return $string . $node . "\n";
        }

        if (!$node) {
            return $string . 'null' . "\n";
        }

        if (!is_object($node)) {
            return $string . $node . "\n";
        }

        $string .= \ast\get_kind_name($node->kind);

        $string .= ' ['
            . self::astFlagDescription($node->flags ?? 0)
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
     */
    public static function astFlagDescription(int $flag) : string
    {
        $flag_names = [];
        foreach (self::$AST_FLAG_ID_NAME_MAP as $id => $name) {
            if ($flag == $id) {
                $flag_names[] = $name;
            }
        }

        return implode('|', $flag_names);
    }

    /**
     * @return string
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
     * Note that flag IDs are not unique. You're likely going to get
     * an incorrect name back from this. So sorry.
     *
     * @suppress PhanUnreferencedProperty
     */
    private static $AST_FLAG_ID_NAME_MAP = [
        \ast\flags\ASSIGN_ADD => 'ASSIGN_ADD',
        \ast\flags\ASSIGN_BITWISE_AND => 'ASSIGN_BITWISE_AND',
        \ast\flags\ASSIGN_BITWISE_OR => 'ASSIGN_BITWISE_OR',
        \ast\flags\ASSIGN_BITWISE_XOR => 'ASSIGN_BITWISE_XOR',
        \ast\flags\ASSIGN_CONCAT => 'ASSIGN_CONCAT',
        \ast\flags\ASSIGN_DIV => 'ASSIGN_DIV',
        \ast\flags\ASSIGN_MOD => 'ASSIGN_MOD',
        \ast\flags\ASSIGN_MUL => 'ASSIGN_MUL',
        \ast\flags\ASSIGN_POW => 'ASSIGN_POW',
        \ast\flags\ASSIGN_SHIFT_LEFT => 'ASSIGN_SHIFT_LEFT',
        \ast\flags\ASSIGN_SHIFT_RIGHT => 'ASSIGN_SHIFT_RIGHT',
        \ast\flags\ASSIGN_SUB => 'ASSIGN_SUB',
        \ast\flags\BINARY_ADD => 'BINARY_ADD',
        \ast\flags\BINARY_BITWISE_AND => 'BINARY_BITWISE_AND',
        \ast\flags\BINARY_BITWISE_OR => 'BINARY_BITWISE_OR',
        \ast\flags\BINARY_BITWISE_XOR => 'BINARY_BITWISE_XOR',
        \ast\flags\BINARY_BOOL_XOR => 'BINARY_BOOL_XOR',
        \ast\flags\BINARY_CONCAT => 'BINARY_CONCAT',
        \ast\flags\BINARY_DIV => 'BINARY_DIV',
        \ast\flags\BINARY_IS_EQUAL => 'BINARY_IS_EQUAL',
        \ast\flags\BINARY_IS_IDENTICAL => 'BINARY_IS_IDENTICAL',
        \ast\flags\BINARY_IS_NOT_EQUAL => 'BINARY_IS_NOT_EQUAL',
        \ast\flags\BINARY_IS_NOT_IDENTICAL => 'BINARY_IS_NOT_IDENTICAL',
        \ast\flags\BINARY_IS_SMALLER => 'BINARY_IS_SMALLER',
        \ast\flags\BINARY_IS_SMALLER_OR_EQUAL => 'BINARY_IS_SMALLER_OR_EQUAL',
        \ast\flags\BINARY_MOD => 'BINARY_MOD',
        \ast\flags\BINARY_MUL => 'BINARY_MUL',
        \ast\flags\BINARY_POW => 'BINARY_POW',
        \ast\flags\BINARY_SHIFT_LEFT => 'BINARY_SHIFT_LEFT',
        \ast\flags\BINARY_SHIFT_RIGHT => 'BINARY_SHIFT_RIGHT',
        \ast\flags\BINARY_SPACESHIP => 'BINARY_SPACESHIP',
        \ast\flags\BINARY_SUB => 'BINARY_SUB',
        \ast\flags\CLASS_ABSTRACT => 'CLASS_ABSTRACT',
        \ast\flags\CLASS_FINAL => 'CLASS_FINAL',
        \ast\flags\CLASS_INTERFACE => 'CLASS_INTERFACE',
        \ast\flags\CLASS_TRAIT => 'CLASS_TRAIT',
        \ast\flags\MODIFIER_ABSTRACT => 'MODIFIER_ABSTRACT',
        \ast\flags\MODIFIER_FINAL => 'MODIFIER_FINAL',
        \ast\flags\MODIFIER_PRIVATE => 'MODIFIER_PRIVATE',
        \ast\flags\MODIFIER_PROTECTED => 'MODIFIER_PROTECTED',
        \ast\flags\MODIFIER_PUBLIC => 'MODIFIER_PUBLIC',
        \ast\flags\MODIFIER_STATIC => 'MODIFIER_STATIC',
        \ast\flags\NAME_FQ => 'NAME_FQ',
        \ast\flags\NAME_NOT_FQ => 'NAME_NOT_FQ',
        \ast\flags\NAME_RELATIVE => 'NAME_RELATIVE',
        \ast\flags\PARAM_REF => 'PARAM_REF',
        \ast\flags\PARAM_VARIADIC => 'PARAM_VARIADIC',
        \ast\flags\RETURNS_REF => 'RETURNS_REF',
        \ast\flags\TYPE_ARRAY => 'TYPE_ARRAY',
        \ast\flags\TYPE_BOOL => 'TYPE_BOOL',
        \ast\flags\TYPE_CALLABLE => 'TYPE_CALLABLE',
        \ast\flags\TYPE_DOUBLE => 'TYPE_DOUBLE',
        \ast\flags\TYPE_LONG => 'TYPE_LONG',
        \ast\flags\TYPE_NULL => 'TYPE_NULL',
        \ast\flags\TYPE_OBJECT => 'TYPE_OBJECT',
        \ast\flags\TYPE_STRING => 'TYPE_STRING',
        \ast\flags\UNARY_BITWISE_NOT => 'UNARY_BITWISE_NOT',
        \ast\flags\UNARY_BOOL_NOT => 'UNARY_BOOL_NOT',
        \ast\flags\BINARY_BOOL_AND => 'BINARY_BOOL_AND',
        \ast\flags\BINARY_BOOL_OR => 'BINARY_BOOL_OR',
        \ast\flags\BINARY_IS_GREATER => 'BINARY_IS_GREATER',
        \ast\flags\BINARY_IS_GREATER_OR_EQUAL => 'BINARY_IS_GREATER_OR_EQUAL',
        \ast\flags\CLASS_ANONYMOUS => 'CLASS_ANONYMOUS',
        \ast\flags\EXEC_EVAL => 'EXEC_EVAL',
        \ast\flags\EXEC_INCLUDE => 'EXEC_INCLUDE',
        \ast\flags\EXEC_INCLUDE_ONCE => 'EXEC_INCLUDE_ONCE',
        \ast\flags\EXEC_REQUIRE => 'EXEC_REQUIRE',
        \ast\flags\EXEC_REQUIRE_ONCE => 'EXEC_REQUIRE_ONCE',
        \ast\flags\MAGIC_CLASS => 'MAGIC_CLASS',
        \ast\flags\MAGIC_DIR => 'MAGIC_DIR',
        \ast\flags\MAGIC_FILE => 'MAGIC_FILE',
        \ast\flags\MAGIC_FUNCTION => 'MAGIC_FUNCTION',
        \ast\flags\MAGIC_LINE => 'MAGIC_LINE',
        \ast\flags\MAGIC_METHOD => 'MAGIC_METHOD',
        \ast\flags\MAGIC_NAMESPACE => 'MAGIC_NAMESPACE',
        \ast\flags\MAGIC_TRAIT => 'MAGIC_TRAIT',
        \ast\flags\UNARY_MINUS => 'UNARY_MINUS',
        \ast\flags\UNARY_PLUS => 'UNARY_PLUS',
        \ast\flags\UNARY_SILENCE => 'UNARY_SILENCE',
        \ast\flags\USE_CONST => 'USE_CONST',
        \ast\flags\USE_FUNCTION => 'USE_FUNCTION',
        \ast\flags\USE_NORMAL => 'USE_NORMAL',
    ];
}
