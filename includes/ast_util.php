<?php
namespace phan;

// ast_node_type() is for places where an actual type name appears. This returns that type name
// node_type() instead to figure out the type of a node
function ast_node_type($file, $node, $namespace) {
	global $namespace_map;

	if($node instanceof \ast\Node) {
		switch($node->kind) {
			case \ast\AST_NAME:
				$result = qualified_name($file, $node, $namespace);
				break;
			case \ast\AST_TYPE:
				if($node->flags == \ast\flags\TYPE_CALLABLE) $result = 'callable';
				else if($node->flags == \ast\flags\TYPE_ARRAY) $result = 'array';
				else assert(false, "Unknown type: {$node->flags}");
				break;
			default:
				Log::err(Log::EFATAL, "ast_node_type: unknown node type: ".\ast\get_kind_name($node->kind));
				break;
		}
	} else {
		$result = (string)$node;
	}
	return $result;
}

function ast_get_flag_info() : array {
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
    ];
    $useTypes = [
        T_CLASS => 'T_CLASS',
        T_FUNCTION => 'T_FUNCTION',
        T_CONST => 'T_CONST',
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
        ],
        \ast\AST_BINARY_OP => [
            \ast\flags\BINARY_BOOL_XOR => 'BINARY_BOOL_XOR',
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
            \ast\flags\BINARY_IS_IDENTICAL => 'BINARY_IS_IDENTICAL',
            \ast\flags\BINARY_IS_NOT_IDENTICAL => 'BINARY_IS_NOT_IDENTICAL',
            \ast\flags\BINARY_IS_EQUAL => 'BINARY_IS_EQUAL',
            \ast\flags\BINARY_IS_NOT_EQUAL => 'BINARY_IS_NOT_EQUAL',
            \ast\flags\BINARY_IS_SMALLER => 'BINARY_IS_SMALLER',
            \ast\flags\BINARY_IS_SMALLER_OR_EQUAL => 'BINARY_IS_SMALLER_OR_EQUAL',
            \ast\flags\BINARY_SPACESHIP => 'BINARY_SPACESHIP',
        ],
        \ast\AST_ASSIGN_OP => [
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
            T_LINE => 'T_LINE',
            T_FILE => 'T_FILE',
            T_DIR => 'T_DIR',
            T_TRAIT_C => 'T_TRAIT_C',
            T_METHOD_C => 'T_METHOD_C',
            T_FUNC_C => 'T_FUNC_C',
            T_NS_C => 'T_NS_C',
            T_CLASS_C => 'T_CLASS_C',
        ],
        \ast\AST_USE => $useTypes,
        \ast\AST_GROUP_USE => $useTypes,
        \ast\AST_USE_ELEM => $useTypes,
    ];

    $combinable = [];
    $combinable[\ast\AST_METHOD] = $combinable[\ast\AST_FUNC_DECL] = $combinable[\ast\AST_CLOSURE]
        = $combinable[\ast\AST_PROP_DECL] = $combinable[\ast\AST_TRAIT_ALIAS] = $modifiers;

    return [$exclusive, $combinable];
}

function ast_format_flags(int $kind, int $flags) : string {
    list($exclusive, $combinable) = ast_get_flag_info();
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
    return $flags;
}

/** Dumps abstract syntax tree - stolen from @nikic */
function ast_dump($ast, $children=true) : string {
	if ($ast instanceof \ast\Node) {
		$result = \ast\get_kind_name($ast->kind);

		$result .= " @ $ast->lineno";
		if (isset($ast->endLineno)) {
			$result .= "-$ast->endLineno";
		}

		if (\ast\kind_uses_flags($ast->kind)) {
			$result .= "\n    flags: " . ast_format_flags($ast->kind, $ast->flags);
		}
		if (isset($ast->name)) {
			$result .= "\n    name: $ast->name";
		}
		if (isset($ast->docComment)) {
			$result .= "\n    docComment: $ast->docComment";
		}
		if($children) foreach ($ast->children as $i => $child) {
			$result .= "\n    $i: " . str_replace("\n", "\n    ", ast_dump($child, $children));
		}
		return $result;
	} else if ($ast === null) {
		return 'null';
	} else if (is_string($ast)) {
		return "\"$ast\"";
	} else {
		return (string)$ast;
	}
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
