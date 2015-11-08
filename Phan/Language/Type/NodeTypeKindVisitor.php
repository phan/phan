<?php declare(strict_types=1);
namespace Phan\Language\Type;

use \Phan\Debug;
use \Phan\Deprecated;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\UnionType;
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
    public function visit(Node $node) : UnionType {
        return UnionType::none();
    }

    /**
     * Visit a node with kind `\ast\AST_ARRAY`
     */
    public function visitArray(Node $node) : UnionType {
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
                        UnionType::typeFromNode(
                            $this->context,
                            $node->children[$i]->children[0]
                        );
                } else {
                    $element_types[] =
                        new UnionType([gettype($node->children[$i]->children[0])]);
                }
            }

            $element_types =
                array_values(array_unique($element_types));

            if(count($element_types) == 1) {
                return UnionType::typeFromString(
                    Deprecated::mkgenerics((string)$element_types[0])
                );
            }
        }

        return new UnionType(['array']);
    }

    /**
     * Visit a node with kind `\ast\AST_BINARY_OP`
     */
    public function visitBinaryOp(Node $node) : UnionType {
        if($node->kind == \ast\AST_BINARY_OP) {
            $node_flags = $node->flags;
        } else {
            $node_flags = $node->kind;
        }

        return
            (new Element($node))->acceptFlagVisitor(
                new NodeTypeBinaryOpFlagVisitor($this->context)
            );
    }

    /**
     * Visit a node with kind `\ast\AST_GREATER`
     */
    public function visitGreater(Node $node) : UnionType {
        return $this->visitBinaryOp($node);
    }

    /**
     * Visit a node with kind `\ast\AST_GREATER_EQUAL`
     */
    public function visitGreaterEqual(Node $node) : UnionType {
        return $this->visitBinaryOp($node);
    }

    /**
     * Visit a node with kind `\ast\AST_CAST`
     */
    public function visitCast(Node $node) : UnionType {
        switch($node->flags) {
        case \ast\flags\TYPE_NULL:
            return new UnionType(['null']);
        case \ast\flags\TYPE_BOOL:
            // $taint = false;
            return new UnionType(['bool']);
        case \ast\flags\TYPE_LONG:
            // $taint = false;
            return new UnionType(['int']);
        case \ast\flags\TYPE_DOUBLE:
            // $taint = false;
            return new UnionType(['float']);
        case \ast\flags\TYPE_STRING:
            return new UnionType(['string']);
        case \ast\flags\TYPE_ARRAY:
            return new UnionType(['array']);
        case \ast\flags\TYPE_OBJECT:
            return new UnionType(['object']);
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
    public function visitNew(Node $node) : UnionType {
        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        assert(!empty($class_name), "Class name cannot be empty");

        if(empty($class_name)) {
            return new UnionType(['object']);
        }

        $class_fqsen = FQSEN::fromContextAndString(
            $this->context,
            $class_name
        );

        if ($this->context->getCodeBase()->hasClassWithFQSEN($class_fqsen)) {
            return $this->context->getCodeBase()->getClassByFQSEN(
                $class_fqsen
            )->getUnionType();
        }
    }

    /**
     * Visit a node with kind `\ast\AST_DIM`
     */
    public function visitDim(Node $node) : UnionType {
        $type = self::typeFromNode($this->context, $node->children[0]);

        if ($type->hasAnyUnionType()) {
            $gen = $type->generics();
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
                return UnionType::none();
            }
        } else {
            return UnionType::none();
        }
        return new UnionType([$gen]);
    }

    /**
     * Visit a node with kind `\ast\AST_VAR`
     */
    public function visitVar(Node $node) : UnionType {
        return self::astVarUnionType($this->context, $node);
    }

    /**
     * Visit a node with kind `\ast\AST_ENCAPS_LIST`
     */
    public function visitEncapsList(Node $node) : UnionType {
        /*
        foreach($node->children as $encap) {
            if($encap instanceof Node) {
                if(var_taint_check($file, $encap, $current_scope)) {
                    $taint = true;
                }
            }
        }
         */
        return new UnionType(['string']);
    }

    /**
     * Visit a node with kind `\ast\AST_CONST`
     */
    public function visitConst(Node $node) : UnionType {
        if($node->children[0]->kind == \ast\AST_NAME) {
            if(defined($node->children[0]->children[0])) {
                // return type_map(gettype(constant($node->children[0]->children[0])));
                return new UnionType([
                    gettype(constant($node->children[0]->children[0]))
                ]);
            }
            else {
                // TODO: user-defined constant
                return UnionType::none();
            }
        }

        return UnionType::none();
    }

    /**
     * Visit a node with kind `\ast\AST_CLASS_CONST`
     */
    public function visitClassConst(Node $node) : UnionType {
        $constant_name = $node->children[1];

        if($constant_name == 'class') {
            return new UnionType(['string']); // class name fetch
        }

        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        if(!$class_name) {
            Log::err(
                Log::EUNDEF,
                "Can't access undeclared constant {$class_name}::{$constant_name}",
                $this->context->getFile(),
                $node->lineno
            );

            return UnionType::none();
        }

        $class_fqsen =
            $this->context->getScopeFQSEN()->withClassName(
                $this->context,
                $class_name
            );

        // Make sure the class exists
        if (!$this->context->getCodeBase()->hasClassWithFQSEN($class_fqsen)) {
            Log::err(
                Log::EUNDEF,
                "Can't access undeclared constant {$class_name}::{$constant_name}",
                $this->context->getFile(),
                $node->lineno
            );

            return UnionType::none();
        }

        // Get a reference to the class defining the constant
        $defining_clazz =
            $this->context->getCodeBase()->getClassByFQSEN($class_fqsen);

        // Climb the parent tree to find the definition of the
        // constant
        while(!$defining_clazz->hasConstantWithName($constant_name)) {
            // Make sure the class has a parent
            if (!$defining_clazz->hasParentClassFQSEN()) {
                return UnionType::none();
            }

            // Make sure that parent exists
            if (!$this->context->getCodeBase()->hasClassWithFQSEN(
                $defining_clazz->getParentClassFQSEN()
            )) {
                return UnionType::none();
            }

            // Climb to that parent
            $defining_clazz = $this->context->getCodeBase()
                ->getClassByFQSEN($defining_clazz->getParentClassFQSEN());
        }

        if (!$defining_clazz
            || !$defining_clazz->hasConstantWithName($constant_name)
        ) {
            Log::err(
                Log::EUNDEF,
                "Can't access undeclared constant {$class_name}::{$constant_name}",
                $this->context->getFile(),
                $node->lineno
            );
            return UnionType::none();
        }

        return $defining_clazz
            ->getConstantWithName($constant_name)
            ->getUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_PROP`
     */
    public function visitProp(Node $node) : UnionType {
        return $this->visitStaticProp($node);

        /*
        if($node->children[0]->kind != \ast\AST_VAR) {
            return UnionType::none();
        }

        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        if($class_name && !($node->children[1] instanceof Node)) {
            $ltemp = find_property($file, $node, $class_name, $node->children[1], $class_name, false);
            if(empty($ltemp)) {
                return UnionType::none();
            }
            // TODO
            return $classes[$ltemp]['properties'][$node->children[1]]['type'];
        }
         */
    }

    /**
     * Visit a node with kind `\ast\AST_STATIC_PROP`
     */
    public function visitStaticProp(Node $node) : UnionType {
        if($node->children[0]->kind != \ast\AST_NAME) {
            return UnionType::none();
        }

        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        if(!($class_name
            && !($node->children[1] instanceof Node))
        ) {
            return UnionType::none();
        }

        $class_fqsen =
            $this->context->getScopeFQSEN()->withClassName(
                $class_name
            );

        assert(
            $this->context->getCodeBase()->hasClassWithFQSEN($class_fqsen),
            "Class $class_fqsen must exist"
        );

        $clazz = $this->context->getCodeBase()->getClassByFQSEN(
            $class_fqsen
        );

        $property_name = $node->children[1];

        // Property not found :(
        if (!$clazz->hasPropertyWithName($property_name)) {
            return UnionType::none();
        }

        $property =
            $clazz->getPropertyWithName($property_name);

        return $property->getUnionType();
    }


    /**
     * Visit a node with kind `\ast\AST_CALL`
     */
    public function visitCall(Node $node) : UnionType {
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
                return new UnionType([$func['ret'] ?? '']);
            }
        } else {
            // TODO: Handle $func() and other cases that get here
        }
    }

    /**
     * Visit a node with kind `\ast\AST_STATIC_CALL`
     */
    public function visitStaticCall(Node $node) : UnionType {
        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        $method_name = $node->children[1];

        $method_fqsen = $this->context->getScopeFQSEN()
            ->withClassName($this->context, $class_name)
            ->withMethodName($this->context, $method_name);

        if (!$this->context->getCodeBase()->getMethodByFQSEN(
            $method_fqsen
        )) {
            return UnionType::none();
        }

        $method = $this->context->getCodeBase()->getMethodByFQSEN(
            $method_fqsen
        );

        return $method->getUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD_CALL`
     */
    public function visitMethodCall(Node $node) : UnionType {
        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        if (empty($class_name)) {
            return UnionType::none();
        }

        $class_fqsen =
            $this->context->getScopeFQSEN()->withClassName(
                $this->context,
                $class_name
            );

        assert(
            $this->context->getCodeBase()->hasClassWithFQSEN($class_fqsen),
            "Class $class_fqsen must exist"
        );

        $clazz = $this->context->getCodeBase()->getClassByFQSEN(
            $class_fqsen
        );

        $method_name = $node->children[1];

        $method_fqsen = $clazz->getFQSEN()->withMethodName(
            $this->context,
            $method_name
        );

        if (!$this->context->getCodeBase()->hasMethodWithFQSEN(
            $method_fqsen
        )) {
            Log::err(
                Log::EUNDEF,
                "call to undeclared method {$class_fqsen}->{$method_name}()",
                $this->context->getFile(),
                $node->lineno
            );
        }

        $method = $this->context->getCodeBase()->getMethodByFQSEN(
            $method_fqsen
        );

        // TODO: What's dynamic mean?
        if (!$method->isDynamic()) {
            // TODO: What's ret?
            //return $method['ret'];
            return $method->getUnionType();
        }

        return $method->getUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_ASSIGN`
     */
    public function visitAssign(Node $node) : UnionType {
        return UnionType::typeFromNode(
            $this->context,
            $node->children[1]
        );
    }

    /**
     * Visit a node with kind `\ast\AST_UNARY_MINUS`
     */
    public function visitUnaryMinus(Node $node) : UnionType {
        return UnionType::typeForObject($node->children[0]);
    }

}
