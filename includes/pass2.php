<?php
namespace phan;

// Now it gets complicated
// Pass 2 tries to keep track of variable types which are stored in $scope
// It uses that info to check every call for anything that looks off
function pass2($file, $namespace, $ast, $current_scope, $parent_node=null, $current_class=null, $current_function=null, $parent_scope=null):string {
	global $classes, $functions, $namespace_map, $scope, $tainted_by, $quick_mode;
	static $next_node = 1;
	$vars = [];

	$parent_kind = null;
	if ($parent_node instanceof \ast\Node) {
		$parent_kind = $parent_node->kind;
	}
	if ($ast instanceof \ast\Node) {
		// Infinite Recursion check
		if(empty($ast->id)) {
			$ast->id = $next_node++;
		}
		if(!empty($parent_node)) {
			if(empty($ast->visited_from[$parent_node->id])) {
				$ast->visited_from[$parent_node->id] = 1;
			} else {
				return $namespace;
			}
		}
		switch($ast->kind) {
			case \ast\AST_NAMESPACE:
				$namespace = (string)$ast->children[0].'\\';
				break;

			case \ast\AST_USE_TRAIT:
				// We load up the trais in AST_CLASS, this part is just for pretty error messages
				foreach($ast->children[0]->children as $trait) {
					$name = $trait->children[0];
					$lname = strtolower($name);
					if(!empty($namespace_map[T_CLASS][$file][$lname])) {
						$name = $namespace_map[T_CLASS][$file][$lname];
					} else {
						if($trait->flags & \ast\flags\NAME_NOT_FQ) {
							$name = $namespace.$name;
						}
					}
					if(empty($classes[strtolower($name)])) {
						Log::err(Log::EUNDEF, "Undeclared trait {$trait->children[0]}", $file, $ast->lineno);
					}
				}
				break;

			case \ast\AST_CLASS:
				$lname = strtolower($namespace.$ast->name);
				if(empty($classes[$lname])) {
					dump_scope($scope);
					Log::err(Log::EFATAL, "Can't find class {$namespace}{$ast->name} - aborting", $file, $ast->lineno);
				}
				$current_class = $classes[$lname];
				$traits = $classes[$lname]['traits'];
				// Copy the trait over into this class
				foreach($traits as $trait) {
					if(empty($classes[$trait])) {
						continue;
					}
					// TODO: Implement the various trait aliasing mechanisms here
					$classes[$lname]['properties'] = array_merge($classes[$lname]['properties'], $classes[$trait]['properties']);
					$classes[$lname]['constants'] = array_merge($classes[$lname]['constants'], $classes[$trait]['constants']);
					$classes[$lname]['methods'] = array_merge($classes[$lname]['methods'], $classes[$trait]['methods']);

					// Need the scope as well
					foreach($classes[$trait]['methods'] as $k=>$method) {
						if(empty($scope["{$classes[$trait]['name']}::{$method['name']}"])) continue;
						$cs = $namespace.$ast->name.'::'.$method['name'];
						if(!array_key_exists($cs, $scope)) $scope[$cs] = [];
						if(!array_key_exists('vars', $scope[$cs])) $scope[$cs]['vars'] = [];
						$scope[$cs] = $scope["{$classes[$trait]['name']}::{$method['name']}"];
						// And finally re-map $this to point to this class
						$scope[$cs]['vars']['this']['type'] = $namespace.$ast->name;
					}
				}
				break;

			case \ast\AST_FUNC_DECL:
				if(empty($functions[strtolower($namespace.$ast->name)])) {
					Log::err(Log::EFATAL, "Can't find function {$namespace}{$ast->name} - aborting", $file, $ast->lineno);
				}
				$current_function = $functions[strtolower($namespace.$ast->name)];
				$parent_scope = $current_scope;
				$current_scope = $namespace.$ast->name;
				break;

			case \ast\AST_CLOSURE:
				$closure_name = '{closure '.$ast->id.'}';
				$functions[$closure_name] = node_func($file, false, $ast, $closure_name, '');
				$current_function = $closure_name;
				$parent_scope = $current_scope;
				$current_scope = $closure_name;
				if(!empty($scope[$parent_scope]['vars']['this'])) {
					// TODO: check for a static closure
					add_var_scope($current_scope, 'this', $scope[$parent_scope]['vars']['this']['type']);
				}
				if(!empty($ast->children[1]) && $ast->children[1]->kind == \ast\AST_CLOSURE_USES) {
					$uses = $ast->children[1];
					foreach($uses->children as $use) {
						if($use->kind != \ast\AST_CLOSURE_VAR) {
							Log::err(Log::EVAR, "You can only have variables in a closure use() clause", $file, $ast->lineno);
						} else {
							$name = var_name($use->children[0]);
							if($use->flags & \ast\flags\PARAM_REF) {
								if(empty($parent_scope) || empty($scope[$parent_scope]['vars']) || empty($scope[$parent_scope]['vars'][$name])) {
									add_var_scope($parent_scope, $name, '');
								}
								$scope[$current_scope]['vars'][$name] = &$scope[$parent_scope]['vars'][$name];
							} else {
								if(empty($parent_scope) || empty($scope[$parent_scope]['vars']) || empty($scope[$parent_scope]['vars'][$name])) {
									Log::err(Log::EVAR, "Variable \${$name} is not defined", $file, $ast->lineno);
								} else {
									$scope[$current_scope]['vars'][$name] = $scope[$parent_scope]['vars'][$name];
								}
							}
						}
					}
				}
				break;

			case \ast\AST_METHOD:
				if(empty($current_class['methods'][strtolower($ast->name)])) {
					Log::err(Log::EFATAL, "Can't find method {$current_class['name']}:{$ast->name} - aborting", $file, $ast->lineno);
				}
				$current_function = $current_class['methods'][strtolower($ast->name)];
				$parent_scope = $current_scope;
				$current_scope = $current_class['name'].'::'.$ast->name;
				break;

			case \ast\AST_USE: // TODO: Figure out a clean way to map namespaces
				break;

			case \ast\AST_FOREACH: // Not doing depth-first here, because we need to declare the vars for the body of the loop
				if(($ast->children[2] instanceof \ast\Node) && ($ast->children[2]->kind == \ast\AST_LIST)) {
					Log::err(Log::EFATAL, "Can't use list() as a key element - aborting", $file, $ast->lineno);
				}
				if($ast->children[1]->kind == \ast\AST_LIST) {
					add_var_scope($current_scope, var_name($ast->children[1]->children[0]), '', true);
					add_var_scope($current_scope, var_name($ast->children[1]->children[1]), '', true);
				} else {
					// value
					add_var_scope($current_scope, var_name($ast->children[1]), '', true);
					// key
					if(!empty($ast->children[2])) {
						add_var_scope($current_scope, var_name($ast->children[2]), '', true);
					}
				}
				break;

			case \ast\AST_CATCH:
				$obj = var_name($ast->children[0]);
				$name = var_name($ast->children[1]);
				if(!empty($name))
					add_var_scope($current_scope, $name, $obj, true);
				break;
		}

		// Depth-First for everything else
		foreach($ast->children as $child) {
			$namespace = pass2($file, $namespace, $child, $current_scope, $ast, $current_class, $current_function, $parent_scope);
		}
		switch($ast->kind) {
			case \ast\AST_ASSIGN:
			case \ast\AST_ASSIGN_REF:
				var_assign($file, $namespace, $ast, $current_scope, $current_class, $vars);
				foreach($vars as $k=>$v) {
					if(empty($v)) $v = ['type'=>'', 'tainted'=>false, 'tainted_by'=>''];
					if(empty($v['type'])) $v['type'] = '';
					if(strpos($k, '::') === false) $cs = $current_scope;
					else $cs = 'global';  // Put static properties in the global scope TODO: revisit

					// Check if we are assigning something to $GLOBALS[key]
					if($k=='GLOBALS' && $ast->children[0]->kind == \ast\AST_DIM) {
						$temp = $ast;
						$depth=0;
						while($temp->children[0]->kind == \ast\AST_DIM) {
							$depth++;
							$temp=$temp->children[0];
						}
						// If the index is a simple scalar, set it in the global scope
						if(!empty($temp->children[1]) && !($temp->children[1] instanceof \ast\Node)) {
							$cs = 'global';
							$k = $temp->children[1];
							if($depth==1) {
								$taint = false;
								$tainted_by = '';
								$v['type'] = node_type($file, $namespace, $ast->children[1], $current_scope, $current_class, $taint);
								$v['tainted'] = $taint;
								$v['tainted_by'] = $tainted_by;
							} else {
								// This is a $GLOBALS['a']['b'] type of assignment
								// TODO: track array content types
								$v['type'] = 'array';
								$v['tainted'] = false;
								$v['tainted_by'] = '';
							}
						}
					}
					if($k=='GLOBALS') break;
					add_var_scope($cs, $k, $v['type']);
					$scope[$cs]['vars'][$k]['tainted'] = $v['tainted'];
					$scope[$cs]['vars'][$k]['tainted_by'] = $v['tainted_by'];
				}
				break;

			case \ast\AST_LIST:
				// TODO: Very simplistic here - we can be smarter
				foreach($ast->children as $c) {
					$name = var_name($c);
					if(!empty($name)) add_var_scope($current_scope, $name, '');
				}
				break;

			case \ast\AST_GLOBAL:
				if(!array_key_exists($current_scope, $scope)) $scope[$current_scope] = [];
				if(!array_key_exists('vars', $scope[$current_scope])) $scope[$current_scope]['vars'] = [];
				$name = var_name($ast);
				if($name === false) break;
				if(!array_key_exists($name, $scope['global']['vars'])) {
					add_var_scope('global', $name, '');
				}
				$scope[$current_scope]['vars'][$name] = &$scope['global']['vars'][$name];
				break;

			case \ast\AST_FOREACH:
				// check the array, the key,value part was checked on in the non-DPS part above
				$type = node_type($file, $namespace, $ast->children[0], $current_scope, $current_class);
				if(type_scalar($type)) {
					Log::err(Log::ETYPE, "$type passed to foreach instead of array", $file, $ast->lineno);
				}
				break;

			case \ast\AST_STATIC:
				$name = var_name($ast);
				$type = node_type($file, $namespace, $ast->children[1], $current_scope, $current_class, $taint);
				add_var_scope($current_scope, $name, $type);
				$scope[$current_scope]['vars'][$name]['tainted'] = $taint;
				$scope[$current_scope]['vars'][$name]['tainted_by'] = $tainted_by;
				break;

			case \ast\AST_PRINT:
			case \ast\AST_ECHO:
				$taint = false;
				$tainted_by = '';
				$type = node_type($file, $namespace, $ast->children[0], $current_scope, $current_class, $taint);
				if($type == 'array' || (strlen($type) > 2 && substr($type,-2)=='[]')) {
					Log::err(Log::ETYPE, "array to string conversion", $file, $ast->lineno);
				}
				if($taint) {
					if(empty($tainted_by)) {
						Log::err(Log::ETAINT, "possibly tainted output.", $file, $ast->lineno);
					} else {
						Log::err(Log::ETAINT, "possibly tainted output. Data tainted at $tainted_by", $file, $ast->lineno);
					}
				}
				break;

			case \ast\AST_VAR:
				if($parent_kind == \ast\AST_STMT_LIST) {
					Log::err(Log::ENOOP, "no-op variable", $file, $ast->lineno);
				}
				break;

			case \ast\AST_ARRAY:
				if($parent_kind == \ast\AST_STMT_LIST) {
					Log::err(Log::ENOOP, "no-op array", $file, $ast->lineno);
				}
				break;

			case \ast\AST_CONST:
				if($parent_kind == \ast\AST_STMT_LIST) {
					Log::err(Log::ENOOP, "no-op constant", $file, $ast->lineno);
				}
				break;

			case \ast\AST_CLOSURE:
				if($parent_kind == \ast\AST_STMT_LIST) {
					Log::err(Log::ENOOP, "no-op closure", $file, $ast->lineno);
				}
				break;

			case \ast\AST_RETURN:
				// Check if there is a return type on the current function
				if(!empty($current_function['oret'])) {
					$ret = $ast->children[0];
					if($ret instanceof \ast\Node) {
					#	if($ast->children[0]->kind == \ast\AST_ARRAY) $ret_type='array';
					#	else $ret_type = node_type($file, $namespace, $ret, $current_scope, $current_class);
						$ret_type = node_type($file, $namespace, $ret, $current_scope, $current_class);
					} else {
						$ret_type = type_map(gettype($ret));
						// This is distinct from returning actual NULL which doesn't hit this else since it is an AST_CONST node
						if($ret_type=='NULL') $ret_type='void';
					}
					$check_type = $current_function['oret'];
					if(strpos("|$check_type|",'|self|')!==false) {
						$check_type = preg_replace("/\bself\b/", $current_class['name'], $check_type);
					}
					if(strpos("|$check_type|",'|static|')!==false) {
						$check_type = preg_replace("/\bstatic\b/", $current_class['name'], $check_type);
					}
					if(strpos("|$check_type|",'|\$this|')!==false) {
						$check_type = preg_replace("/\b\$this\b/", $current_class['name'], $check_type);
					}
					if(!type_check($ret_type, $check_type, $namespace)) {
						Log::err(Log::ETYPE, "return $ret_type but {$current_function['name']}() is declared to return {$current_function['oret']}", $file, $ast->lineno);
					}
				} else {
					$type = node_type($file, $namespace, $ast->children[0], $current_scope, $current_class);
					if(!empty($functions[$current_scope]['oret'])) { // The function has a return type declared
						if(!type_check($type, $functions[$current_scope]['oret'], $namespace)) {
							Log::err(Log::ETYPE, "return $type but {$functions[$current_scope]['name']}() is declared to return {$functions[$current_scope]['oret']}", $file, $ast->lineno);
						}
					} else {
						if(strpos($current_scope, '::') !== false) {
							list($class_name,$method_name) = explode('::',$current_scope,2);
							$idx = find_method_class($class_name, $method_name);
							if($idx) {
								$classes[$idx]['methods'][strtolower($method_name)]['ret'] = $type;
							}
						} else {
							if(!empty($functions[$current_scope]['ret'])) {
								foreach(explode('|',$type) as $t) {
									if(!empty($t) && strpos($functions[$current_scope]['ret'], $t) === false) {
										$functions[$current_scope]['ret'] = $functions[$current_scope]['ret'].'|'.$type;
									}
								}
								$functions[$current_scope]['ret'] = trim($functions[$current_scope]['ret'],'|');
							} else {
								if($current_scope != 'global') {
									$functions[$current_scope]['ret'] = $type;
								}
							}
						}
					}
				}
				break;

			case \ast\AST_CLASS_CONST_DECL:
			case \ast\AST_PROP_DECL:
				// TODO
				break;

			case \ast\AST_CALL:
				$found = false;
				$call = $ast->children[0];

				if($call->kind == \ast\AST_NAME) {
					$func_name = $call->children[0];
					$found = null;
					if($call->flags & \ast\flags\NAME_NOT_FQ) {
						if(!empty($namespace_map[T_FUNCTION][$file][strtolower($namespace.$func_name)])) {
							$cs = $namespace_map[T_FUNCTION][$file][strtolower($namespace.$func_name)];
							$found = $functions[strtolower($cs)];
						} else if(!empty($namespace_map[T_FUNCTION][$file][strtolower($func_name)])) {
							$cs = $namespace_map[T_FUNCTION][$file][strtolower($func_name)];
							$found = $functions[strtolower($cs)];
						} else if(!empty($functions[strtolower($namespace.$func_name)])) {
							$cs = $namespace.$func_name;
							$found = $functions[strtolower($cs)];
						} else if(!empty($functions[strtolower($func_name)])) {
							$cs = $func_name;
							$found = $functions[strtolower($func_name)];
						}
					} else {
						if(!empty($functions[strtolower($func_name)])) {
							$cs = $func_name;
							$found = $functions[strtolower($func_name)];
						}
					}
					if(!$found) Log::err(Log::EUNDEF, "call to undefined function {$func_name}()", $file, $ast->lineno);
					else {
						// Ok, the function exists, but are we calling it correctly?
						if($found instanceof ReflectionType) echo "oops at $file:{$ast->lineno}\n";  // DEBUG
						arg_check($file, $namespace, $ast, $func_name, $found, $current_scope, $current_class);
						if($found['file'] != 'internal') {
							// re-check the function's ast with these args
							if(!$quick_mode) pass2($found['file'], $found['namespace'], $found['ast'], $found['scope'], $ast, $current_class, $found, $parent_scope);
						} else {
							if(!$found['avail']) {
								if(!$found) Log::err(Log::EAVAIL, "function {$func_name}() is not compiled into this version of PHP", $file, $ast->lineno);
							}
						}
					}
				} else if ($call->kind == \ast\AST_VAR) {
					$name = var_name($call);
					if($name instanceof \ast\Node) {
						// $$var() - Ugh..
						// TODO - something brilliant here
					} else {
					// $var() - hopefully a closure, otherwise we don't know
						if(array_key_exists($name, $scope[$current_scope]['vars'])) {
							if(($pos=strpos($scope[$current_scope]['vars'][$name]['type'], '{closure '))!==false) {
								$closure_id = (int)substr($scope[$current_scope]['vars'][$name]['type'], $pos+9);
								$func_name = '{closure '.$closure_id.'}';
								$found = $functions[$func_name];
								arg_check($file, $namespace, $ast, $func_name, $found, $current_scope, $current_class);
								if(!$quick_mode) pass2($found['file'], $found['namespace'], $found['ast'], $found['scope'], $ast, $current_class, $found, $parent_scope);
							}
						}
					}
				}
				break;

			case \ast\AST_NEW:
				$class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope);
				if($class_name) {
					$method_name = '__construct';  // No type checking for PHP4-style constructors
					$method = find_method($class_name, $method_name);
					if($method) { // Found a constructor
						arg_check($file, $namespace, $ast, $method_name, $method, $current_scope, $current_class, $class_name);
						if($method['file'] != 'internal') {
							// re-check the function's ast with these args
							if(!$quick_mode) pass2($method['file'], $method['namespace'], $method['ast'], $method['scope'], $ast, $classes[strtolower($class_name)], $method, $parent_scope);
						}
					}
				}
				break;

			case \ast\AST_STATIC_CALL:
				$static_call_ok = false;
				$class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope, $static_call_ok);
				if($class_name) {
					// The class is declared, but does it have the method?
					$method_name = $ast->children[1];
					$method = find_method($class_name, $method_name);
					if(is_array($method) && array_key_exists('avail', $method) && !$method['avail']) {
						Log::err(Log::EAVAIL, "method {$class_name}::{$method_name}() is not compiled into this version of PHP", $file, $ast->lineno);
					}
					if($method === false) {
						Log::err(Log::EUNDEF, "static call to undeclared method {$class_name}::{$method_name}()", $file, $ast->lineno);
					} else if($method != 'dynamic') {
						// Was it declared static?
						if(!($method['flags'] & \ast\flags\MODIFIER_STATIC)) {
							if(!$static_call_ok) {
								Log::err(Log::ESTATIC, "static call to non-static method {$class_name}::{$method_name}() defined at {$method['file']}:{$method['lineno']}", $file, $ast->lineno);
							}
						}
						arg_check($file, $namespace, $ast, $method_name, $method, $current_scope, $current_class, $class_name);
						if($method['file'] != 'internal') {
							// re-check the function's ast with these args
							if(!$quick_mode) pass2($method['file'], $method['namespace'], $method['ast'], $method['scope'], $ast, $classes[strtolower($class_name)], $method, $parent_scope);
						}
					}
				}
				break;

			case \ast\AST_METHOD_CALL:
				$class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope);
				if($class_name) {
					$method_name = $ast->children[1];
					$method = find_method($class_name, $method_name);
					if($method === false) {
						Log::err(Log::EUNDEF, "call to undeclared method {$class_name}->{$method_name}()", $file, $ast->lineno);
					} else if($method != 'dynamic') {
						if(array_key_exists('avail', $method) && !$method['avail']) {
							Log::err(Log::EAVAIL, "method {$class_name}::{$method_name}() is not compiled into this version of PHP", $file, $ast->lineno);
						}
						arg_check($file, $namespace, $ast, $method_name, $method, $current_scope, $current_class, $class_name);
						if($method['file'] != 'internal') {
							// re-check the function's ast with these args
							if(!$quick_mode) pass2($method['file'], $method['namespace'], $method['ast'], $method['scope'], $ast, $classes[strtolower($class_name)], $method, $parent_scope);
						}
					}
				}
				break;

		}
	} else {
		if($parent_kind == \ast\AST_STMT_LIST) {
			if($ast !== null) {
				Log::err(Log::ENOOP, "(line number not accurate) dangling expression: ".var_export($ast, true), $file, $parent_node->lineno);
			}
		}
	}
	return $namespace;
}

