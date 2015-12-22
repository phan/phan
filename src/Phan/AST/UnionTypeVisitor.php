<?php
namespace Phan\AST;

use \Phan\AST\ContextNode;
use \Phan\AST\Visitor\Element;
use \Phan\AST\Visitor\KindVisitorImplementation;
use \Phan\Analyze\BinaryOperatorFlagVisitor;
use \Phan\CodeBase;
use \Phan\Debug;
use \Phan\Exception\AccessException;
use \Phan\Exception\CodeBaseException;
use \Phan\Exception\NodeException;
use \Phan\Exception\TypeException;
use \Phan\Language\Context;
use \Phan\Language\Element\Clazz;
use \Phan\Language\Element\Variable;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\Type;
use \Phan\Language\Type\ArrayType;
use \Phan\Language\Type\BoolType;
use \Phan\Language\Type\CallableType;
use \Phan\Language\Type\FloatType;
use \Phan\Language\Type\GenericArrayType;
use \Phan\Language\Type\IntType;
use \Phan\Language\Type\MixedType;
use \Phan\Language\Type\NativeType;
use \Phan\Language\Type\NullType;
use \Phan\Language\Type\ObjectType;
use \Phan\Language\Type\ResourceType;
use \Phan\Language\Type\ScalarType;
use \Phan\Language\Type\StringType;
use \Phan\Language\Type\VoidType;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * Determine the UnionType associated with a
 * given node
 */
class UnionTypeVisitor extends KindVisitorImplementation {

