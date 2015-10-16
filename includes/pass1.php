<?php
namespace phan;

// Pass 1 recursively finds all the class and function declarations and populates the appropriate globals
function pass1($file, $namespace, $conditional, $ast, $current_scope, $current_class=null, $current_function=null) {
	global $classes, $functions, $namespace_map, $summary, $bc_checks;
	$done = false;

	$lc = strtolower((string)$current_class);

	if ($ast instanceof \ast\Node) {
		switch($ast->kind) {
			case \ast\AST_NAMESPACE:
				$namespace = (string)$ast->children[0].'\\';
				break;

			case \ast\AST_IF:
				$conditional = true;
				$summary['conditionals']++;
				break;

			case \ast\AST_DIM:
				if($bc_checks) {
					if($ast->children[0] instanceof \ast\Node && $ast->children[0]->children[0] instanceof \ast\Node) {
						// check for $$var[]
						if($ast->children[0]->kind == \ast\AST_VAR && $ast->children[0]->children[0]->kind == \ast\AST_VAR) {
							$temp = $ast->children[0]->children[0];
							$depth = 1;
							while($temp instanceof \ast\Node) {
								$temp = $temp->children[0];
								$depth++;
							}
							$dollars = str_repeat('$',$depth);
							$ftemp = new \SplFileObject($file);
							$ftemp->seek($ast->lineno-1);
							$line = $ftemp->current();
							unset($ftemp);
							if(strpos($line,'{') === false || strpos($line,'}') === false) {
								Log::err(Log::ECOMPAT, "{$dollars}{$temp}[] expression may not be PHP 7 compatible", $file, $ast->lineno);
							}
						}
						// $foo->$bar['baz'];
						else if(!empty($ast->children[0]->children[1]) && ($ast->children[0]->children[1] instanceof \ast\Node) && ($ast->children[0]->kind == \ast\AST_PROP) &&
								  ($ast->children[0]->children[0]->kind == \ast\AST_VAR) && ($ast->children[0]->children[1]->kind == \ast\AST_VAR)) {
							$ftemp = new \SplFileObject($file);
							$ftemp->seek($ast->lineno-1);
							$line = $ftemp->current();
							unset($ftemp);
							if(strpos($line,'{') === false || strpos($line,'}') === false) {
								Log::err(Log::ECOMPAT, "expression may not be PHP 7 compatible", $file, $ast->lineno);
							}
						}
					}
				}
				break;

			case \ast\AST_USE:
				foreach($ast->children as $elem) {
					$target = $elem->children[0];
					if(empty($elem->children[1])) {
						if(($pos=strrpos($target, '\\'))!==false) {
							$alias = substr($target, $pos + 1);
						} else {
							$alias = $target;
						}
					} else {
						$alias = $elem->children[1];
					}
					$namespace_map[$ast->flags][$file][strtolower($alias)] = $target;
				}
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
				$lc = strtolower($current_class);
				if(!empty($ast->children[0])) {
					$parent = $ast->children[0]->children[0];
					if($ast->children[0]->flags & \ast\flags\NAME_NOT_FQ) {
						if(($pos = strpos($parent,'\\')) !== false) {
							// extends A\B
							// check if we have a namespace alias for A
							if(!empty($namespace_map[T_CLASS][$file][strtolower(substr($parent,0,$pos))])) {
								$parent = $namespace_map[T_CLASS][$file][strtolower(substr($parent,0,$pos))] . substr($parent,$pos);
								goto done;
							}
						}
						$parent = $namespace_map[T_CLASS][$file][strtolower($parent)] ?? $namespace.$parent;
						done:
					}
				} else {
					$parent = null;
				}
				$classes[$lc] = [
									'file'		 => $file,
									'namespace'	 => $namespace,
									'conditional'=> $conditional,
									'flags'		 => $ast->flags,
									'lineno'	 => $ast->lineno,
									'endLineno'  => $ast->endLineno,
									'name'		 => $namespace.$ast->name,
									'docComment' => $ast->docComment,
									'parent'	 => $parent,
									'pc_called'  => true,
									'type'	     => '',
									'properties' => [],
									'constants'  => [],
									'traits'	 => [],
									'interfaces' => [],
									'methods'	 => [] ];

				$classes[$lc]['interfaces'] = array_merge($classes[$lc]['interfaces'], node_namelist($file, $ast->children[1], $namespace));
				$summary['classes']++;
				break;

			case \ast\AST_USE_TRAIT:
				$classes[$lc]['traits'] = array_merge($classes[$lc]['traits'], node_namelist($file, $ast->children[0], $namespace));
				$summary['traits']++;
				break;

			case \ast\AST_METHOD:
				if(!empty($classes[$lc]['methods'][strtolower($ast->name)])) {
					for($i=1;;$i++) {
						if(empty($classes[$lc]['methods'][$i.':'.strtolower($ast->name)])) break;
					}
					$method = $i.':'.$ast->name;
				} else {
					$method = $ast->name;
				}
				$classes[$lc]['methods'][strtolower($method)] = node_func($file, $conditional, $ast, "{$current_class}::{$method}", $current_class, $namespace);
				$summary['methods']++;
				$current_function = $method;
				$current_scope = "{$current_class}::{$method}";
				if($method == '__construct') $classes[$lc]['pc_called'] = false;
				if($method == '__invoke') $classes[$lc]['type'] = merge_type($classes[$lc]['type'], 'callable');
				break;

			case \ast\AST_PROP_DECL:
				if(empty($current_class)) Log::err(Log::EFATAL, "Invalid property declaration", $file, $ast->lineno);
				$dc = null;
				if(!empty($ast->docComment)) $dc = parse_doc_comment($ast->docComment);

				foreach($ast->children as $i=>$node) {
					$classes[$lc]['properties'][$node->children[0]] = [ 'flags'=>$ast->flags,
																	    'name'=>$node->children[0],
																	    'lineno'=>$node->lineno];
					$type = node_type($file, $namespace, $node->children[1], $current_scope, empty($classes[$lc]) ? null : $classes[$lc]);
					if(!empty($dc['vars'][$i]['type'])) {
						if($type !=='null' && !type_check($type, $dc['vars'][$i]['type'])) {
							Log::err(Log::ETYPE, "property is declared to be {$dc['vars'][$i]['type']} but was assigned $type", $file, $node->lineno);
						}
						// Set the declarted type to the doc-comment type and add |null if the default value is null
						$classes[$lc]['properties'][$node->children[0]]['dtype'] = $dc['vars'][$i]['type'] . (($type==='null')?'|null':'');
						$classes[$lc]['properties'][$node->children[0]]['type'] = $dc['vars'][$i]['type'];
						if(!empty($type) && $type != $classes[$lc]['properties'][$node->children[0]]['type']) {
							$classes[$lc]['properties'][$node->children[0]]['type'] = merge_type($classes[$lc]['properties'][$node->children[0]]['type'], strtolower($type));
						}
					} else {
						$classes[$lc]['properties'][$node->children[0]]['dtype'] = '';
						$classes[$lc]['properties'][$node->children[0]]['type'] = $type;
					}
				}
				$done = true;
				break;

			case \ast\AST_CLASS_CONST_DECL:
				if(empty($current_class)) Log::err(Log::EFATAL, "Invalid constant declaration", $file, $ast->lineno);

				foreach($ast->children as $node) {
					$classes[$lc]['constants'][$node->children[0]] = [
					  'name'=>$node->children[0],
					  'lineno'=>$node->lineno,
					  'type'=>node_type($file, $namespace, $node->children[1], $current_scope, empty($classes[$lc]) ? null : $classes[$lc])
					];
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

				$functions[strtolower($function)] = node_func($file, $conditional, $ast, $function, $current_class, $namespace);
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
							$classes[$lc]['methods'][strtolower($current_function)]['optional'] = 999999;
						} else {
							$functions[strtolower($current_function)]['optional'] = 999999;
						}
					}
				}
				if($bc_checks) bc_check($file, $ast);
                break;

			case \ast\AST_STATIC_CALL: // Indicate whether a class calls its parent constructor
				$call = $ast->children[0];
				if($call->kind == \ast\AST_NAME) {
					$func_name = strtolower($call->children[0]);
					if($func_name == 'parent') {
						$meth = strtolower($ast->children[1]);
						if($meth == '__construct') {
							$classes[strtolower($current_class)]['pc_called'] = true;
						}
					}
				}
				break;

			case \ast\AST_RETURN:
			case \ast\AST_PRINT:
			case \ast\AST_ECHO:
			case \ast\AST_STATIC_CALL:
			case \ast\AST_METHOD_CALL:
				if($bc_checks) bc_check($file, $ast);
                break;
		}
		if(!$done) foreach($ast->children as $child) {
			$namespace = pass1($file, $namespace, $conditional, $child, $current_scope, $current_class, $current_function);
		}
	}
	return $namespace;
}

