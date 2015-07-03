<?php
namespace phan;

// Pass 1 recursively finds all the class and function declarations and populates the appropriate globals
function pass1($file, $conditional, $ast, $current_scope, $current_class=null, $current_function=null) {
	global $classes, $functions, $namespace, $summary;
	$done = false;

	if ($ast instanceof \ast\Node) {
		switch($ast->kind) {
			case \ast\AST_NAMESPACE:
				$namespace = (string)$ast->children[0].'\\';
				break;

			case \ast\AST_IF:
				$conditional = true;
				$summary['conditionals']++;
				break;

			case \ast\AST_CLASS:
				if(!empty($classes[strtolower($namespace.$ast->name)])) {
					for($i=1;;$i++) {
						if(empty($classes[$i.":".strtolower($namespace.$ast->name)])) break;
					}
					$current_class = $i.":".$namespace.$ast->name;
				} else {
					$current_class = $namespace.$ast->name;
				}
				if(!empty($ast->children[0])) $parent = $namespace.$ast->children[0]->children[0];
				else $parent = null;
				$classes[strtolower($current_class)] = [
											'file'		 => $file,
											'conditional'=> $conditional,
											'flags'		 => $ast->flags,
											'lineno'	 => $ast->lineno,
											'endLineno'  => $ast->endLineno,
											'name'		 => $namespace.$ast->name,
											'docComment' => $ast->docComment,
											'parent'	 => $parent,
											'properties' => [],
											'constants'  => [],
											'traits'	 => [],
											'interfaces' => [],
											'methods'	 => [] ];
				$classes[strtolower($current_class)]['interfaces'] = array_merge($classes[strtolower($current_class)]['interfaces'], node_namelist($ast->children[1]));
				$summary['classes']++;
				break;

			case \ast\AST_USE_TRAIT:
				$classes[strtolower($current_class)]['traits'] = array_merge($classes[strtolower($current_class)]['traits'], node_namelist($ast->children[0]));
				$summary['traits']++;
				break;

			case \ast\AST_METHOD:
				if(!empty($classes[strtolower($current_class)]['methods'][strtolower($ast->name)])) {
					for($i=1;;$i++) {
						if(empty($classes[strtolower($current_class)]['methods'][$i.':'.strtolower($ast->name)])) break;
					}
					$method = $i.':'.$ast->name;
				} else {
					$method = $ast->name;
				}
				$classes[strtolower($current_class)]['methods'][strtolower($method)] = node_func($file, $conditional, $ast, "{$current_class}::{$method}");
                if(!($classes[strtolower($current_class)]['methods'][strtolower($method)]['flags'] & \ast\flags\MODIFIER_STATIC)) {
                    add_var_scope("{$current_class}::{$method}", 'this', 'object:'.$current_class);
                }

				$summary['methods']++;
				$current_function = $method;
				$current_scope = "{$current_class}::{$method}";
				break;

			case \ast\AST_PROP_DECL:
				if(empty($current_class)) Log::err(Log::EFATAL, "Invalid property declaration", $file, $ast->lineno);

				foreach($ast->children as $node) {
					$classes[strtolower($current_class)]['properties'][strtolower($node->children[0])] = [
																				   'flags'=>$ast->flags,
																				   'name'=>$node->children[0],
																				   'lineno'=>$node->lineno,
																				   'value'=>$node->children[1] ];
				}
				$done = true;
				break;

			case \ast\AST_CLASS_CONST_DECL:
				if(empty($current_class)) Log::err(Log::EFATAL, "Invalid constant declaration", $file, $ast->lineno);

				foreach($ast->children as $node) {
					$classes[strtolower($current_class)]['constants'][strtolower($node->children[0])] = [
																				  'name'=>$node->children[0],
																				  'lineno'=>$node->lineno,
																				  'value'=>$node->children[1] ];
				}
				$done = true;
				break;

			case \ast\AST_FUNC_DECL:
				if(!empty($functions[strtolower($namespace.$ast->name)])) {
					for($i=1;;$i++) {
						if(empty($functions[$i.":".strtolower($namespace.$ast->name)])) break;
					}
					$function = $i.':'.$namespace.$ast->name;
				} else {
					$function = $namespace.$ast->name;
				}
				$functions[strtolower($function)] = node_func($file, $conditional, $ast, $function, $namespace);
				$summary['functions']++;
				$current_function = $function;
				$current_scope = $function;
				// Not $done=true here since nested function declarations are allowed
				break;

			case \ast\AST_CLOSURE:
				$summary['closures']++;
				$current_scope = "{closure}";
				break;

			case \ast\AST_CALL: // Looks odd to check for AST_CALL in pass1, but we need to see if a function calls func_get_arg/func_get_args/func_num_args
				$found = false;
				$call = $ast->children[0];
				if($call->kind == \ast\AST_NAME) {
					$func_name = strtolower($call->children[0]);
					if($func_name == 'func_get_args' || $func_name == 'func_get_arg' || $func_name == 'func_num_args') {
						if(!empty($current_class)) {
							$classes[strtolower($current_class)]['methods'][strtolower($current_function)]['optional'] = 999999;
						} else {
							$functions[strtolower($current_function)]['optional'] = 999999;
						}
					}
				}
                break;
		}
		if(!$done) foreach($ast->children as $child) {
			pass1($file, $conditional, $child, $current_scope, $current_class, $current_function);
		}
	}
}

