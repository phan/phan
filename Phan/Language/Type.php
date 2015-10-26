<?php
declare(strict_types=1);
namespace Phan\Language;

/**
 * Static data defining type names for builtin classes
 */
$BUILTIN_CLASS_TYPES =
    require(__DIR__.'/Type/BuiltinClassTypes.php');

/**
 * Static data defining types for builtin functions
 */
$BUILTIN_FUNCTION_ARGUMENT_TYPES =
    require(__DIR__.'/Type/BuiltinFunctionArgumentTypes.php');

class Type {

    /**
     * @var string[]
     * A list of type names
     */
    private $type_name_list = [];

    /**
     * @param string[] $type_name_list
     * A list of type names
     */
    public function __construct(array $type_name_list) {
        $this->type_name_list = array_map(function(string $type_name) {
            return $this->toCanonicalName($type_name);
        }, $type_name_list);
    }

    public function __toString() : string {
        return implode('|', $this->type_name_list);
    }

    /**
     * Get a Type specifying that there are no
     * known types on a thing.
     */
    public static function none() : Type {
        return new Type([]);
    }

    /**
     * @return Type
     * A Type for the given object
     */
    public static function typeForObject($object) {
        return new Type([gettype($object)]);
    }

    /**
     * @param Context $context
     * @param \ast\Node|string $node
     * @param bool $taint
     *
     * @return Type
     */
    public static function typeForASTNode(
        Context $context,
        $node,
        bool &$taint
    ) : Type {

        if(!($node instanceof \ast\Node)) {
            if($node === null) {
                return new Type([]);
            }
            return self::typeForObject($node);
        }


		if($node->kind == \ast\AST_ARRAY) {
            if(!empty($node->children)
                && $node->children[0] instanceof \ast\Node
                && $node->children[0]->kind == \ast\AST_ARRAY_ELEM
            ) {
                // Check the first 5 (completely arbitrary) elements
                // and assume the rest are the same type
				$etypes = [];
				for($i=0; $i<5; $i++) {
					if(empty($node->children[$i])) break;
					if($node->children[$i]->children[0] instanceof \ast\Node) {
                        $etypes[] =
                            self::typeForASTNode(
                                $context,
                                $node->children[$i]->children[0],
                                $temp_taint
                            );
					} else {
                        $etypes[] =
                            new Type([$node->children[$i]->children[0]]);
					}
				}
				$types = array_unique($etypes);
                if(count($types) == 1
                    && !empty($types[0])
                ) {
                    return self::mkgenerics($types[0]);
                }
            }

			return new Type(['array']);

        } else if($node->kind == \ast\AST_BINARY_OP
            || $node->kind == \ast\AST_GREATER
            || $node->kind == \ast\AST_GREATER_EQUAL
        ) {
            if($node->kind == \ast\AST_BINARY_OP) {
                $node_flags = $node->flags;
            } else {
                $node_flags = $node->kind;
            }

			$taint = var_taint_check($file, $node, $current_scope);

			switch($node_flags) {
				// Always a string from a concat
				case \ast\flags\BINARY_CONCAT:
					$temp_taint = false;

                    node_type(
                        $file,
                        $namespace,
                        $node->children[0],
                        $current_scope,
                        $current_class,
                        $temp_taint
                    );
					if($temp_taint) {
						$taint = true;
						return 'string';
					}
                    node_type(
                        $file,
                        $namespace,
                        $node->children[1],
                        $current_scope,
                        $current_class,
                        $temp_taint
                    );

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
				case \ast\AST_GREATER:
				case \ast\AST_GREATER_EQUAL:
					$temp = node_type($file, $namespace, $node->children[0], $current_scope, $current_class);
					if(!$temp) $left = '';
					else $left = type_map($temp);
					$temp = node_type($file, $namespace, $node->children[1], $current_scope, $current_class);
					if(!$temp) $right = '';
					else $right = type_map($temp);
					$taint = false;
					// If we have generics and no non-generics on the left and the right is not array-like ...
					if(!empty(generics($left)) && empty(nongenerics($left)) && !type_check($right, 'array')) {
						Log::err(Log::ETYPE, "array to $right comparison", $file, $node->lineno);
					} else // and the same for the right side
						if(!empty(generics($right)) && empty(nongenerics($right)) && !type_check($left, 'array')) {
						Log::err(Log::ETYPE, "$left to array comparison", $file, $node->lineno);
					}
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
					// fast-track common cases
					if($left=='int' && $right == 'int') return 'int';
					if(($left=='int' || $left=='float') && ($right=='int' || $right=='float')) return 'float';

					$left_is_array = (!empty(generics($left)) && empty(nongenerics($left)));
					$right_is_array = (!empty(generics($right)) && empty(nongenerics($right)));

					if($left_is_array && !type_check($right, 'array')) {
						Log::err(Log::ETYPE, "invalid operator: left operand is array and right is not", $file, $node->lineno);
						return '';
					} else if($right_is_array && !type_check($left, 'array')) {
						Log::err(Log::ETYPE, "invalid operator: right operand is array and left is not", $file, $node->lineno);
						return '';
					} else if($left_is_array || $right_is_array) {
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
				case \ast\flags\TYPE_OBJECT: return 'object'; break;
				default: Log::err(Log::EFATAL, "Unknown type (".$node->flags.") in cast");
			}
		} else if($node->kind == \ast\AST_NEW) {
			$class_name = find_class_name($file, $node, $namespace, $current_class, $current_scope);
			if($class_name) return $classes[strtolower($class_name)]['type'];
			return 'object';

		} else if($node->kind == \ast\AST_DIM) {
			$taint = var_taint_check($file, $node->children[0], $current_scope);
			$type = node_type($file, $namespace, $node->children[0], $current_scope, $current_class);
			if(!empty($type)) {
				$gen = generics($type);
				if(empty($gen)) {
					if($type!=='null' && !type_check($type, 'string|ArrayAccess')) {  // array offsets work on strings, unfortunately
						// Double check that any classes in the type don't have ArrayAccess
						$ok = false;
						foreach(explode('|', $type) as $t) {
							if(!empty($t) && !is_native_type($t)) {
								if(!empty($classes[strtolower($t)]['type'])) {
									if(strpos('|'.$classes[strtolower($t)]['type'].'|','|ArrayAccess|')!==false) {
										$ok = true;
										break;
									}
								}
							}
						}
						if(!$ok) Log::err(Log::ETYPE, "Suspicious array access to $type", $file, $node->lineno);
					}
					return '';
				}
			} else {
				return '';
			}
			return $gen;

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

		} else if($node->kind == \ast\AST_CLASS_CONST) {
			if($node->children[1] == 'class') return 'string'; // class name fetch
			$class_name = find_class_name($file, $node, $namespace, $current_class, $current_scope);
			if(!$class_name) return '';
			$ltemp = strtolower($class_name);
			while($ltemp && !array_key_exists($node->children[1], $classes[$ltemp]['constants'])) {
				$ltemp = strtolower($classes[$ltemp]['parent']);
				if(empty($classes[$ltemp])) return ''; // undeclared class - will be caught elsewhere
			}
			if(!$ltemp || !array_key_exists($node->children[1], $classes[$ltemp]['constants'])) {
				Log::err(Log::EUNDEF, "can't access undeclared constant {$class_name}::{$node->children[1]}", $file, $node->lineno);
				return '';
			}
			return $classes[$ltemp]['constants'][$node->children[1]]['type'];

		} else if($node->kind == \ast\AST_PROP) {
			if($node->children[0]->kind == \ast\AST_VAR) {
				$class_name = find_class_name($file, $node, $namespace, $current_class, $current_scope);
				if($class_name && !($node->children[1] instanceof \ast\Node)) {
					$ltemp = find_property($file, $node, $class_name, $node->children[1], $class_name, false);
					if(empty($ltemp)) return '';
					return $classes[$ltemp]['properties'][$node->children[1]]['type'];
				}
			}
		} else if($node->kind == \ast\AST_STATIC_PROP) {
			if($node->children[0]->kind == \ast\AST_NAME) {
				$class_name = qualified_name($file, $node->children[0], $namespace);
				if($class_name && !($node->children[1] instanceof \ast\Node)) {
					$ltemp = find_property($file, $node, $class_name, $node->children[1], $class_name, false);
					if(empty($ltemp)) return '';
					return $classes[$ltemp]['properties'][$node->children[1]]['type'];
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

        return '';
	}

    /**
     * Looks for any suspicious GPSC variables in the given node
     *
     * @return bool
     */
    private function isTainted(
        Context $context,
        $node,
        string $current_scope
    ) : bool {

        // global $scope, $tainted_by;

        static $tainted = [
            '_GET' => '*',
            '_POST' => '*',
            '_COOKIE' => '*',
            '_REQUEST' => '*',
            '_FILES' => '*',
            '_SERVER' => [
                'QUERY_STRING',
                'HTTP_HOST',
                'HTTP_USER_AGENT',
                'HTTP_ACCEPT_ENCODING',
                'HTTP_ACCEPT_LANGUAGE',
                'REQUEST_URI',
                'PHP_SELF',
                'argv'
            ]
        ];

        if(!$node instanceof \ast\Node) {
            return false;
        }

        $parent = $node;
        while(($node instanceof \ast\Node)
            && ($node->kind != \ast\AST_VAR)
            && ($node->kind != \ast\AST_MAGIC_CONST)
        ) {
            $parent = $node;
            if(empty($node->children[0])) {
                break;
            }
            $node = $node->children[0];
        }

        if($parent->kind == \ast\AST_DIM) {
            if($node->children[0] instanceof \ast\Node) {
                // $$var or something else dynamic is going on, not direct access to a suspivious var
                return false;
            }
            foreach($tainted as $name=>$index) {
                if($node->children[0] === $name) {
                    if($index=='*') {
                        return true;
                    }
                    if($parent->children[1] instanceof \ast\Node) {
                        // Dynamic index, give up
                        return false;
                    }
                    if(in_array($parent->children[1], $index, true)) {
                        return true;
                    }
                }
            }
        } else if($parent->kind == \ast\AST_VAR
            && !($parent->children[0] instanceof \ast\Node)
        ) {
            $variable_name = $parent->children[0];
            if (empty($context->getScope()->getVariableNameList()[$variable_name])) {
            }

            if(empty($scope[$current_scope]['vars'][$parent->children[0]])) {
                if(!superglobal($parent->children[0]))
                    Log::err(
                        Log::EVAR,
                        "Variable \${$parent->children[0]} is not defined",
                        $file,
                        $parent->lineno
                    );
            } else {
                if(!empty($scope[$current_scope]['vars'][$parent->children[0]]['tainted'])
                ) {
                    $tainted_by =
                        $scope[$current_scope]['vars'][$parent->children[0]]['tainted_by'];
                    return true;
                }
            }
        }

        return false;
    }


    public static function builtinClassPropertyType(
        string $class_name,
        string $property_name
    ) : Type {
        $class_property_type_map =
            $BUILTIN_CLASS_TYPES[strtolower($class_name)]['properties'];

        $property_type_name =
            $class_property_type_map[$property_name];

        return new Type($property_type_name);
    }

    /**
     * @return Type[]
     * A list of types for parameters associated with the
     * given builtin function with the given name
     */
    public static function builtinFunctionPropertyNameTypeMap(
        FQSEN $function_fqsen
    ) : array {
        $type_name_struct =
            $BUILTIN_FUNCTION_ARGUMENT_TYPES[$function_fqsen->__toString()];

        if (!$type_name_struct) {
            return [];
        }

        $type_return = array_shift($type_name_struct);
        $name_type_name_map = $type_name_struct;

        $property_name_type_map = [];

        foreach ($name_type_name_map as $name => $type_name) {
            $property_name_type_map[$name] =
                new Type($type_name);
        }

        return $property_name_type_map;
    }

    /**
     * @return bool
     * True if a builtin with the given FQSEN exists, else
     * flase.
     */
    public static function builtinExists(FQSEN $fqsen) : bool {
        return !empty(
            $BUILTIN_FUNCTION_ARGUMENT_TYPES[$fqsen->__toString()]
        );
    }

    /**
     * @param string $type_name
     * Any type name
     *
     * @return string
     * A canonical name for the given type name
     */
    private static function toCanonicalName(string $type_name) : string {
        static $repmaps = [
            [
                'integer',
                'double',
                'boolean',
                'false',
                'true',
                'callback',
                'closure',
                'NULL'
            ],
            [
                'int',
                'float',
                'bool',
                'bool',
                'bool',
                'callable',
                'callable',
                'null'
            ]
        ];

        return str_replace(
            $repmaps[0],
            $repmaps[1],
            $type_name
        );
    }

    /**
     * @return
     * Takes "a|b" and returns "a[]|b[]"
     */
    private static function  mkgenerics(string $str) : string {
        $ret = [];
        foreach(explode('|', $str) as $type) {
            if(empty($type)) continue;
            if($type=='array'
                || $type=='mixed'
                || strpos($type,'[]')!==false
            ) {
                $ret[] = 'array';
            }
            else {
                $ret[] = $type.'[]';
            }
        }

        return implode('|', $ret);
    }
}
