<?php declare(strict_types=1);
namespace Phan\Language\Type;

use \Phan\Debug;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\Type;
use \Phan\Language\Type\{
    ArrayType,
    FloatType,
    IntType,
    MixedType,
    NoneType,
    NullType,
    StringType
};
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
        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_ARRAY`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
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

                if($node->children[$i]->children['value'] instanceof Node) {
                    $element_types[] =
                        UnionType::fromNode(
                            $this->context,
                            $node->children[$i]->children['value']
                        );
                } else {
                    $element_types[] =
                        Type::fromObject(
                            $node->children[$i]->children['value']
                        )->asUnionType();
                }
            }

            $element_types =
                array_values(array_unique($element_types));

            if(count($element_types) == 1) {
                return $element_types[0]->asGenericTypes();
            }
        }

        return ArrayType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_BINARY_OP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitBinaryOp(Node $node) : UnionType {
        return
            (new Element($node))->acceptFlagVisitor(
                new NodeTypeBinaryOpFlagVisitor($this->context)
            );
    }

    /**
     * Visit a node with kind `\ast\AST_GREATER`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitGreater(Node $node) : UnionType {
        return $this->visitBinaryOp($node);
    }

    /**
     * Visit a node with kind `\ast\AST_GREATER_EQUAL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitGreaterEqual(Node $node) : UnionType {
        return $this->visitBinaryOp($node);
    }

    /**
     * Visit a node with kind `\ast\AST_CAST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitCast(Node $node) : UnionType {
        switch($node->flags) {
        case \ast\flags\TYPE_NULL:
            return NullType::instance()->asUnionType();
        case \ast\flags\TYPE_BOOL:
            return BoolType::instance()->asUnionType();
        case \ast\flags\TYPE_LONG:
            return IntType::instance()->asUnionType();
        case \ast\flags\TYPE_DOUBLE:
            return FloatType::instance()->asUnionType();
        case \ast\flags\TYPE_STRING:
            return StringType::instance()->asUnionType();
        case \ast\flags\TYPE_ARRAY:
            return ArrayType::instance()->asUnionType();
        case \ast\flags\TYPE_OBJECT:
            return ObjectType::instance()->asUnionType();
        default:
            Log::err(
                Log::EFATAL,
                "Unknown type (".$node->flags.") in cast"
            );
        }
    }

    /**
     * Visit a node with kind `\ast\AST_NEW`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitNew(Node $node) : UnionType {
        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        if(empty($class_name)) {
            return ObjectType::instance()->asUnionType();
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

        assert(false,
            "Class $class_fqsen not found at {$this->context}");

        return ObjectType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_DIM`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitDim(Node $node) : UnionType {
        $union_type =
            UnionType::fromNode(
                $this->context,
                $node->children['expr']
            );

        if ($union_type->isEmpty()) {
            return new UnionType();
        }

        // Figure out what the types of accessed array
        // elements would be
        $generic_types =
            $union_type->asNonGenericTypes();

        // If we have generics, we're all set
        if(!$generic_types->isEmpty()) {
            return $generic_types;
        }

        // If the only type is null, we don't know what
        // accessed items will be
        if ($union_type->isType(NullType::instance())) {
            return new UnionType();
        }

        $element_types = new UnionType();

        // You can access string characters via array index,
        // so we'll add the string type to the result if we're
        // indexing something that could be a string
        if ($union_type->isType(StringType::instance())
            || $union_type->canCastToUnionType(StringType::instance()->asUnionType())
        ) {
            $element_types->addType(StringType::instance());
        }

        // array offsets work on strings, unfortunately
        // Double check that any classes in the type don't
        // have ArrayAccess
        $array_access_type = new Type('ArrayAccess', '\\');

        // Hunt for any types that are viable class names and
        // see if they inherit from ArrayAccess
        foreach ($union_type as $type) {

            if ($type->isNativeType()) {
                continue;
            }

            $class_fqsen = FQSEN::fromFullyQualifiedString(
                (string)$type
            );

            // If we can't find the class, the type probably
            // wasn't a class.
            if (!$this->context->getCodeBase()->hasClassWithFQSEN(
                $class_fqsen
            )) {
                continue;
            }

            $clazz =
                $this->context->getCodeBase()->getClassByFQSEN($class_fqsen);

            // If the class has type ArrayAccess, it can be indexed
            // as if it were an array. That being said, we still don't
            // know the types of the elements, but at least we don't
            // error out.
            if ($clazz->getUnionType()->hasType($array_access_type)) {
                return $element_types;
            }
        }

        if ($element_types->isEmpty()) {
            Log::err(
                Log::ETYPE,
                "Suspicious array access to $union_type",
                $this->context->getFile(),
                $node->lineno
            );
        }

        return $element_types;
    }

    /**
     * Visit a node with kind `\ast\AST_VAR`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitVar(Node $node) : UnionType {
        return self::astVarUnionType($this->context, $node);
    }

    /**
     * Visit a node with kind `\ast\AST_ENCAPS_LIST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitEncapsList(Node $node) : UnionType {
        return StringType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_CONST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitConst(Node $node) : UnionType {
        if($node->children['name']->kind == \ast\AST_NAME) {
            if(defined($node->children['name']->children['name'])) {
                return Type::fromObject(
                    constant($node->children['name']->children['name'])
                )->asUnionType();
            }
            else {
                // TODO: user-defined constant
                return new UnionType();
            }
        }

        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_CLASS_CONST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitClassConst(Node $node) : UnionType {
        $constant_name = $node->children['const'];

        if($constant_name == 'class') {
            return StringType::instance()->asUnionType(); // class name fetch
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

            return new UnionType();
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

            return new UnionType();
        }

        // Get a reference to the class defining the constant
        $defining_clazz =
            $this->context->getCodeBase()->getClassByFQSEN($class_fqsen);

        // Climb the parent tree to find the definition of the
        // constant
        while(!$defining_clazz->hasConstantWithName($constant_name)) {
            // Make sure the class has a parent
            if (!$defining_clazz->hasParentClassFQSEN()) {
                return new UnionType();
            }

            // Make sure that parent exists
            if (!$this->context->getCodeBase()->hasClassWithFQSEN(
                $defining_clazz->getParentClassFQSEN()
            )) {
                return new UnionType();
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
            return new UnionType();
        }

        return $defining_clazz
            ->getConstantWithName($constant_name)
            ->getUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_PROP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitProp(Node $node) : UnionType {
        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        if(!($class_name
            && !($node->children['prop'] instanceof Node))
        ) {
            return new UnionType();
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

        $property_name = $node->children['prop'];

        // Property not found :(
        if (!$clazz->hasPropertyWithName($property_name)) {
            return new UnionType();
        }

        $property =
            $clazz->getPropertyWithName($property_name);

        return $property->getUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_STATIC_PROP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitStaticProp(Node $node) : UnionType {
        if($node->children['class']->kind != \ast\AST_NAME) {
            return new UnionType();
        }

        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        if(!($class_name
            && !($node->children['prop'] instanceof Node))
        ) {
            return new UnionType();
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

        $property_name = $node->children['prop'];

        // Property not found :(
        if (!$clazz->hasPropertyWithName($property_name)) {
            return new UnionType();
        }

        $property =
            $clazz->getPropertyWithName($property_name);

        return $property->getUnionType();
    }


    /**
     * Visit a node with kind `\ast\AST_CALL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitCall(Node $node) : UnionType {
        if($node->children['expr']->kind !== \ast\AST_NAME) {
            // TODO: Handle $func() and other cases that get here
            return new UnionType();
        }

        $function_name =
            $node->children['expr']->children['name'];

        $function_fqsen = null;

        // If its not fully qualified
        if($node->children['expr']->flags & \ast\flags\NAME_NOT_FQ) {
            // Check to see if we have a mapped name
            if ($this->context->hasNamespaceMapFor(
                T_FUNCTION, $function_name
            )) {
                $function_fqsen =
                    $this->context->getNamespaceMapFor(
                        T_FUNCTION, $function_name
                    );
            } else {
                $function_fqsen =
                    $this->context->getScopeFQSEN()->withMethodName(
                        $this->context, $function_name
                    );
            }

        // If the name is fully qualified
        } else {
            $function_fqsen =
                FQSEN::fromFullyQualifiedString($function_name);
        }

        // If the function doesn't exist, check to see if its
        // a call to a builtin method
        if (!$this->context->getCodeBase()->hasMethodWithFQSEN(
            $function_fqsen
        )) {
            $function_fqsen =
                FQSEN::fromFullyQualifiedString('\\::' . $function_name);
        }

        if (!$this->context->getCodeBase()->hasMethodWithFQSEN($function_fqsen)) {
            // TODO: Log missing builtin?
            return new UnionType();
        }

        $function =
            $this->context->getCodeBase()->getMethodByFQSEN(
                $function_fqsen
            );

        // If this is an internal function, see if we can get
        // its types from the static dataset.
        if ($function->getContext()->isInternal()
            && $function->getUnionType()->isEmpty()
        ) {
            $map = UnionType::builtinFunctionPropertyNameTypeMap(
                $function_fqsen,
                $this->context->getCodeBase()
            );

            return $map[$function_name] ?? new UnionType();
        }

        return $function->getUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_STATIC_CALL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitStaticCall(Node $node) : UnionType {
        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        // assert(!empty($class_name), 'Class name cannot be empty');

        if (!$class_name) {
            return new UnionType();
        }

        $method_name = $node->children['method'];

        // Give up on any complicated nonsense where the
        // method name is a variable such as in
        // `$variable->$function_name()`.
        if ($method_name instanceof Node) {
            return new UnionType();
        }

        // Method names can some times turn up being
        // other method calls.
        assert(is_string($method_name),
            "Method name must be a string. Something else given.");


        $method_fqsen = $this->context->getScopeFQSEN()
            ->withClassName($this->context, $class_name)
            ->withMethodName($this->context, $method_name);

        if (!$this->context->getCodeBase()->hasMethodWithFQSEN(
            $method_fqsen
        )) {
            return new UnionType();
        }

        $method = $this->context->getCodeBase()->getMethodByFQSEN(
            $method_fqsen
        );

        return $method->getUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD_CALL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitMethodCall(Node $node) : UnionType {
        $class_name =
            $this->astClassNameFromNode($this->context, $node);

        if (empty($class_name)) {
            return new UnionType();
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

        $method_name = $node->children['method'];

        // Give up on any complicated nonsense where the
        // method name is a variable such as in
        // `$variable->$function_name()`.
        if ($method_name instanceof Node) {
            return new UnionType();
        }

        // Method names can some times turn up being
        // other method calls.
        assert(is_string($method_name),
            "Method name must be a string. Something else given.");

        if (!$clazz->hasMethodWithName($method_name)) {
            Log::err(
                Log::EUNDEF,
                "call to undeclared method {$class_fqsen}->{$method_name}()",
                $this->context->getFile(),
                $node->lineno
            );

            return new UnionType();
        }

        $method = $clazz->getMethodByName($method_name);

        return $method->getUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_ASSIGN`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitAssign(Node $node) : UnionType {
        $type =
            UnionType::fromNode(
                $this->context,
                $node->children['expr']
            );

        return $type;
    }

    /**
     * Visit a node with kind `\ast\AST_UNARY_OP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitUnaryOp(Node $node) : UnionType {
        return UnionType::fromNode(
            $this->context,
            $node->children['expr']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_UNARY_MINUS`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitUnaryMinus(Node $node) : UnionType {
        return Type::fromObject($node->children['expr'])->asUnionType();
    }

}