function node_namelist($node) {
	$result = [];
	if($node instanceof \ast\Node) {
		foreach($node->children as $name_node) {
			$result[] = strtolower((string)$name_node->children[0]);
		}
	}
	return $result;
}

function node_paramlist($file, $node, &$req, &$opt, $dc) {
	if($node instanceof \ast\Node) {
		$result = [];
		$i = 0;
		foreach($node->children as $param_node) {
			$result[] = node_param($file, $param_node, $dc, $i);
			if($param_node->children[2]===null) {
				if($opt) Log::err(Log::EPARAM, "required arg follows optional", $file, $node->lineno);
				$req++;
			} else $opt++;
			$i++;
		}
		return $result;
	}
	assert(false, ast_dump($node)." was not an \\ast\\Node");
}

function node_param($file, $node, $dc, $i) {
	if($node instanceof \ast\Node) {
		$type = ast_node_type($node->children[0]);
		if(empty($type) && !empty($dc['params'][$i]['type'])) $type = $dc['params'][$i]['type'];

		$result = [
					'flags'=>$node->flags,
					'lineno'=>$node->lineno,
					'name'=>(string)$node->children[1],
					'type'=>$type
				  ];
		if($node->children[2]!==null) $result['def'] = $node->children[2];
		return $result;
	}
	assert(false, "$node was not an \\ast\\Node");
}

function node_func($file, $conditional, $node, $current_scope, $namespace='') {
	global $scope;

	if($node instanceof \ast\Node) {
		$req = $opt = 0;
		$dc = ['return'=>'', 'params'=>[]];
		if(!empty($node->docComment)) $dc = parse_doc_comment($node->docComment);
		$result = [
					'file'=>$file,
					'scope'=>$current_scope,
					'conditional'=>$conditional,
					'flags'=>$node->flags,
					'lineno'=>$node->lineno,
					'endLineno'=>$node->endLineno,
					'name'=>$namespace.$node->name,
					'docComment'=>$node->docComment,
					'params'=>node_paramlist($file, $node->children[0], $req, $opt, $dc),
					'required'=>$req,
					'optional'=>$opt,
					'ast'=>$node->children[2]
				  ];
		if($node->children[3] !==null) {
			$result['oret'] = ast_node_type($node->children[3]); // Original return type
			$result['ret'] = ast_node_type($node->children[3]); // This one changes as we walk the tree
		} else {
			// Check if the docComment has a return value specified
			if(!empty($dc['return'])) {
				$result['oret'] = $dc['return'];
				$result['ret'] = $dc['return'];
			}
		}
		// Add params to local scope for user functions
		if($file != 'internal') {
			$i = 1;
			foreach($result['params'] as $k=>$v) {
				if(empty($v['type'])) {
					// If there is no type specified in PHP, check for a docComment
					// We assume order in the docComment matches the parameter order in the code
					if(!empty($dc['params'][$k]['type'])) {
						$scope[$current_scope]['vars'][$v['name']] = ['type'=>$dc['params'][$k]['type'], 'tainted'=>false, 'tainted_by'=>'', 'param'=>$i];
					} else {
						$scope[$current_scope]['vars'][$v['name']] = ['type'=>'', 'tainted'=>false, 'tainted_by'=>'', 'param'=>$i];
					}
				} else {
					$scope[$current_scope]['vars'][$v['name']] = ['type'=>$v['type'], 'tainted'=>false, 'tainted_by'=>'', 'param'=>$i];
				}
				if(array_key_exists('def', $v)) {
					$type = node_type($file, $v['def'], $current_scope);
					if($type==="NULL") {
						add_type($current_scope, $v['name'], $type, $current_scope);
						$result['params'][$k]['type'] .= '|NULL';
					} else {
						if($scope[$current_scope]['vars'][$v['name']]['type'] !== '') {
							// Does the default value match the declared type?
							if(!type_check($type, $scope[$current_scope]['vars'][$v['name']]['type'])) {
								Log::err(Log::ETYPE, "Default value for {$scope[$current_scope]['vars'][$v['name']]['type']} parameter can't be $type", $file, $node->lineno);
							}
						}
					}
				}
				$i++;
			}
		}
		return $result;
	}
	assert(false, "$node was not an \\ast\\Node");
}

// TODO: Make this way smarter
function parse_doc_comment(string $comment):array {
	$lines = explode("\n",$comment);
	$result = ['return'=>'', 'params'=>[]];
	foreach($lines as $line) {
		$line = strtolower($line);
		if(($pos=strpos($line, '@param')) !== false) {
			if(preg_match('/@param\s+(\S+)\s+(\S+)/', $line, $match)) {
				$result['params'][] = ['name'=>trim($match[2],'$'), 'type'=>$match[1]];
			}
		}
		if(($pos=strpos($line, '@return')) !== false) {
			if(preg_match('/@return\s+(\S+)/', $line, $match)) {
				$result['return'] = $match[1];
			}
		}
		// TODO: add support for properties
	}
	return $result;
}
/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
