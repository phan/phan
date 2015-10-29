<?php declare(strict_types=1);
namespace Phan;

use \ast\Node;

/**
 * Debug utilities
 */
class Debug {

    /**
     * Print an AST node
     *
     * @return null
     */
    public static function printNode(Node $node) {
        print self::nodeToString($node);
    }

    /**
     * @param string|Node|null $node
     * An AST node
     *
     * @return string
     * A string representation of an AST node
     */
    public static function nodeToString(
        $node,
        int $indent = 0
    ) : string {
        $string = str_repeat("\t", $indent);

        if (!$node) {
            return $string . 'null' . "\n";
        }

        if (is_string($node)) {
            return $string . $node . "\n";
        }

        $string .= self::$AST_KIND_ID_NAME_MAP[$node->kind];
        $string .= ' [' . self::astFlagDescription($node->flags) . ']';

        if ($node->lineno) {
            $string .= ' #' . $node->lineno;
        }

        if (isset($node->endLineno)) {
            $string .= ':' . $node->endLineno;
        }

        foreach ($node->children as $child_node) {
            $string .= "\n" . self::nodeToString($child_node, $indent + 1);
        }

        $string .= "\n";

        return $string;
    }

    /**
     * @return string
     * Get a string representation of AST node flags such as
     * 'ASSIGN_DIV|TYPE_ARRAY'
     */
    public static function astFlagDescription(int $flag) : string {
        $flag_names = [];
        foreach (self::$AST_FLAG_ID_NAME_MAP as $id => $name) {
            if ($flag & $id) {
                $flag_names[] = $name;
            }
        }

        return implode('|', $flag_names);
    }

    private static $AST_KIND_ID_NAME_MAP = [
        \ast\AST_ARRAY => 'AST_ARRAY',
        \ast\AST_ARRAY_ELEM => 'AST_ARRAY_ELEM',
        \ast\AST_ASSIGN => 'AST_ASSIGN',
        \ast\AST_ASSIGN_OP => 'AST_ASSIGN_OP',
        \ast\AST_ASSIGN_REF => 'AST_ASSIGN_REF',
        \ast\AST_BINARY_OP => 'AST_BINARY_OP',
        \ast\AST_CALL => 'AST_CALL',
        \ast\AST_CAST => 'AST_CAST',
        \ast\AST_CATCH => 'AST_CATCH',
        \ast\AST_CLASS => 'AST_CLASS',
        \ast\AST_CLASS_CONST => 'AST_CLASS_CONST',
        \ast\AST_CLASS_CONST_DECL => 'AST_CLASS_CONST_DECL',
        \ast\AST_CLOSURE => 'AST_CLOSURE',
        \ast\AST_CLOSURE_USES => 'AST_CLOSURE_USES',
        \ast\AST_CLOSURE_VAR => 'AST_CLOSURE_VAR',
        \ast\AST_CONST => 'AST_CONST',
        \ast\AST_DIM => 'AST_DIM',
        \ast\AST_DO_WHILE => 'AST_DO_WHILE',
        \ast\AST_ECHO => 'AST_ECHO',
        \ast\AST_ENCAPS_LIST => 'AST_ENCAPS_LIST',
        \ast\AST_EXPR_LIST => 'AST_EXPR_LIST',
        \ast\AST_FOREACH => 'AST_FOREACH',
        \ast\AST_FUNC_DECL => 'AST_FUNC_DECL',
        \ast\AST_GLOBAL => 'AST_GLOBAL',
        \ast\AST_GREATER => 'AST_GREATER',
        \ast\AST_GREATER_EQUAL => 'AST_GREATER_EQUAL',
        \ast\AST_GROUP_USE => 'AST_GROUP_USE',
        \ast\AST_IF => 'AST_IF',
        \ast\AST_IF_ELEM => 'AST_IF_ELEM',
        \ast\AST_INSTANCEOF => 'AST_INSTANCEOF',
        \ast\AST_LIST => 'AST_LIST',
        \ast\AST_MAGIC_CONST => 'AST_MAGIC_CONST',
        \ast\AST_METHOD => 'AST_METHOD',
        \ast\AST_METHOD_CALL => 'AST_METHOD_CALL',
        \ast\AST_NAME => 'AST_NAME',
        \ast\AST_NAMESPACE => 'AST_NAMESPACE',
        \ast\AST_NEW => 'AST_NEW',
        \ast\AST_PARAM => 'AST_PARAM',
        \ast\AST_PRINT => 'AST_PRINT',
        \ast\AST_PROP => 'AST_PROP',
        \ast\AST_PROP_DECL => 'AST_PROP_DECL',
        \ast\AST_PROP_ELEM => 'AST_PROP_ELEM',
        \ast\AST_RETURN => 'AST_RETURN',
        \ast\AST_STATIC => 'AST_STATIC',
        \ast\AST_STATIC_CALL => 'AST_STATIC_CALL',
        \ast\AST_STATIC_PROP => 'AST_STATIC_PROP',
        \ast\AST_STMT_LIST => 'AST_STMT_LIST',
        \ast\AST_SWITCH => 'AST_SWITCH',
        \ast\AST_SWITCH_CASE => 'AST_SWITCH_CASE',
        \ast\AST_TYPE => 'AST_TYPE',
        \ast\AST_UNARY_OP => 'AST_UNARY_OP',
        \ast\AST_USE => 'AST_USE',
        \ast\AST_USE_ELEM => 'AST_USE_ELEM',
        \ast\AST_USE_TRAIT => 'AST_USE_TRAIT',
        \ast\AST_VAR => 'AST_VAR',
        \ast\AST_WHILE => 'AST_WHILE',
    ];

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
    ];
}