// Takes an AST_VAR node and tries to find the variable in the current scope and returns its likely type
// For pass-by-ref args, we suppress the not defined error message
function var_type($file, $node, $current_scope, &$taint, $check_var_exists=true) {
	global $scope, $tainted_by;
	// Check for $$var or ${...} (whose idea was that anyway?)
	if(($node->children[0] instanceof \ast\Node) && ($node->children[0]->kind == \ast\AST_VAR || $node->children[0]->kind == \ast\AST_BINARY_OP)) {
		return "mixed";
	}
	if(empty($scope[$current_scope]['vars'][$node->children[0]])) {
		if($check_var_exists) {
			if(!superglobal($node->children[0]))
				Log::err(Log::EVAR, "Variable \${$node->children[0]} is not defined", $file, $node->lineno);
		}
	} else {
		if(!empty($scope[$current_scope]['vars'][$node->children[0]]['tainted'])) {
			$tainted_by = $scope[$current_scope]['vars'][$node->children[0]]['tainted_by'];
			$taint = true;
		}
		return $scope[$current_scope]['vars'][$node->children[0]]['type'] ?? '';
	}
	return '';
}

// Adds variable types to the current scope from the given node
// It does a bit of simple taint checking as well and sets a tainted flag on each one
function var_assign($file, $namespace, $ast, $current_scope, $current_class, &$vars) {
	global $classes, $functions, $scope;

	$left = $ast->children[0];
	$right = $ast->children[1];
	$left_type = $right_type = null;
	$parent = $ast;
	$taint = false;

	// Deal with $a=$b=$c=1; and trickle the right-most value to the top through recursion
	if(($right instanceof \ast\Node) && ($right->kind == \ast\AST_ASSIGN)) {
		$right_type = var_assign($file, $namespace, $right, $current_scope, $current_class, $vars);
	}

	if(($left instanceof \ast\Node) && ($left->kind != \ast\AST_VAR) && ($left->kind != \ast\AST_STATIC_PROP)) {
		// Walk multi-level arrays and chained stuff
		// eg. $var->prop[1][2]->prop
		while(($left instanceof \ast\Node) && ($left->kind != \ast\AST_VAR)) {
			 $parent = $left;
			 $left = $left->children[0];
		}
	}

	if($left==null) {
		// No variable name for assignment??
		// This is generally for something like list(,$var) = [1,2]
		return $right_type;
	}

	if(!is_object($left)) {
		if($left=="self") {
			// TODO: Looks like a self::$var assignment - do something smart here
			return $right_type;
		} else if($left=='static') {
			// TODO: static::$prop assignment
			return $right_type;
		} else {
			return $right_type;
		}
	}

	// DEBUG
	if(!($left instanceof \ast\Node)) {
		echo "Check this $file\n".ast_dump($left)."\n".ast_dump($parent)."\n";
	}

	if($left->kind == \ast\AST_STATIC_PROP && $left->children[0]->kind == \ast\AST_NAME) {
		if($left->children[1] instanceof \ast\Node) {
			// This is some sort of self::${$key}  thing, give up
			return $right_type;
		}
		if($left->children[0]->flags & \ast\flags\NAME_NOT_FQ) {
			$left_name = $namespace.$left->children[0]->children[0].'::'.$left->children[1];
		} else {
			$left_name = $left->children[0]->children[0].'::'.$left->children[1];
		}
	} else {
		$left_name = $left->children[0];
	}

	if(($right instanceof \ast\Node) && ($right->kind == \ast\AST_CLOSURE)) {
		$right_type = 'callable:{closure '.$right->id.'}';
		$vars[$left_name]['type'] = $right_type;
		$vars[$left_name]['tainted'] = false;
		$vars[$left_name]['tainted_by'] = '';
		return $right_type;
	}

	if(!$left_type && $right_type) {
		$left_type = $right_type;
	} else if(!$left_type) {
		// We didn't figure out the type simply by looking at the left side of the assignment, check the right
		$right_type = node_type($file, $namespace, $right, $current_scope, $current_class, $taint);
		$left_type = $right_type;
	}

	if($parent->kind == \ast\AST_DIM && $left->kind == \ast\AST_VAR) {
		// Generics check
		if(!($left->children[0] instanceof \ast\Node)) {
			$var_type = $scope[$current_scope]['vars'][$left->children[0]]['type'] ?? '';
			if(!empty($var_type) && strpos($var_type, '[]') !== false) {
				if(!type_check(generics($var_type), $right_type)) {
					Log::err(Log::ETYPE, "Assigning {$right_type} to \${$left->children[0]} which is {$var_type}", $file, $ast->lineno);
				}
			}
		}
	}
	// $var->prop = ...
	if($parent->kind == \ast\AST_PROP && $left->kind == \ast\AST_VAR) {
		// Check for $$var-> weirdness
		if(!($left->children[0] instanceof \ast\Node)) {
			$prop = $parent->children[1];
			// Check for $var->$...
			if(!($prop instanceof \ast\Node)) {
				if($left->children[0] == 'this') {
					// $this->prop =
					$lclass = strtolower($current_class['name']);
					if(empty($classes[$lclass]['properties'][$prop])) {
						$classes[$lclass]['properties'][$prop] = [
							'flags'=>\ast\flags\MODIFIER_PUBLIC,
							'name'=>$prop,
							'lineno'=>0,
							'value'=>$right_type ];
					} else {
						$classes[$lclass]['properties'][$prop]['value'] = merge_type($classes[$lclass]['properties'][$prop]['value'], $right_type);
					}
					return $right_type;
				} else {
					// $var->prop =
					$temp = node_type($file, $namespace, $left, $current_scope, $current_class, $taint);
					if(!is_native_type($temp)) {
						$lclass = strtolower($temp);
						if(!empty($classes[$lclass])) {
							if(empty($classes[$lclass]['properties'][$prop])) {
								$classes[$lclass]['properties'][$prop] = [
									'flags'=>\ast\flags\MODIFIER_PUBLIC,
									'name'=>$prop,
									'lineno'=>0,
									'value'=>$right_type ];
							} else {
								$classes[$lclass]['properties'][$prop]['value'] = merge_type($classes[$lclass]['properties'][$prop]['value'], $right_type);
							}
							return $right_type;
						} else {
							return '';
						}
					} else {
						return '';
					}
				}
			}
		}
	}

	if($left_name instanceof \ast\Node) {
		// TODO: Deal with $$var
	} else {
		$vars[$left_name]['type'] = $left_type ?? '';
		$vars[$left_name]['tainted'] = $taint;
		$vars[$left_name]['tainted_by'] = $taint ? "{$file}:{$left->lineno}" : '';
	}
	return $right_type;
}