function bc_check($file, $ast) {
	if($ast->children[0] instanceof \ast\Node) {
		if($ast->children[0]->kind == \ast\AST_DIM) {
			$temp = $ast->children[0]->children[0];
			$last = $temp;
			if($temp->kind == \ast\AST_PROP || $temp->kind == \ast\AST_STATIC_PROP) {
				while($temp instanceof \ast\Node && ($temp->kind == \ast\AST_PROP || $temp->kind == \ast\AST_STATIC_PROP)) {
					$last = $temp;
					$temp = $temp->children[0];
				}
				if($temp instanceof \ast\Node) {
					if(($last->children[1] instanceof \ast\Node && $last->children[1]->kind == \ast\AST_VAR) && ($temp->kind == \ast\AST_VAR || $temp->kind == \ast\AST_NAME)) {
						$ftemp = new \SplFileObject($file);
						$ftemp->seek($ast->lineno-1);
						$line = $ftemp->current();
						unset($ftemp);
						if(strpos($line,'}[') === false || strpos($line,']}') === false || strpos($line,'>{') === false) {
							Log::err(Log::ECOMPAT, "expression may not be PHP 7 compatible", $file, $ast->lineno);
						}
					}
				}
			}
		}
	}
}

function node_namelist($file, $node, $namespace) {
	$result = [];
	if($node instanceof \ast\Node) {
		foreach($node->children as $name_node) {
			$result[] = qualified_name($file, $name_node, $namespace);
		}
	}
	return $result;
}