    /**
     * @var CodeBase
     * The code base within which we're operating
     */
    private $code_base;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exits.
     */
    private $context;

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        $this->context = $context;
        $this->code_base = $code_base;
    }

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param $context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param Node|mixed $node
     * The node for which we'd like to determine its type
     *
     * @return UnionType
     * The UnionType associated with the given node
     * in the given Context within the given CodeBase
     */
    public static function unionTypeFromNode(
        CodeBase $code_base,
        Context $context,
        $node
    ) : UnionType {
        if(!($node instanceof Node)) {
            if($node === null) {
                return new UnionType();
            }

            return Type::fromObject($node)->asUnionType();
        }

        return (new Element($node))->acceptKindVisitor(
            new self($code_base, $context)
        );
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * An AST node we'd like to determine the UnionType
     * for
     *
     * @return UnionType
     * The set of types associated with the given node
     */
    public function visit(Node $node) : UnionType {
        /*
        throw new NodeException($node,
            'Visitor not implemented for node of type '
            . Debug::nodeName($node)
        );
        */
        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_POST_INC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitPostInc(Node $node) : UnionType {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_POST_DEC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitPostDec(Node $node) : UnionType {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_PRE_DEC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitPreDec(Node $node) : UnionType {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_PRE_INC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitPreInc(Node $node) : UnionType {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_CLONE`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitClone(Node $node) : UnionType {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_COALESCE`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitCoalesce(Node $node) : UnionType {
        $union_type = new UnionType();

        $left_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['left']
        );
        $union_type->addUnionType($left_type);

        $right_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right']
        );

        $union_type->addUnionType(
            $right_type
        );

        return $union_type;
    }

    /**
     * Visit a node with kind `\ast\AST_EMPTY`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitEmpty(Node $node) : UnionType {
        return BoolType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_ISSET`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitIsset(Node $node) : UnionType {
        return BoolType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_INCLUDE_OR_EVAL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitIncludeOrEval(Node $node) : UnionType {
        // require() can return arbitrary objects. Lets just
        // say that we don't know what it is and move on
        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_MAGIC_CONST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitMagicConst(Node $node) : UnionType {
        // This is for things like __METHOD__
        return StringType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_ASSIGN_REF`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitAssignRef(Node $node) : UnionType {
        // TODO
        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_SHELL_EXEC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitShellExec(Node $node) : UnionType {
        return StringType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_NAME`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitName(Node $node) : UnionType {
        if ($node->flags & \ast\flags\NAME_NOT_FQ) {
            if ('parent' === $node->children['name']) {
                $class = $this->context->getClassInScope($this->code_base);
                return Type::fromFullyQualifiedString(
                    (string)$class->getParentClassFQSEN()
                )->asUnionType();
            }

            return Type::fromStringInContext(
                $node->children['name'],
                $this->context
            )->asUnionType();
        }

        return Type::fromFullyQualifiedString(
            '\\' . $node->children['name']
        )->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_TYPE`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitType(Node $node) : UnionType {
        switch ($node->flags) {
        case \ast\flags\TYPE_ARRAY:
            return ArrayType::instance()->asUnionType();
        case \ast\flags\TYPE_BOOL:
            return BoolType::instance()->asUnionType();
        case \ast\flags\TYPE_CALLABLE:
            return CallableType::instance()->asUnionType();
        case \ast\flags\TYPE_DOUBLE:
            return FloatType::instance()->asUnionType();
        case \ast\flags\TYPE_LONG:
            return IntType::instance()->asUnionType();
        case \ast\flags\TYPE_NULL:
            return NullType::instance()->asUnionType();
        case \ast\flags\TYPE_OBJECT:
            return ObjectType::instance()->asUnionType();
        case \ast\flags\TYPE_STRING:
            return StringType::instance()->asUnionType();
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($node->flags ?? 0));
            break;
        }
    }

    /**
     * Visit a node with kind `\ast\AST_CONDITIONAL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitConditional(Node $node) : UnionType {
        $union_type = new UnionType();

        // Add the type for the 'true' side
        $union_type->addUnionType(UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['trueExpr'] ??
                $node->children['true'] ?? ''
        ));

        // Add the type for the 'false' side
        $union_type->addUnionType(UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['falseExpr'] ??
                $node->children['false'] ?? ''
        ));

        return $union_type;
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
                    $element_types[] = UnionType::fromNode(
                        $this->context,
                        $this->code_base,
                        $node->children[$i]->children['value']
                    );
                } else {
                    $element_types[] = Type::fromObject(
                        $node->children[$i]->children['value']
                    )->asUnionType();
                }
            }

            $element_types =
                array_values(array_unique($element_types));

            if(count($element_types) == 1) {
                return $element_types[0]->asGenericArrayTypes();
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
        return (new Element($node))->acceptBinaryFlagVisitor(
            new BinaryOperatorFlagVisitor(
                $this->context,
                $this->code_base
            )
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
                'Unknown type (' . $node->flags . ') in cast'
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
        return $this->visitClassNode($node->children['class']);
    }


    /**
     * Visit a node with kind `\ast\AST_INSTANCEOF`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitInstanceOf(Node $node) : UnionType {
        try {
            // Confirm that the right-side exists
            $union_type = $this->visitClassNode(
                $node->children['class']
            );
        } catch (TypeException $exception) {
            // TODO: log it?
        }

        return BoolType::instance()->asUnionType();
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
        $union_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );

        if ($union_type->isEmpty()) {
            return $union_type;
        }

        // Figure out what the types of accessed array
        // elements would be
        $generic_types =
            $union_type->genericArrayElementTypes();

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
        $array_access_type =
            Type::fromNamespaceAndName('\\', 'ArrayAccess');

        // Hunt for any types that are viable class names and
        // see if they inherit from ArrayAccess
        foreach ($union_type->getTypeList() as $type) {

            if ($type->isNativeType()) {
                continue;
            }

            $class_fqsen = FullyQualifiedClassName::fromType($type);

            // If we can't find the class, the type probably
            // wasn't a class.
            if (!$this->code_base->hasClassWithFQSEN(
                $class_fqsen
            )) {
                continue;
            }

            $class = $this->code_base->getClassByFQSEN(
                $class_fqsen
            );

            // If the class has type ArrayAccess, it can be indexed
            // as if it were an array. That being said, we still don't
            // know the types of the elements, but at least we don't
            // error out.
            if ($class->getUnionType()->hasType($array_access_type)) {
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
     * Visit a node with kind `\ast\AST_CLOSURE`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitClosure(Node $node) : UnionType {
        // The type of a closure is the fqsen pointing
        // at its definition
        $closure_fqsen =
            FullyQualifiedFunctionName::fromClosureInContext(
                $this->context
            );

        $type = CallableType::instanceWithClosureFQSEN(
            $closure_fqsen
        )->asUnionType();

        return $type;
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

        // $$var or ${...} (whose idea was that anyway?)
        if(($node->children['name'] instanceof Node)
            && ($node->children['name']->kind == \ast\AST_VAR
            || $node->children['name']->kind == \ast\AST_BINARY_OP)
        ) {
            return MixedType::instance()->asUnionType();
        }

        // This is nonsense. Give up.
        if($node->children['name'] instanceof Node) {
            return new UnionType();
        }

        $variable_name =
            $node->children['name'];

        if (!$this->context->getScope()->hasVariableWithName($variable_name)) {
            if(!Variable::isSuperglobalVariableWithName($variable_name)) {
                Log::err(
                    Log::EVAR,
                    "Variable \$$variable_name is not defined",
                    $this->context->getFile(),
                    $node->lineno ?? 0
                );
            }
        } else {
            $variable = $this->context->getScope()->getVariableWithName(
                $variable_name
            );

            return $variable->getUnionType();
        }

        return new UnionType();
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
            } else {
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

        // class name fetch
        if($constant_name == 'class') {
            return StringType::instance()->asUnionType();
        }

        try {
            $class_fqsen = null;
            foreach ($this->classListFromNode(
                $node->children['class']
            )
                as $i => $class
            ) {
                $class_fqsen = $class->getFQSEN();

                // Check to see if the class has the constant
                if (!$class->hasConstantWithName(
                    $this->code_base,
                    $constant_name
                )) {
                    continue;
                }

                return $class->getConstantWithName(
                    $this->code_base,
                    $constant_name
                )->getUnionType();
            }
        } catch (CodeBaseException $exception) {
            Log::err(
                Log::EUNDEF,
                "Can't access constant {$constant_name} from undeclared class {$exception->getFQSEN()}",
                $this->context->getFile(),
                $node->lineno
            );
        }

        // If no class is found, we'll emit the error elsewhere
        if ($class_fqsen) {
            Log::err(
                Log::EUNDEF,
                "Can't access undeclared constant {$class_fqsen}::{$constant_name}",
                $this->context->getFile(),
                $node->lineno
            );
        }

        return new UnionType();
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
        $property_name = $node->children['prop'];

        // Give up for things like C::$prop_name
        if (!is_string($property_name)) {
            return new UnionType();
        }

        assert(is_string($property_name),
            "Found non-string property name in {$this->context}");

        try {
            $class_fqsen = null;
            foreach ($this->classListFromNode(
                $node->children['expr'] ?? $node->children['class']
            )
                as $i => $class
            ) {
                $class_fqsen = $class->getFQSEN();

                // Keep hunting if this class doesn't have the given
                // property
                if (!$class->hasPropertyWithName(
                        $this->code_base,
                        $property_name
                )) {
                    // If there's a getter on properties than all
                    // bets are off.
                    if ($class->hasMethodWithName(
                        $this->code_base, '__get'
                    )) {
                        return new UnionType();
                    }

                    continue;
                }

                try {
                    $property = $class->getPropertyByNameInContext(
                        $this->code_base,
                        $property_name,
                        $this->context
                    );

                    $property->addReference($this->context);
                } catch (AccessException $exception) {
                    Log::err(
                        Log::EACCESS,
                        $exception->getMessage(),
                        $this->context->getFile(),
                        $node->lineno
                    );
                    return new UnionType();
                }

                return $property->getUnionType();
            }
        } catch (CodeBaseException $exception) {
            Log::err(
                Log::EUNDEF,
                "Can't access property {$property_name} from undeclared class {$exception->getFQSEN()}",
                $this->context->getFile(),
                $node->lineno
            );
        }

        // If the class isn't found, we'll get the message elsewhere
        if ($class_fqsen) {
            Log::err(
                Log::EUNDEF,
                "Can't access undeclared property {$class_fqsen}->{$property_name}",
                $this->context->getFile(),
                $node->lineno
            );
        }

        return new UnionType();
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
        return $this->visitProp($node);
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
            // Things like `$func()`
            return new UnionType();
        }

        $function_name =
            $node->children['expr']->children['name'];

        try {
            $function = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['expr']
            ))->getFunction($function_name);
        } catch (CodeBaseException $exception) {
            // If the function wasn't declared, it'll be caught
            // and reported elsewhere
            return new UnionType();
        }

        $function_fqsen = $function->getFQSEN();

        // TODO: I don't believe we need this any more
        // If this is an internal function, see if we can get
        // its types from the static dataset.
        if ($function->getContext()->isInternal()
            && $function->getUnionType()->isEmpty()
        ) {
            $map = UnionType::internalFunctionSignatureMapForFQSEN(
                $function->getFQSEN()
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
        return $this->visitMethodCall($node);
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

        try {
            $class_fqsen = null;
            foreach ($this->classListFromNode(
                $node->children['class'] ?? $node->children['expr']
            )
                as $i => $class
            ) {

                $class_fqsen = $class->getFQSEN();

                if (!$class->hasMethodWithName(
                    $this->code_base,
                    $method_name
                )) {
                    continue;
                }

                try {
                    $method = $class->getMethodByNameInContext(
                        $this->code_base,
                        $method_name,
                        $this->context
                    );

                    return $method->getUnionType();
                } catch (AccessException $exception) {
                    Log::err(
                        Log::EACCESS,
                        $exception->getMessage(),
                        $this->context->getFile(),
                        $node->lineno
                    );
                    return new UnionType();
                }
            }
        } catch (CodeBaseException $exception) {
            Log::err(
                Log::EUNDEF,
                "Can't access method {$method_name} from undeclared class {$exception->getFQSEN()}",
                $this->context->getFile(),
                $node->lineno
            );
        }

        /*
        Log::err(
            Log::EUNDEF,
            "call to undeclared method {$class_fqsen}->{$method_name}()",
            $this->context->getFile(),
            $node->lineno
        );
        */

        return new UnionType();
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
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );
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
        // Shortcut some easy operators
        switch ($node->flags) {
        case \ast\flags\UNARY_BOOL_NOT:
            return BoolType::instance()->asUnionType();
        }

        return self::unionTypeFromNode(
            $this->code_base,
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

    /*
     * @param Node $node
     * A node holding a class
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    private function visitClassNode(Node $node) : UnionType {

        // Things of the form `new $class_name();`
        if ($node->kind == \ast\AST_VAR) {
            return new UnionType();
        }

        // Anonymous class of form `new class { ... }`
        if ($node->kind == \ast\AST_CLASS
            && $node->flags & \ast\flags\CLASS_ANONYMOUS
        ) {
            // Generate a stable name for the anonymous class
            $anonymous_class_name =
                (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getUnqualifiedNameForAnonymousClass();

            // Turn that into a fully qualified name
            $fqsen = FullyQualifiedClassName::fromStringInContext(
                $anonymous_class_name,
                $this->context
            );

            // Turn that into a union type
            return Type::fromFullyQualifiedString($fqsen)->asUnionType();
        }

        // Things of the form `new $method->name()`
        if($node->kind !== \ast\AST_NAME) {
            return new UnionType();
        }

        // Get the name of the class
        $class_name =
            $node->children['name'];

        // If this is a straight-forward class name, recurse into the
        // class node and get its type
        if (!Type::isSelfTypeString($class_name)) {
            // TODO: does anyone else call this method?
            return self::unionTypeFromClassNode(
                $this->code_base,
                $this->context,
                $node
            );
        }

        // This is a self-referential node
        if (!$this->context->isInClassScope()) {
            Log::err(
                Log::ESTATIC,
                "Cannot access {$class_name} when not in a class scope",
                $this->context->getFile(),
                $node->lineno
            );

            return new UnionType();
        }

        // Reference to a parent class
        if($class_name === 'parent') {
            $class = $this->context->getClassInScope(
                $this->code_base
            );

            if (!$class->hasParentClassFQSEN()) {
                Log::err(
                    Log::ESTATIC,
                    "Reference to parent of parentless class {$class->getFQSEN()}",
                    $this->context->getFile(),
                    $node->lineno
                );

                return new UnionType();
            }

            return Type::fromFullyQualifiedString(
                (string)$class->getParentClassFQSEN()
            )->asUnionType();
        }

        return Type::fromFullyQualifiedString(
            (string)$this->context->getClassFQSEN()
        )->asUnionType();
    }

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param $context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param Node|mixed $node
     * The node for which we'd like to determine its type
     *
     * @return UnionType
     * The UnionType associated with the given node
     * in the given Context within the given CodeBase
     *
     * @return UnionType
     * The union type for a node of type \ast\AST_CLASS
     */
    public static function unionTypeFromClassNode(
        CodeBase $code_base,
        Context $context,
        $node
    ) : UnionType {

        // For simple nodes or very complicated nodes,
        // recurse
        if(!($node instanceof \ast\Node)
            || $node->kind != \ast\AST_NAME
        ) {
            return self::unionTypeFromNode(
                $code_base,
                $context,
                $node
            );
        }

        $class_name = $node->children['name'];

        // Check to see if the name is fully qualified
        if(!($node->flags & \ast\flags\NAME_NOT_FQ)) {
            if (0 !== strpos($class_name, '\\')) {
                $class_name = '\\' . $class_name;
            }
            return UnionType::fromFullyQualifiedString(
                $class_name
            );
        }

        return UnionType::fromStringInContext(
            $class_name, $context
        );
    }

    /**
     * @return Clazz[]
     * A list of classes associated with the given node
     *
     * @throws CodeBaseException
     * An exception is thrown if we can't find a class for
     * the given type
     */
    private function classListFromNode(Node $node) {
        // Get the types associated with the node
        $union_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node
        );

        // Iterate over each viable class type to see if any
        // have the constant we're looking for
        foreach ($union_type->nonNativeTypes()->getTypeList()
            as $class_type
        ) {
            // Get the class FQSEN
            $class_fqsen = $class_type->asFQSEN();

            // See if the class exists
            if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
                throw new CodeBaseException(
                    $class_fqsen,
                    "Cannot find class $class_fqsen"
                );
            }

            yield $this->code_base->getClassByFQSEN($class_fqsen);
        }
    }

}