function arg_check(string $file, $namespace, $ast, string $func_name, $func, string $current_scope, $current_class, string $class_name='') {
	global $internal_arginfo, $functions, $scope;

	$ok = false;
	$varargs = false;
	$taint = false;

	// Are we calling it with the right number of args?
	if($ast->kind == \ast\AST_CALL || $ast->kind == \ast\AST_NEW) $arglist = $ast->children[1];
	else  $arglist = $ast->children[2];

	$argcount = count($arglist->children);
	// Special common cases where we want slightly better multi-signature error messages
	if($func['file']=='internal') {
	  switch($func['name']) {
		case 'join':
		case 'implode': // (string glue, array pieces), (array pieces, string glue) or (array pieces)
			if($argcount == 1) { // If we have just one arg it must be an array
				if(($arg_type=node_type($file, $namespace, $arglist->children[0], $current_scope, $current_class)) != 'array') {
					Log::err(Log::ETYPE, "arg#1(pieces) is $arg_type but {$func['name']}() takes array when passed only 1 arg", $file, $ast->lineno);
				}
				return;
			} else if($argcount == 2) {
				$arg1_type = node_type($file, $namespace, $arglist->children[0], $current_scope, $current_class);
				$arg2_type = node_type($file, $namespace, $arglist->children[1], $current_scope, $current_class);
				if($arg1_type == 'array') {
					if(!type_check($arg2_type,'string')) {
						Log::err(Log::ETYPE, "arg#2(glue) is $arg2_type but {$func['name']}() takes string when arg#1 is array", $file, $ast->lineno);
					}
				} else if($arg1_type=='string') {
					if(!type_check($arg2_type, 'array')) {
						Log::err(Log::ETYPE, "arg#2(pieces) is $arg2_type but {$func['name']}() takes array when arg#1 is string", $file, $ast->lineno);
					}
				}
				return;
			}
			// Any other arg counts we will let the regular checks handle
			break;
		case 'strtok': // (string str, string token) or (string token)
			if($argcount == 1) { // If we have just one arg it must be a string token
				if(($arg_type=node_type($file, $namespace, $arglist->children[0], $current_scope, $current_class)) != 'string') {
					Log::err(Log::ETYPE, "arg#1(token) is $arg_type but {$func['name']}() takes string when passed only one arg", $file, $ast->lineno);
					return;
				}
			}
			// The arginfo check will handle the other case
			break;
		case 'min':
		case 'max':
			if($argcount == 1) { // If we have just one arg it must be an array
				if(($arg_type=node_type($file, $namespace, $arglist->children[0], $current_scope, $current_class)) != 'array') {
					Log::err(Log::ETYPE, "arg#1(values) is $arg_type but {$func['name']}() takes array when passed only one arg", $file, $ast->lineno);
					return;
				}
			}
			$varargs = true;
			// The arginfo check will handle the other case
			break;
		default:
			if(internal_varargs_check($func['name'])) $varargs = true;
			break;
	  }
	} else {
		foreach($func['params'] as $param) {
			if($param['flags'] & \ast\flags\PARAM_VARIADIC) $varargs = true;
		}
	}

	$fn = $func['scope'] ?? $func['name'];
	if($argcount < $func['required']) {
		$err = true;
		$alt = 1;
		// Check if there is an alternate signature that is ok
		while(!empty($functions["{$func['name']} $alt"])) {
			if($argcount < $functions["{$func['name']} $alt"]['required']) $alt++;
			else { $err = false; break; }
		}
		if($err) {
			if($func['file']=='internal') {
				Log::err(Log::EPARAM, "call with $argcount arg(s) to {$func['name']}() which requires {$func['required']} arg(s)", $file, $ast->lineno);
			} else {
				Log::err(Log::EPARAM, "call with $argcount arg(s) to {$func['name']}() which requires {$func['required']} arg(s) defined at {$func['file']}:{$func['lineno']}", $file, $ast->lineno);
			}
		}
	}

	if(!$varargs && $argcount > $func['required']+$func['optional']) {
		$err = true;
		$alt = 1;
		// Check if there is an alternate signature that is ok
		while(!empty($functions["{$func['name']} $alt"])) {
			if($argcount > ($functions["{$func['name']} $alt"]['required']+$functions["{$func['name']} $alt"]['optional'])) $alt++;
			else { $err = false; break; }
		}
		if($err) {
			$max = $func['required']+$func['optional'];
			if($func['file']=='internal')
				Log::err(Log::EPARAM, "call with $argcount arg(s) to {$func['name']}() which only takes {$max} arg(s)", $file, $ast->lineno);
			else
				Log::err(Log::EPARAM, "call with $argcount arg(s) to {$func['name']}() which only takes {$max} arg(s) defined at {$func['file']}:{$func['lineno']}", $file, $ast->lineno);
		}
	}

	// Are the types right?
	// Check if we have any alternate arginfo signatures
	// Checking the alternates before the main to make the final error messages, if any, refer to the main signature
	$errs = [];
	$alt = 1;
	while(!empty($functions["{$func['name']} $alt"])) {
		$errs = arglist_type_check($file, $namespace, $arglist, $functions["{$func['name']} $alt"], $current_scope, $current_class);
		$alt++;
		if(empty($errs)) break;
	}
	if($alt==1 || ($alt>1 && !empty($errs))) $errs = arglist_type_check($file, $namespace, $arglist, $func, $current_scope, $current_class);

	foreach($errs as $err) {
		Log::err(Log::ETYPE, $err, $file, $ast->lineno);
	}
}