function node_paramlist($file, $node, &$req, &$opt, $dc, $namespace) {
	if($node instanceof \ast\Node) {
		$result = [];
		$i = 0;
		foreach($node->children as $param_node) {
			$result[] = node_param($file, $param_node, $dc, $i, $namespace);
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

function node_param($file, $node, $dc, $i, $namespace) {
	if($node instanceof \ast\Node) {
		$type = ast_node_type($file, $node->children[0], $namespace);
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

function node_func($file, $conditional, $node, $current_scope, $current_class, $namespace='') {
	global $scope, $classes;

	if($node instanceof \ast\Node) {
		$req = $opt = 0;
		$dc = ['return'=>'', 'params'=>[]];
		if(!empty($node->docComment)) $dc = parse_doc_comment($node->docComment);
		$result = [
					'file'=>$file,
					'namespace'=>$namespace,
					'scope'=>$current_scope,
					'conditional'=>$conditional,
					'flags'=>$node->flags,
					'lineno'=>$node->lineno,
					'endLineno'=>$node->endLineno,
					'name'=>(strpos($current_scope,'::')===false) ? $namespace.$node->name : $node->name,
					'docComment'=>$node->docComment,
					'params'=>node_paramlist($file, $node->children[0], $req, $opt, $dc, $namespace),
					'required'=>$req,
					'optional'=>$opt,
					'ret'=>'',
					'oret'=>'',
					'ast'=>$node->children[2]
				  ];

		if(!empty($dc['deprecated'])) {
			$result['deprecated'] = true;
		}

		if($node->children[3] !==null) {
			$result['oret'] = ast_node_type($file, $node->children[3], $namespace); // Original return type
			$result['ret'] = ast_node_type($file, $node->children[3], $namespace); // This one changes as we walk the tree
		} else {
			// Check if the docComment has a return value specified
			if(!empty($dc['return'])) {
				// We can't actually figure out 'static' at this point, but fill it in regardless. It will be partially correct
				if($dc['return'] == 'static' || $dc['return'] == 'self' || $dc['return'] == '$this') {
					if(strpos($current_scope,'::')!==false) list($dc['return'],) = explode('::',$current_scope);
				}
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
					$type = node_type($file, $namespace, $v['def'], $current_scope, empty($current_class) ? null : $classes[strtolower($current_class)]);
					if($scope[$current_scope]['vars'][$v['name']]['type'] !== '') {
						// Does the default value match the declared type?
						if($type!=='null' && !type_check($type, $scope[$current_scope]['vars'][$v['name']]['type'])) {
							Log::err(Log::ETYPE, "Default value for {$scope[$current_scope]['vars'][$v['name']]['type']} \${$v['name']} can't be $type", $file, $node->lineno);
						}
					}
					add_type($current_scope, $v['name'], strtolower($type));
					// If we have no other type info about a parameter, just because it has a default value of null
					// doesn't mean that is its type. Any type can default to null
					if($type==='null' && !empty($result['params'][$k]['type'])) {
						$result['params'][$k]['type'] = merge_type($result['params'][$k]['type'], strtolower($type));
					}
				}
				$i++;
			}
			if(!empty($dc['vars'])) {
				foreach($dc['vars'] as $var) {
					if(empty($scope[$current_scope]['vars'][$var['name']])) {
						$scope[$current_scope]['vars'][$var['name']] = ['type'=>$var['type'], 'tainted'=>false, 'tainted_by'=>''];
					} else {
						add_type($current_scope, $var['name'], $var['type']);
					}
				}
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
			if(preg_match('/@param\s+(\S+)\s*(?:(\S+))*/', $line, $match)) {
				if(strpos($match[1],'\\')===0 && strpos($match[1],'\\',1)===false) {
					$type = trim($match[1],'\\');
				} else {
					$type = $match[1];
				}
				$result['params'][] = ['name'=>empty($match[2])?'':trim($match[2],'$'), 'type'=>$type];
			}
		}
		if(($pos=strpos($line, '@var')) !== false) {
			if(preg_match('/@var\s+(\S+)\s*(?:(\S+))*/', $line, $match)) {
				if(strpos($match[1],'\\')===0 && strpos($match[1],'\\',1)===false) {
					$type = trim($match[1],'\\');
				} else {
					$type = $match[1];
				}
				$result['vars'][] = ['name'=>empty($match[2])?'':trim($match[2],'$'), 'type'=>$type];
			}
		}
		if(($pos=strpos($line, '@return')) !== false) {
			if(preg_match('/@return\s+(\S+)/', $line, $match)) {
				if(strpos($match[1],'\\')===0 && strpos($match[1],'\\',1)===false) {
					$result['return'] = trim($match[1],'\\');
				} else {
					$result['return'] = $match[1];
				}
			}
		}
		if(($pos=strpos($line, '@deprecated')) !== false) {
			if(preg_match('/@deprecated\b/', $line, $match)) {
				$result['deprecated'] = true;
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
