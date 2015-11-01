<?php declare(strict_types=1);
namespace Phan\Language\Type;

use \Phan\Deprecated;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\Type;
use \Phan\Log;
use \ast\Node;

class NodeTypeKindVisitor extends KindVisitorImplementation {
    use \Phan\Language\AST;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exits.
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

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     */
    public function visit(Node $node) : Type {
        return Type::none();
    }

    /**
     * Visit a node with kind `\ast\AST_ARRAY`
     */
    public function visitArray(Node $node) : Type {
        if(!empty($node->children)
            && $node->children[0] instanceof Node
            && $node->children[0]->kind == \ast\AST_ARRAY_ELEM
        ) {
            $element_types = [];

            // Check the first 5 (completely arbitrary) elements
            // and assume the rest are the same type
            for($i=0; $i<5; $i++) {
                // Check to see if we're out of elements
                if(empty($node->children[$i])) {
                    break;
                }

                if($node->children[$i]->children[0] instanceof Node) {
                    $element_types[] =
                        Type::typeFromNode(
                            $this->context,
                            $node->children[$i]->children[0]
                        );
                } else {
                    $element_types[] =
                        new Type([gettype($node->children[$i]->children[0])]);
                }
            }

            $element_types =
                array_values(array_unique($element_types));

            if(count($element_types) == 1) {
                return Type::typeFromString(
                    Deprecated::mkgenerics((string)$element_types[0])
                );
            }
        }

        return new Type(['array']);
    }

    /**
     * Visit a node with kind `\ast\AST_BINARY_OP`
     */
    public function visitBinaryOp(Node $node) : Type {
        if($node->kind == \ast\AST_BINARY_OP) {
            $node_flags = $node->flags;
        } else {
            $node_flags = $node->kind;
        }

        // $taint = var_taint_check($file, $node, $current_scope);

        return
            (new Element($node))->acceptFlagVisitor(
                new NodeTypeBinaryOpFlagVisitor($this->context)
            );
    }

    /**
     * Visit a node with kind `\ast\AST_GREATER`
     */
    public function visitGreater(Node $node) : Type {
        return $this->visitBinaryOp($node);
    }

    /**
     * Visit a node with kind `\ast\AST_GREATER_EQUAL`
     */
    public function visitGreaterEqual(Node $node) : Type {
        return $this->visitBinaryOp($node);
    }

    /**
     * Visit a node with kind `\ast\AST_CAST`
     */
    public function visitCast(Node $node) : Type {
        // $taint = var_taint_check($file, $node->children[0], $current_scope);

        switch($node->flags) {
        case \ast\flags\TYPE_NULL:
            return new Type(['null']);
        case \ast\flags\TYPE_BOOL:
            // $taint = false;
            return new Type(['bool']);
        case \ast\flags\TYPE_LONG:
            // $taint = false;
            return new Type(['int']);
        case \ast\flags\TYPE_DOUBLE:
            // $taint = false;
            return new Type(['float']);
        case \ast\flags\TYPE_STRING:
            return new Type(['string']);
        case \ast\flags\TYPE_ARRAY:
            return new Type(['array']);
        case \ast\flags\TYPE_OBJECT:
            return new Type(['object']);
        default:
            Log::err(
                Log::EFATAL,
                "Unknown type (".$node->flags.") in cast"
            );
        }
    }

    /**
     * Visit a node with kind `\ast\AST_NEW`
     */
    public function visitNew(Node $node) : Type {

        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        if($class_name) {
            $class_fqsen = FQSEN::fromContextAndString(
                $this->context,
                $class_name
            );

            if ($this->context->getCodeBase()->hasClassWithFQSEN($class_fqsen)) {
                return $this->context->getCodeBase()->getClassByFQSEN(
                    $class_fqsen
                )->getType();
            }
        }

        return new Type(['object']);
    }

    /**
     * Visit a node with kind `\ast\AST_DIM`
     */
    public function visitDim(Node $node) : Type {
        // $taint = var_taint_check($file, $node->children[0], $current_scope);

        $type = self::typeFromNode($this->context, $node->children[0]);

        if ($type->hasAnyType()) {
            // TODO
            $gen = generics($type);
            if(empty($gen)) {
                if($type!=='null'
                    // TODO
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
                            $this->context->getFile(),
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

    /**
     * Visit a node with kind `\ast\AST_VAR`
     */
    public function visitVar(Node $node) : Type {
        return var_type(
            $file,
            $node,
            $current_scope,
            $taint
        );
    }

    /**
     * Visit a node with kind `\ast\AST_ENCAPS_LIST`
     */
    public function visitEncapsList(Node $node) : Type {
			foreach($node->children as $encap) {
				if($encap instanceof Node) {
                    if(var_taint_check($file, $encap, $current_scope)) {
                        $taint = true;
                    }
				}
			}
			return new Type(['string']);
    }

    /**
     * Visit a node with kind `\ast\AST_CONST`
     */
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

    /**
     * Visit a node with kind `\ast\AST_CLASS_CONST`
     */
    public function visitClassConst(Node $node) : Type {
        if($node->children[1] == 'class') {
            return new Type(['string']); // class name fetch
        }

        $class_name =
            $this->astClassNameFromNode($this->context, $node);

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
                $this->context->getFile(),
                $node->lineno
            );
            return Type::none();
        }
        // TODO
        return $classes[$ltemp]['constants'][$node->children[1]]['type'];
    }

    /**
     * Visit a node with kind `\ast\AST_PROP`
     */
    public function visitProp(Node $node) : Type {
        if($node->children[0]->kind == \ast\AST_VAR) {
            $class_name =
                $this->astClassNameFromNode($this->context, $node);

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

    /**
     * Visit a node with kind `\ast\AST_STATIC_PROP`
     */
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


    /**
     * Visit a node with kind `\ast\AST_CALL`
     */
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

    /**
     * Visit a node with kind `\ast\AST_STATIC_CALL`
     */
    public function visitStaticCall(Node $node) : Type {
        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        $method = find_method($class_name, $node->children[1]);
        if($method) {
            // TODO
            return $method['ret'] ?? '';
        }
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD_CALL`
     */
    public function visitMethodCall(Node $node) : Type {
        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        if($class_name) {
            $method_name = $node->children[1];
            $method = find_method($class_name, $method_name);
            if($method === false) {
                Log::err(
                    Log::EUNDEF,
                    "call to undeclared method {$class_name}->{$method_name}()",
                    $this->context->getFile(),
                    $node->lineno
                );
            } else if($method != 'dynamic') {
                // TODO
                return $method['ret'];
            }
        }
    }

}