function arglist_type_check($file, $namespace, $arglist, $func, $current_scope, $current_class):array {
	global $classes, $internal_arginfo, $scope, $tainted_by;

	$errs=[];
	$fn = $func['scope'] ?? $func['name'];
	foreach($arglist->children as $k=>$arg) {
		$taint = false;
		$tainted_by = '';

		if(empty($func['params'][$k])) break;
		$param = $func['params'][$k];
		$argno = $k+1;
		$arg_name = false;
		if($param['flags'] & \ast\flags\PARAM_REF) {
			if((!$arg instanceof \ast\Node) || ($arg->kind != \ast\AST_VAR && $arg->kind != \ast\AST_DIM && $arg->kind != \ast\AST_PROP)) {
				$errs[] = "Only variables can be passed by reference at arg#$argno of $fn()";
			} else {
				$arg_name = var_name($arg);
			}
		}
		// For user functions, add the types of the args to the receiving function's scope
		if($func['file'] != 'internal') {
			if(empty($scope[$fn]['vars'][$param['name']])) {
				$scope[$fn]['vars'][$param['name']] = ['type'=>'', 'tainted'=>false, 'tainted_by'=>''];
			}
			// If it is by-ref link it back to the local variable name
			if($param['flags'] & \ast\flags\PARAM_REF) {
				$arg_type = node_type($file, $namespace, $arg, $current_scope, $current_class, $taint, false);
				if(!empty($scope[$current_scope]['vars'][$arg_name])) {
					$scope[$fn]['vars'][$param['name']] = &$scope[$current_scope]['vars'][$arg_name];
				} else {
					$scope[$fn]['vars'][$param['name']]['type'] = $arg_type;
				}
			} else {
				$arg_type = node_type($file, $namespace, $arg, $current_scope, $current_class, $taint);
				if(!empty($arg_type)) add_type($fn, $param['name'], $arg_type);
			}
			if($taint) {
				$scope[$fn]['vars'][$param['name']]['tainted'] = true;
				$scope[$fn]['vars'][$param['name']]['tainted_by'] = $tainted_by;
			} else {
				$scope[$fn]['vars'][$param['name']]['tainted'] = false;
				$scope[$fn]['vars'][$param['name']]['tainted_by'] = '';
			}
		} else {
			$arg_type = node_type($file, $namespace, $arg, $current_scope, $current_class, $taint, !($param['flags'] & \ast\flags\PARAM_REF));
		}

		// For all functions, add the param to the local scope if pass-by-ref
		// and make it an actual ref for user functions
		if($param['flags'] & \ast\flags\PARAM_REF) {
			if($func['file'] == 'internal') {
				if(empty($scope[$current_scope]['vars'][$arg_name])) {
					add_var_scope($current_scope, $arg_name, $arg_type);
				}
			} else {
				if(empty($scope[$current_scope]['vars'][$arg_name])) {
					if(!array_key_exists($current_scope, $scope)) $scope[$current_scope] = [];
					if(!array_key_exists('vars', $scope[$current_scope])) $scope[$current_scope]['vars'] = [];
					$scope[$current_scope]['vars'][$arg_name] = &$scope[$fn]['vars'][$param['name']];
				}
			}
		}

		// turn callable:{closure n} into just callable
		if(strpos($arg_type, ':') !== false) list($arg_type,) = explode(':',$arg_type,2);

		// if we have a single non-native type, expand it
		if(!is_native_type($arg_type)) {
			if(!empty($classes[strtolower($arg_type)]['type'])) {
				$arg_type = $classes[strtolower($arg_type)]['type'];
			}
		}

		if(!type_check($arg_type, $param['type'], $namespace)) {
			if(!empty($param['name'])) $paramstr = '('.trim($param['name'],'&=').')';
			else $paramstr = '';
			if(empty($arg_type)) $arg_type = '';
			if($func['file']=='internal') {
				if(!($param['flags'] & \ast\flags\PARAM_REF)) $errs[] = "arg#$argno{$paramstr} is $arg_type but {$func['name']}() takes {$param['type']}";
			} else {
				$errs[] = "arg#$argno{$paramstr} is $arg_type but {$func['name']}() takes {$param['type']} defined at {$func['file']}:{$func['lineno']}";
			}
		}
	}
	return $errs;
}

