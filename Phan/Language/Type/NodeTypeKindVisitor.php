<?php declare(strict_types=1);
namespace Phan\Language\Type;

use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Type;
use \ast\Node;

class NodeTypeKindVisitor extends KindVisitorImplementation {

    /**
     * @var Context
     */
    private $context;

    /**
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(Context $context) {
        $this->context = $context;
    }

    public function visit(Node $node) : Type {
        return Type::none();
    }

    public function visitArray(Node $node) {
        if(!empty($node->children)
            && $node->children[0] instanceof Node
            && $node->children[0]->kind == \ast\AST_ARRAY_ELEM
        ) {
            // Check the first 5 (completely arbitrary) elements
            // and assume the rest are the same type
            $etypes = [];
            for($i=0; $i<5; $i++) {
                if(empty($node->children[$i])) break;
                if($node->children[$i]->children[0] instanceof Node) {
                    $temp_taint = false;
                    $etypes[] =
                        self::typeFromNode(
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
            if(count($types) == 1 && !empty($types[0])
            ) {
                return Type::typeFromString(
                    Deprecated::mkgenerics($types[0]->__toString())
                );
            }
        }

        return new Type(['array']);
    }

    public function visitBinaryOp(Node $node) {
        if($node->kind == \ast\AST_BINARY_OP) {
            $node_flags = $node->flags;
        } else {
            $node_flags = $node->kind;
        }

        $taint = var_taint_check($file, $node, $current_scope);

        return
            (new Element($node))->acceptFlagVisitor(
                new NodeTypeBinaryOpFlagVisitor($this->context)
            );
    }

    public function visitGreater(Node $node) {
        return $this->visitBinaryOp($node);
    }

    public function visitGreaterEqual(Node $node) {
        return $this->visitBinaryOp($node);
    }

    public function visitCast(Node $node) {
        $taint =
            var_taint_check($file, $node->children[0], $current_scope);

        switch($node->flags) {
        case \ast\flags\TYPE_NULL: return new Type(['null']); break;
        case \ast\flags\TYPE_BOOL: $taint = false; return new Type(['bool']); break;
        case \ast\flags\TYPE_LONG: $taint = false; return new Type(['int']); break;
        case \ast\flags\TYPE_DOUBLE: $taint = false; return new Type(['float']); break;
        case \ast\flags\TYPE_STRING: return new Type(['string']); break;
        case \ast\flags\TYPE_ARRAY: return new Type(['array']); break;
        case \ast\flags\TYPE_OBJECT: return new Type(['object']); break;
        default: Log::err(Log::EFATAL, "Unknown type (".$node->flags.") in cast");
        }
    }

    public function visitNew(Node $node) {
        $class_name = find_class_name($file, $node, $namespace, $current_class, $current_scope);
        if($class_name) {
            // TODO
            return $classes[strtolower($class_name)]['type'];
        }
        return new Type(['object']);
    }

    public function visitDim(Node $node) {
        $taint =
            var_taint_check($file, $node->children[0], $current_scope);

        // $type = node_type($file, $namespace, $node->children[0], $current_scope, $current_class);
        $type = self::typeFromNode($context, $node->children[0]);

        // if(!empty($type)) {
        if ($type->hasAnyType()) {
            $gen = generics($type);
            if(empty($gen)) {
                if($type!=='null'
                    && !type_check($type, 'string|ArrayAccess')
                ) {
                    // array offsets work on strings, unfortunately
                    // Double check that any classes in the type don't have ArrayAccess
                    $ok = false;
                    foreach(explode('|', $type) as $t) {
                        if(!empty($t) && !is_native_type($t)) {
                            // TODO
                            if(!empty($classes[strtolower($t)]['type'])) {
                                // TODO
                                if(strpos('|'.$classes[strtolower($t)]['type'].'|','|ArrayAccess|')!==false) {
                                    $ok = true;
                                    break;
                                }
                            }
                        }
                    }
                    if(!$ok) {
                        Log::err(
                            Log::ETYPE,
                            "Suspicious array access to $type",
                            $context->getFile(),
                            $node->lineno
                        );
                    }
                }
                return Type::none();
            }
        } else {
            return Type::none();
        }
        return new Type([$gen]);
    }

    public function visitVar(Node $node) : Type {
        return var_type(
            $file,
            $node,
            $current_scope,
            $taint,
            $check_var_exists
        );
    }

    public function visitEncapsList(Node $node) : Type) {
			foreach($node->children as $encap) {
				if($encap instanceof Node) {
                    if(var_taint_check($file, $encap, $current_scope)) {
                        $taint = true;
                    }
				}
			}
			return new Type(['string']);
    }

    public function visitConst(Node $node) : Type {
        if($node->children[0]->kind == \ast\AST_NAME) {
            if(defined($node->children[0]->children[0])) {
                // return type_map(gettype(constant($node->children[0]->children[0])));
                return new Type([
                    gettype(constant($node->children[0]->children[0]))
                ]);
            }
            else {
                // Todo: user-defined constant
            }
        }
    }

    public function visitClassConst(Node $node) : Type {
        if($node->children[1] == 'class') {
            return new Type(['string']); // class name fetch
        }
        $class_name = find_class_name($file, $node, $namespace, $current_class, $current_scope);
        if(!$class_name) {
            return Type::none();
        }
        $ltemp = strtolower($class_name);
        while($ltemp
            && !array_key_exists($node->children[1],
                // TODO
                $classes[$ltemp]['constants'])
        ) {
            // TODO
            $ltemp = strtolower($classes[$ltemp]['parent']);
            // TODO
            if(empty($classes[$ltemp])) {
                // undeclared class - will be caught elsewhere
                return Type::none();
            }
        }
        if(!$ltemp
            || !array_key_exists($node->children[1],
                // TODO
                $classes[$ltemp]['constants'])
        ) {
            Log::err(
                Log::EUNDEF,
                "can't access undeclared constant {$class_name}::{$node->children[1]}",
                $context->getFile(),
                $node->lineno
            );
            return Type::none();
        }
        // TODO
        return $classes[$ltemp]['constants'][$node->children[1]]['type'];
    }

    public function visitProp(Node $node) : Type {
        if($node->children[0]->kind == \ast\AST_VAR) {
            $class_name = find_class_name($file, $node, $namespace, $current_class, $current_scope);
            if($class_name && !($node->children[1] instanceof Node)) {
                $ltemp = find_property($file, $node, $class_name, $node->children[1], $class_name, false);
                if(empty($ltemp)) {
                    return Type::none();
                }
                // TODO
                return $classes[$ltemp]['properties'][$node->children[1]]['type'];
            }
        }
    }


    public function visitStaticProp(Node $node) : Type {
        if($node->children[0]->kind == \ast\AST_NAME) {
            $class_name = qualified_name($file, $node->children[0], $namespace);
            if($class_name && !($node->children[1] instanceof Node)) {
                $ltemp = find_property($file, $node, $class_name, $node->children[1], $class_name, false);
                if(empty($ltemp)) {
                    return Type::none();
                }
                // TODO
                return $classes[$ltemp]['properties'][$node->children[1]]['type'];
            }
        }
    }


    public function visitCall(Node $node) : Type {
        if($node->children[0]->kind == \ast\AST_NAME) {
            $func_name = $node->children[0]->children[0];
            if($node->children[0]->flags & \ast\flags\NAME_NOT_FQ) {
                $func = $namespace_map[T_FUNCTION][$file][strtolower($namespace.$func_name)] ??
                    $namespace_map[T_FUNCTION][$file][strtolower($func_name)] ??
                    $functions[strtolower($namespace.$func_name)] ??
                    $functions[strtolower($func_name)] ??  null;
            } else {
                $func = $functions[strtolower($func_name)] ?? null;
            }
            if($func['file'] == 'internal' && empty($func['ret'])) {
                if(!empty($internal_arginfo[$func_name])) {
                    // TODO
                    return $internal_arginfo[$func_name][0] ?? '';
                }
            } else {
                return new Type([$func['ret'] ?? '']);
            }
        } else {
            // TODO: Handle $func() and other cases that get here
        }
    }

    public function visitStaticCall(Node $node) : Type {
        $class_name = find_class_name($file, $node, $namespace, $current_class, $current_scope);
        $method = find_method($class_name, $node->children[1]);
        if($method) {
            // TODO
            return $method['ret'] ?? '';
        }
    }

    public function visitMethodCall(Node $node) : Type {
        $class_name = find_class_name($file, $node, $namespace, $current_class, $current_scope);
        if($class_name) {
            $method_name = $node->children[1];
            $method = find_method($class_name, $method_name);
            if($method === false) {
                Log::err(
                    Log::EUNDEF,
                    "call to undeclared method {$class_name}->{$method_name}()",
                    $context->getFile(),
                    $node->lineno
                );
            } else if($method != 'dynamic') {
                // TODO
                return $method['ret'];
            }
        }
    }
}
