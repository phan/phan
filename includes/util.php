<?php
namespace phan;

function add_class($class_name) {
	global $classes, $internal_arginfo;

	$lc = strtolower($class_name);
	$class = new \ReflectionClass($class_name);
	if($class->isFinal()) $flags = \ast\flags\CLASS_FINAL;
	else if($class->isInterface()) $flags = \ast\flags\CLASS_INTERFACE;
	else if($class->isTrait()) $flags = \ast\flags\CLASS_TRAIT;
	if($class->isAbstract()) $flags |= \ast\flags\CLASS_ABSTRACT;

	$classes[$lc] = ['file'=>'internal',
					 'conditional'=>false,
					 'flags'=>$flags,
					 'lineno'=>0,
					 'endLineno'=>0,
					 'name'=>$class_name,
					 'docComment'=>'', // Internal classes don't have docComments
					 'type'=>'',
					 'traits'=>[]
					];

	foreach($class->getDefaultProperties() as $name=>$value) {
		$prop = new \ReflectionProperty($class_name, $name);
		$classes[$lc]['properties'][strtolower($name)] = [
											'flags' => $prop->getModifiers(),
											'name'  => $name,
											'lineno'=> 0,
											'value' => $value ];
	}

	$classes[$lc]['interfaces'] = $class->getInterfaceNames();
	$classes[$lc]['traits'] = $class->getTraitNames();
	$parents = [];
	$parent = $class->getParentClass();
	if($parent) {
		$temp = $class;
		while($parent = $temp->getParentClass()) {
			$parents[] = $parent->getName();
			$parents = array_merge($parents, $parent->getInterfaceNames());
			$temp = $parent;
		}
	}

	$types = [$class_name];
	$types = array_merge($types, $classes[$lc]['interfaces']);
	$types = array_merge($types, $parents);
	$classes[$lc]['type'] = implode('|', array_unique($types));

	foreach($class->getConstants() as $name=>$value) {
		$classes[$lc]['constants'][strtolower($name)] = [	'name'  => $name,
															'lineno'=> 0,
															'value' => $value ];
	}

	foreach($class->getMethods() as $method) {
		$meth = new \ReflectionMethod($class_name, $method->name);
		$required = $meth->getNumberOfRequiredParameters();
		$optional = $meth->getNumberOfParameters() - $required;
		$lmname = strtolower($method->name);
		$classes[$lc]['methods'][$lmname] = [
                                              'file'=>'internal',
                                              'conditional'=>false,
                                              'flags'=>$meth->getModifiers(),
                                              'lineno'=>0,
                                              'endLineno'=>0,
                                              'name'=>$method->name,
                                              'docComment'=>'',
                                              'required'=>$required,
                                              'optional'=>$optional,
                                              'ret'=>null
		                                    ];
		$arginfo = null;
		if(!empty($internal_arginfo["{$class_name}::{$method->name}"])) {
			$arginfo = $internal_arginfo["{$class_name}::{$method->name}"];
			$classes[$lc]['methods'][$lmname]['ret'] = $arginfo[0];
		}
		foreach($method->getParameters() as $param) {
			$flags = 0;
			if($param->isPassedByReference()) $flags |= \ast\flags\PARAM_REF;
			if($param->isVariadic()) $flags |= \ast\flags\PARAM_VARIADIC;
			$classes[$lc]['methods'][strtolower($method->name)]['params'][] =
				 [ 'file'=>'internal',
				   'flags'=>$flags,
				   'lineno'=>0,
				   'name'=>$param->name,
#				   'type'=>$param->getType(),
				   'type'=>(empty($arginfo) ? null : next($arginfo)),
				   'def'=>null
				 ];

		}
	}
}

function add_internal($internal_classes) {
	global $functions, $internal_arginfo;

	foreach($internal_classes as $class_name) {
		add_class($class_name, 0);
	}
	foreach(get_declared_interfaces() as $class_name) {
		add_class($class_name);
	}
	foreach(get_declared_traits() as $class_name) {
		add_class($class_name);
	}

	foreach(get_defined_functions()['internal'] as $function_name) {
		$function = new \ReflectionFunction($function_name);
		$required = $function->getNumberOfRequiredParameters();
		$optional = $function->getNumberOfParameters() - $required;
		$functions[strtolower($function_name)] = [
			 'file'=>'internal',
			 'avail'=>true,
			 'conditional'=>false,
			 'flags'=>0,
			 'lineno'=>0,
			 'endLineno'=>0,
			 'name'=>$function_name,
			 'docComment'=>'',
			 'required'=>$required,
			 'optional'=>$optional,
			 'ret'=>null,
			 'params'=>[]
	    ];
		add_param_info($function_name);

	}
	foreach(array_keys($internal_arginfo) as $function_name) {
		if(strpos($function_name, ':')!==false) continue;
		$ln = strtolower($function_name);
		$functions[$ln] = [
			 'file'=>'internal',
			 'avail'=>false,
			 'conditional'=>false,
			 'flags'=>0,
			 'lineno'=>0,
			 'endLineno'=>0,
			 'name'=>$function_name,
			 'docComment'=>'',
			 'ret'=>null,
			 'params'=>[]
	    ];
		add_param_info($function_name);
	}
}