// int->float is allowed
// float->int is not
function type_check($src, $dst, $namespace=''):bool {
	global $classes;

	// Fast-track most common cases first
	if($src===$dst) return true;
	if(empty($dst) || empty($src)) return true;
	if(strpos("|$src|", '|mixed|') !== false) return true;
	if(strpos("|$dst|", '|mixed|') !== false) return true;
	if($src==='int' && $dst==='float') return true;

	$src = type_map(strtolower($src));
	$dst = type_map(strtolower($dst));

	// our own union types
	foreach(explode('|',$src) as $s) {
		if(empty($s)) continue;
		foreach(explode('|',$dst) as $d) {
			if(empty($d)) continue;
			if(substr($s,0,9)=='callable:') $s = 'callable';
			if(substr($d,0,9)=='callable:') $d = 'callable';
			if($s[0]=='\\') $s = substr($s,1);
			if($d[0]=='\\') $d = substr($d,1);
			if($s===$d) return true;
			if($s==='int' && $d==='float') return true; // int->float is ok
			if(($s==='array' || $s==='string' || $s==='string[]') && $d==='callable') return true;
			if($s === 'object' && !type_scalar($d) && $d!=='array') return true;
			if($d === 'object' && !type_scalar($s) && $s!=='array') return true;
			if(strpos($s,'[]')!==false && $d==='array') return true;
			if(strpos($d,'[]')!==false && $s==='array') return true;
			if(($pos=strrpos($d,'\\'))!==false) {
				if(!empty($namespace)) {
					if(trim(strtolower($namespace.$s),'\\') == $d) return true;
				} else {
					if(substr($d,$pos+1)===$s) return true; // Lazy hack, but...
				}
			}
			if(($pos=strrpos($s,'\\'))!==false) {
				if(!empty($namespace)) {
					if(trim(strtolower($namespace.$d),'\\') == $s) return true;
				} else {
					if(substr($s,$pos+1)===$d) return true; // Lazy hack, but...
				}
			}
		}
	}
	return false;
}

