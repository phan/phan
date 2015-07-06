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

// Debug function for dumping an AST node
function ast_dump($ast, $children=true) {
	if ($ast instanceof \ast\Node) {
		$result = \ast\get_kind_name($ast->kind);
		$result .= " @ $ast->lineno";
		if (isset($ast->endLineno)) {
			$result .= "-$ast->endLineno";
		}
		if (\ast\kind_uses_flags($ast->kind)) {
			$result .= "\n    flags: $ast->flags";
		}
		if (isset($ast->name)) {
			$result .= "\n    name: $ast->name";
		}
		if (isset($ast->docComment)) {
			$result .= "\n    docComment: $ast->docComment";
		}
		if($children) foreach ($ast->children as $i => $child) {
			$result .= "\n    $i: " . str_replace("\n", "\n    ", ast_dump($child));
		}
		return $result;
	} else if ($ast === null) {
		return 'null';
	} else if (is_string($ast)) {
		return "\"$ast\"";
	} else {
		return (string) $ast;
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