function add_param_info($function_name) {
	global $internal_arginfo, $functions;

	$lfn = strtolower($function_name);

	if(!empty($internal_arginfo[$function_name])) {
		// If we have the signature in arginfo.php, use it
		$req = $opt = 0;
		foreach($internal_arginfo[$function_name] as $k=>$v) {
			if($k===0) {
				$functions[$lfn]['ret'] = $v;
				continue;
			}
			$flags = 0;
			if($k[0]=='&') $flags |= \ast\flags\PARAM_REF;
			if(strpos($k,'...')!==false) $flags |= \ast\flags\PARAM_VARIADIC;
			if(strpos($k,'=')!==false) $opt++;
			else $req++;
			$functions[$lfn]['params'][] = [
				'flags'=>$flags,
				'lineno'=>0,
				'name'=>$k,
				'type'=>$v,
				'def'=>null
			];
		}
		$functions[$lfn]['required'] = $req;
		$functions[$lfn]['optional'] = $opt;
		// Check for alternate signatures
		$alt = 1;
		$req = $opt = 0;
		while(!empty($internal_arginfo["$function_name $alt"])) {
			// Copy the main part
			$functions["$lfn $alt"] = $functions[strtolower($function_name)];
			$functions["$lfn $alt"]['params'] = [];
			// Then parse the alternate signature
			foreach($internal_arginfo["$function_name $alt"] as $k=>$v) {
				if($k===0) {
					$functions["$lfn $alt"]['ret'] = $v;
					continue;
				}
				$flags = 0;
				if($k[0]=='&') $flags |= \ast\flags\PARAM_REF;
				if(strpos($k,'...')) $flags |= \ast\flags\PARAM_VARIADIC;
			if(strpos($k,'=')!==false) $opt++;
			else $req++;
				$functions["$lfn $alt"]['params'][] = [
					'flags'=>$flags,
					'lineno'=>0,
					'name'=>$k,
					'type'=>$v,
					'def'=>null
				];
			}
			$functions["$lfn $alt"]['required'] = $req;
			$functions["$lfn $alt"]['optional'] = $opt;
			$alt++;
		}
	} else {
		$function = new \ReflectionFunction($function_name);
		foreach($function->getParameters() as $param) {
			$flags = 0;
			if($param->isPassedByReference()) $flags |= \ast\flags\PARAM_REF;
			if($param->isVariadic()) $flags |= \ast\flags\PARAM_VARIADIC;
			$functions[$lfn]['params'][] = [
				'flags'=>$flags,
				'lineno'=>0,
				'name'=>$param->name,
				'type'=>'',  // perhaps $param->getType() one day when we fix this in PHP
				'def'=>null
			];
		}
	}
}

function check_classes(&$classes) {
	global $namespace_map;

	foreach($classes as $name=>$class) {
		if(strpos($name, ':')!==false) {
			list(,$class_name) = explode(':',$name,2);
			if($class['flags'] & \ast\flags\CLASS_INTERFACE) $class_str = "Interface";
			else if($class['flags'] & \ast\flags\CLASS_TRAIT) $class_str = "Trait";
			else $class_str = "Class";

			$orig = $classes[strtolower($class_name)];
			if($orig['flags'] & \ast\flags\CLASS_INTERFACE) $orig_str = "Interface";
			else if($orig['flags'] & \ast\flags\CLASS_TRAIT) $orig_str = "Trait";
			else $orig_str = "Class";

			if($orig['file'] == 'internal') {
				Log::err(Log::EREDEF, "{$class_str} {$class_name} defined at {$class['file']}:{$class['lineno']} was previously defined as {$orig_str} {$class_name} internally", $class['file'], $class['lineno']);
			} else {
				Log::err(Log::EREDEF, "{$class_str} {$class_name} defined at {$class['file']}:{$class['lineno']} was previously defined as {$orig_str} {$class_name} at {$orig['file']}:{$orig['lineno']}", $class['file'], $class['lineno']);
			}
		} else {
			if($class['file']!=='internal') {
				$parents = [];
				$temp = $class;
				while(!empty($temp['parent'])) {
					if(empty($classes[strtolower($temp['parent'])])) {
						Log::err(Log::EUNDEF, "Trying to inherit from unknown class {$temp['parent']}", $class['file'], $class['lineno']);
						break;
					}
					$temp = $classes[strtolower($temp['parent'])];
					$parents[] = $temp['name'];
					if(!empty($temp['interfaces'])) $parents = array_merge($parents, $temp['interfaces']);
				}
				$types = [$class['name']];
				if(!empty($class['interfaces'])) {
					foreach($class['interfaces'] as $interface) {
						$temp = $namespace_map[T_CLASS][strtolower($interface)] ?? $interface;
						if(empty($classes[strtolower($temp)])) {
							Log::err(Log::EUNDEF, "Trying to implement unknown interface {$temp}", $class['file'], $class['lineno']);
						} else {
							$found = $classes[strtolower($temp)];
							if(!($found['flags'] & \ast\flags\CLASS_INTERFACE)) {
								Log::err(Log::ETYPE, "Trying to implement interface {$found['name']} which is not an interface", $class['file'], $class['lineno']);
							}
						}
					}
					$types = array_merge($types, $class['interfaces']);
				}
				if(!empty($parents)) $types = array_merge($types, $parents);
				// Fill in type from inheritance tree and interfaces
				$classes[$name]['type'] = implode('|', array_unique($types));
			}
		}
	}
}