function internal_varargs_check(string $func_name):bool  {
	global $internal_arginfo;

	if(empty($internal_arginfo[$func_name])) return false;
	foreach($internal_arginfo[$func_name] as $k=>$v) {
		if($k===0) continue;
		if(strpos($k,'...')!==false) return true;
	}
	return false;
}

/**
 * Walk the inheritance tree to find the method
 * @return array|string|false
 */
function find_method(string $class_name, $method_name) {
	global $classes;

	if($method_name instanceof \ast\Node) return 'dynamic';
	$class_name  = strtolower($class_name);
	$method_name = strtolower($method_name);

	if(!empty($classes[$class_name]['methods'][$method_name])) {
		return $classes[$class_name]['methods'][$method_name];
	}
	if(!empty($classes[$class_name]['traits'])) {
		foreach($classes[$class_name]['traits'] as $trait) {
			if(!empty($classes[strtolower($trait)]['methods'][$method_name])) {
				return $classes[strtolower($trait)]['methods'][$method_name];
			}
		}
	}

	// We don't chain constructors
	if($method_name == '__construct') return false;

	if(!empty($classes[$class_name]['parent'])) {
		if(strtolower($classes[$class_name]['parent']) == $class_name) return $class_name;
		else return find_method($classes[$class_name]['parent'], $method_name);
	}
	return false;
}

// Returns the class_name location in the $classes array of the given method
function find_method_class(string $class_name, $method_name) {
	global $classes;

	if($method_name instanceof \ast\Node) return false;
	$class_name  = strtolower($class_name);
	$method_name = strtolower($method_name);

	if(!empty($classes[$class_name]['methods'][$method_name])) {
		return $class_name;
	}
	if(!empty($classes[$class_name]['traits'])) {
		foreach($classes[$class_name]['traits'] as $trait) {
			if(!empty($classes[$trait]['methods'][$method_name])) {
				return $trait;
			}
		}
	}
	if(!empty($classes[$class_name]['parent'])) {
		if(strtolower($classes[$class_name]['parent']) == $class_name) return $class_name;
		else return find_method_class($classes[$class_name]['parent'], $method_name);
	}
	return false;
}

// Find the class name gicen and AST_NAME node
function find_class($node, $namespace, $nmap) {
	global $classes;

	if(!($node instanceof \ast\Node) || $node->kind != \ast\AST_NAME) {
		Log::err(Log::EFATAL, "BUG: Bad node passed to find_class");
		return null;
	}
	$name = strtolower(var_name($node->children[0]));

	if($node->flags & \ast\flags\NAME_NOT_FQ) {
		if(!empty($nmap[strtolower($name)])) {
			if(!empty($classes[strtolower($nmap[$name] )])) {
				return $classes[strtolower($nmap[$name])];
			}
		}
		if(!empty($classes[$namespace.$name])) {
			return $classes[$namespace.$name];
		}
	} else {
		if(empty($classes[$name])) {
			return null;
		} else return $classes[$name];
	}
	return null;
}

// Takes "a|b[]|c|d[]|e" and returns "b|d"
function generics(string $str):string {
	if((strpos($str,'[]'))===false) return '';
	$ret = [];
	foreach(explode('|', $str) as $type) {
		if(($pos=strpos($type, '[]')) === false) continue;
		$ret[] = substr($type, 0, $pos);
	}

	// If |array| is in there, then it can be any type
	if(stripos("|$str|", "|array|") !== false) $ret[] = 'mixed';

	return implode('|', $ret);
}

// Takes "a|b" and returns "a[]|b[]"
function  mkgenerics(string $str):string {
	$ret = [];
	foreach(explode('|', $str) as $type) {
		if(empty($type)) continue;
		if($type=='array' || $type=='mixed') $ret[] = 'array';
		else {
			$ret[] = trim($type,'[]').'[]';
		}
	}
	return implode('|', $ret);
}