function check_functions($functions) {
	foreach($functions as $name=>$func) {
		if(strpos($name, ':')!==false) {
			list(,$func_name) = explode(':',$name,2);
			$orig = $functions[strtolower($func_name)];
			if($orig['file'] == 'internal') {
				if($func['conditional'] == true) continue;
				Log::err(Log::EREDEF, "Function {$func_name} defined at {$func['file']}:{$func['lineno']} was previously defined internally", $func['file'], $func['lineno']);
			} else {
				Log::err(Log::EREDEF, "Function {$func_name} defined at {$func['file']}:{$func['lineno']} was previously defined at {$orig['file']}:{$orig['lineno']}", $func['file'], $func['lineno']);
			}
		}
	}
}

function dump_scope($scope) {
	foreach($scope as $k=>$v) {
		echo $k."\n".str_repeat("\u{00AF}",strlen($k))."\n";
		echo " Variables:\n";
		foreach($v['vars'] as $kk=>$vv) {
			echo "\t$kk: {$vv['type']}";
			if($vv['tainted']) {
				echo "(tainted";
				if($vv['tainted_by']) echo ':'.$vv['tainted_by'];
				echo ")";
			}
			if(!empty($vv['param'])) echo " (param: {$vv['param']})";
			echo "\n";
		}
		echo "\n";
	}
}

function dump_functions($type='user') {
	global $classes, $functions;
	$temp = "Global functions";
	echo $temp."\n".str_repeat("\u{00AF}", strlen($temp))."\n";

	foreach($functions as $k=>$func) {
		if($func['file'] != 'internal') {
			echo "{$func['name']}(";
			$pstr = '';
			if(!empty($func['params'])) foreach($func['params'] as $k=>$param) {
				$type = !empty($param['type']) ? "{$param['type']} " : '';
				if($k>$func['required']) $pstr .= '[';
				$pstr .= "{$type}{$param['name']}";
				if($k>$func['required']) $pstr .= ']';
				$pstr .= ', ';
			}
			echo trim($pstr,', ');
			echo ')';
			if(!empty($func['ret'])) echo ':'.$func['ret'];
			echo "\n";
		}
	}
	echo "\n\n";

	foreach($classes as $class=>$entry) {
		if($entry['file']=='internal') continue;
		$temp = "class ".$entry['name'];
		if(!empty($entry['parent'])) $temp .= " extends {$entry['parent']}";
		if(!empty($entry['type'])) $temp .= " types: {$entry['type']}";
		echo $temp."\n".str_repeat("\u{00AF}", strlen($temp))."\n";

		if(!empty($entry['methods'])) foreach($entry['methods'] as $func) {
			if($func['file'] != 'internal') {
				if($func['flags'] & \ast\flags\MODIFIER_STATIC) {
					echo "\t {$classes[$class]['name']}::{$func['name']}(";
				} else {
					echo "\t {$classes[$class]['name']}->{$func['name']}(";
				}
				$pstr = '';
				if(!empty($func['params'])) foreach($func['params'] as $k=>$param) {
					$type = !empty($param['type']) ? "{$param['type']} " : '';
					if($k>$func['required']) $pstr .= '[';
					$pstr .= "{$type}{$param['name']}";
					if($k>$func['required']) $pstr .= ']';
					$pstr .= ', ';
				}
				echo trim($pstr,', ');
				echo ')';
				if(!empty($func['ret'])) echo ':'.$func['ret'];
				echo "\n";
			}
		}
		echo "\n";
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