function node_type($file, $namespace, $node, $current_scope, $current_class, &$taint=null, $check_var_exists=true) {
	global $classes, $functions, $scope, $namespace_map, $internal_arginfo;

	if(!($node instanceof \ast\Node)) {
		if($node===null) return '';
		return(type_map(gettype($node)));
	} else {
		if($node->kind == \ast\AST_ARRAY) {
			if(!empty($node->children) && $node->children[0] instanceof \ast\Node && $node->children[0]->kind == \ast\AST_ARRAY_ELEM) {
				if($node->children[0]->children[0] instanceof \ast\Node) {
					$type = node_type($file, $namespace, $node->children[0]->children[0], $current_scope, $current_class, $temp_taint);
				} else {
					$type = type_map(gettype($node->children[0]->children[0]));
				}
				if(!empty($type)) return mkgenerics($type);
			}
			return 'array';

		} else if($node->kind == \ast\AST_BINARY_OP) {
			$taint = var_taint_check($file, $node, $current_scope);
			switch($node->flags) {
				// Always a string from a concat
				case \ast\flags\BINARY_CONCAT:
					$temp_taint = false;
					node_type($file, $namespace, $node->children[0], $current_scope, $current_class, $temp_taint);
					if($temp_taint) {
						$taint = true;
						return 'string';
					}
					node_type($file, $namespace, $node->children[1], $current_scope, $current_class, $temp_taint);
					if($temp_taint) {
						$taint = true;
					}
					return 'string';
					break;

				// Boolean unless invalid operands
				case \ast\flags\BINARY_IS_IDENTICAL:
				case \ast\flags\BINARY_IS_NOT_IDENTICAL:
				case \ast\flags\BINARY_IS_EQUAL:
				case \ast\flags\BINARY_IS_NOT_EQUAL:
				case \ast\flags\BINARY_IS_SMALLER:
				case \ast\flags\BINARY_IS_SMALLER_OR_EQUAL:
					$taint = false;
					return 'bool';
					break;
				// Add is special because you can add arrays
				case \ast\flags\BINARY_ADD:
					$temp = node_type($file, $namespace, $node->children[0], $current_scope, $current_class);
					if(!$temp) $left = '';
					else $left = type_map($temp);
					$temp = node_type($file, $namespace, $node->children[1], $current_scope, $current_class);
					if(!$temp) $right = '';
					else $right = type_map($temp);

					if($left == 'array' && $right == 'array') {
						return 'array';
					} else if($left == 'array' && !type_check($right, 'array')) {
						Log::err(Log::ETYPE, "invalid operator: left operand is array and right is not", $file, $node->lineno);
						return '';
					} else if($right == 'array' && !type_check($left, 'array')) {
						Log::err(Log::ETYPE, "invalid operator: right operand is array and left is not", $file, $node->lineno);
						return '';
					} else if($left=='int' && $right == 'int') {
						return 'int';
					} else if($left=='float' || $right=='float') {
						return 'float';
					} else if($left=='array' || $right=='array') {
						// If it is a '+' and we know one side is an array and the other is unknown, assume array
						return 'array';
					}
					return 'int|float';
					$taint = false;
					break;

				// Everything else should be an int/float
				default:
					$temp = node_type($file, $namespace, $node->children[0], $current_scope, $current_class);
					if(!$temp) $left = '';
					else $left = type_map($temp);
					$temp = node_type($file, $namespace, $node->children[1], $current_scope, $current_class);
					if(!$temp) $right = '';
					else $right = type_map($temp);

					if($left == 'array' || $right == 'array') {
						Log::err(Log::ETYPE, "invalid array operator", $file, $node->lineno);
						return '';
					} else if($left=='int' && $right == 'int') {
						return 'int';
					} else if($left=='float' || $right=='float') {
						return 'float';
					}
					return 'int|float';
					$taint = false;
					break;
			}
		} else if($node->kind == \ast\AST_CAST) {
			$taint = var_taint_check($file, $node->children[0], $current_scope);
			switch($node->flags) {
				case \ast\flags\TYPE_NULL: return 'null'; break;
				case \ast\flags\TYPE_BOOL: $taint = false; return 'bool'; break;
				case \ast\flags\TYPE_LONG: $taint = false; return 'int'; break;
				case \ast\flags\TYPE_DOUBLE: $taint = false; return 'float'; break;
				case \ast\flags\TYPE_STRING: return 'string'; break;
				case \ast\flags\TYPE_ARRAY: return 'array'; break;
				case \ast\flags\TYPE_OBJECT: return 'stdClass'; break;
				default: Log::err(Log::EFATAL, "Unknown type (".$node->flags.") in cast");
			}
		} else if($node->kind == \ast\AST_NEW) {
			$class_name = find_class_name($file, $node, $namespace, $current_class, $current_scope);
			if($class_name) return $classes[strtolower($class_name)]['type'];
			return 'object';

		} else if($node->kind == \ast\AST_DIM) {
			$taint = var_taint_check($file, $node->children[0], $current_scope);
			// TODO: Do something smart with array elements
			return '';

		} else if($node->kind == \ast\AST_VAR) {
			return var_type($file, $node, $current_scope, $taint, $check_var_exists);

		} else if($node->kind == \ast\AST_ENCAPS_LIST) {
			foreach($node->children as $encap) {
				if($encap instanceof \ast\Node) {
					if(var_taint_check($file, $encap, $current_scope)) $taint = true;
				}
			}
			return "string";

		} else if($node->kind == \ast\AST_CONST) {
			if($node->children[0]->kind == \ast\AST_NAME) {
				if(defined($node->children[0]->children[0])) return type_map(gettype(constant($node->children[0]->children[0])));
				else {
					// Todo: user-defined constant
				}
			}

		} else if($node->kind == \ast\AST_PROP) {
			if($node->children[0]->kind == \ast\AST_VAR) {
				$class_name = find_class_name($file, $node, $namespace, $current_class, $current_scope);
				if($class_name && !($node->children[1] instanceof \ast\Node)) {
					if(empty($classes[strtolower($class_name)]['properties'][$node->children[1]])) {
						return '';
					}
					return $classes[strtolower($class_name)]['properties'][$node->children[1]]['value'];
				}
			}
		} else if($node->kind == \ast\AST_CALL) {
			if($node->children[0]->kind == \ast\AST_NAME) {
				$func_name = $node->children[0]->children[0];
				if($node->children[0]->flags & \ast\flags\NAME_NOT_FQ) {
					$func = $namespace_map[T_FUNCTION][$file][strtolower($namespace.$func_name)] ??
					        $namespace_map[T_FUNCTION][$file][strtolower($func_name)] ??
					        $functions[strtolower($namespace.$func_name)] ??
							$functions[strtolower($func_name)] ??
					        null;
				} else {
					$func = $functions[strtolower($func_name)] ?? null;
				}
				if($func['file'] == 'internal' && empty($func['ret'])) {
					if(!empty($internal_arginfo[$func_name])) {
						return $internal_arginfo[$func_name][0] ?? '';
					}
				} else {
					return $func['ret'] ?? '';
				}
			} else {
				// TODO: Handle $func() and other cases that get here
			}

		} else if($node->kind == \ast\AST_STATIC_CALL) {
			$class_name = find_class_name($file, $node, $namespace, $current_class, $current_scope);
			$method = find_method($class_name, $node->children[1]);
			if($method) return $method['ret'] ?? '';

		} else if($node->kind == \ast\AST_METHOD_CALL) {
			$class_name = find_class_name($file, $node, $namespace, $current_class, $current_scope);
			if($class_name) {
				$method_name = $node->children[1];
				$method = find_method($class_name, $method_name);
				if($method === false) {
					Log::err(Log::EUNDEF, "call to undeclared method {$class_name}->{$method_name}()", $file, $node->lineno);
				} else if($method != 'dynamic') {
					return $method['ret'];
				}
			}
		}
	}
	return '';
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
