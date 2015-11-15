<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\Configuration;
use \Phan\Debug;
use \Phan\Language\AST;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\Element\{Clazz, Comment, Constant, Method, Property};
use \Phan\Language\FQSEN;
use \Phan\Language\Type;
use \Phan\Language\Type\{
    ArrayType,
    BoolType,
    CallableType,
    FloatType,
    GenericArrayType,
    IntType,
    MixedType,
    NativeType,
    NullType,
    ObjectType,
    ResourceType,
    ScalarType,
    StringType,
    VoidType
};
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * The class is a visitor for AST nodes that does parsing. Each
 * visitor populates the $context->getCodeBase() with any
 * globally accessible structural elements and will return a
 * possibly new context as modified by the given node.
 *
 * # Example Usage
 * ```
 * $context =
 *     (new Element($node))->acceptKindVisitor(
 *         new ParseVisitor($context)
 *     );
 * ```
 */
class ParseVisitor extends ScopeVisitor {

    /**
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(Context $context) {
        parent::__construct($context);
    }

    /**
     * Visit a node with kind `\ast\AST_DIM`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitDim(Node $node) : Context {
        if (!Configuration::instance()->backward_compatibility_checks) {
            return $this->context;
        }

        if(!($node->children['expr'] instanceof Node
            && ($node->children['expr']->children['name'] ?? null) instanceof Node)
        ) {
            return $this->context;
        }

        // check for $$var[]
        if($node->children['expr']->kind == \ast\AST_VAR
            && $node->children['expr']->children['name']->kind == \ast\AST_VAR
        ) {
            $temp = $node->children['expr']->children['name'];
            $depth = 1;
            while($temp instanceof Node) {
                $temp = $temp->children[0];
                $depth++;
            }
            $dollars = str_repeat('$',$depth);
            $ftemp = new \SplFileObject($this->context->getFile());
            $ftemp->seek($node->lineno-1);
            $line = $ftemp->current();
            unset($ftemp);
            if(strpos($line,'{') === false
                || strpos($line,'}') === false
            ) {
                Log::err(
                    Log::ECOMPAT,
                    "{$dollars}{$temp}[] expression may not be PHP 7 compatible",
                    $this->context->getFile(),
                    $node->lineno
                );
            }

        // $foo->$bar['baz'];
        } else if(!empty($node->children['expr']->children[1])
            && ($node->children['expr']->children[1] instanceof Node)
            && ($node->children['expr']->kind == \ast\AST_PROP)
            && ($node->children['expr']->children[0]->kind == \ast\AST_VAR)
            && ($node->children['expr']->children[1]->kind == \ast\AST_VAR)
        ) {
            $ftemp = new \SplFileObject($this->context->getFile());
            $ftemp->seek($node->lineno-1);
            $line = $ftemp->current();
            unset($ftemp);
            if(strpos($line,'{') === false
                || strpos($line,'}') === false
            ) {
                Log::err(
                    Log::ECOMPAT,
                    "expression may not be PHP 7 compatible",
                    $this->context->getFile(),
                    $node->lineno
                );
            }
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_CLASS`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClass(Node $node) : Context {

        $class_name = $node->name;

        // This happens now and then and I have no idea
        // why.
        if (empty($class_name)) {
            return $this->context;
        }

        assert(!empty($class_name),
            "Class must have name in {$this->context}");

        $class_fqsen = FQSEN::fromContext($this->context)
            ->withClassName($this->context, $class_name);

        // Hunt for an available alternate ID if necessary
        $alternate_id = 0;
        while($this->context->getCodeBase()->hasClassWithFQSEN($class_fqsen)) {
            $class_fqsen = $class_fqsen->withAlternateId(++$alternate_id);
        }

        // Build the class from what we know so far
        $clazz = new Clazz(
            $this->context
                ->withLineNumberStart($node->lineno)
                ->withLineNumberEnd($node->endLineno ?: -1),
                Comment::fromStringInContext(
                    $node->docComment ?: '',
                    $this->context
                ),
            $node->name,
            UnionType::fromStringInContext(
                $node->name,
                $this->context
            ),
            $node->flags
        );

        // Override the FQSEN with the found alternate ID
        $clazz->setFQSEN($class_fqsen);

        // Add the class to the code base as a globally
        // accessible object
        $this->context->getCodeBase()->addClass($clazz);

        // Look to see if we have a parent class
        if(!empty($node->children['extends'])) {
            $parent_class_name =
                $node->children['extends']->children['name'];

            // Check to see if the name isn't fully qualified
            if($node->children['extends']->flags & \ast\flags\NAME_NOT_FQ) {
                if ($this->context->hasNamespaceMapFor(
                    T_CLASS,
                    $parent_class_name
                )) {
                    // Get a fully-qualified name
                    $parent_class_name =
                        (string)($this->context->getNamespaceMapFor(
                            T_CLASS,
                            $parent_class_name
                        ));
                } else {
                    $parent_class_name =
                        $this->context->getNamespace() . '\\' . $parent_class_name;
                }
            }

            // The name is fully qualified. Make sure it looks
            // like it is
            if(0 !== strpos($parent_class_name, '\\')) {
                $parent_class_name = '\\' . $parent_class_name;
            }

            $parent_fqsen =
                $this->context->getScopeFQSEN()->withClassName(
                    $this->context,
                    $parent_class_name
                );

            // Set the parent for the class
            $clazz->setParentClassFQSEN($parent_fqsen);
        }

        // Add any implemeneted interfaces
        if (!empty($node->children['implements'])) {
            $interface_list = AST::qualifiedNameList(
                $this->context,
                $node->children['implements']
            );
            foreach ($interface_list as $name) {
                $clazz->addInterfaceClassFQSEN(
                    FQSEN::fromFullyQualifiedString($name)
                );
            }
        }

        // Update the context to signal that we're now
        // within a class context.
        $context = $clazz->getContext()->withClassFQSEN(
            $class_fqsen
        );

        return $context;
    }

    /**
     * Visit a node with kind `\ast\AST_USE_TRAIT`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitUseTrait(Node $node) : Context {
        // Bomb out if we're not in a class context
        $clazz = $this->getContextClass();

        $trait_fqsen_string_list =
            AST::qualifiedNameList(
                $this->context,
                $node->children['traits']
            );

        // Add each trait to the class
        foreach ($trait_fqsen_string_list as $trait_fqsen_string) {
            $trait_fqsen =
                FQSEN::fromContextAndString(
                    $clazz->getContext(),
                    $trait_fqsen_string
                );

            $clazz->addTraitFQSEN($trait_fqsen);
        }

        $this->context->getCodeBase()->incrementTraits();

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD_REFERENCE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethod(Node $node) : Context {
        // Bomb out if we're not in a class context
        $clazz = $this->getContextClass();

        $method_name = $node->name;

        $method_fqsen =
            $this->context->getScopeFQSEN()->withMethodName(
                $this->context,
                $method_name
            );

        // Hunt for an available alternate ID if necessary
        $alternate_id = 0;
        while($this->context->getCodeBase()->hasMethodWithFQSEN($method_fqsen)) {
            $method_fqsen =
                $method_fqsen->withAlternateId(++$alternate_id);
        }

        $method =
            Method::fromNode($this->context, $node);

        // Override the FQSEN with the found alternate ID
        $method->setFQSEN($method_fqsen);

        $clazz->addMethod($method);
        $this->context->getCodeBase()->addMethod($method);

        if ('__construct' === $method_name) {
            $clazz->setIsParentConstructorCalled(false);
        }

        if ('__invoke' === $method_name) {
            $clazz->getUnionType()->addType(
                CallableType::instance()
            );
        }

        // Send the context into the method
        $context = $this->context->withMethodFQSEN(
            $method->getFQSEN()
        );

        // Add each method parameter to the scope. We clone it
        // so that changes to the variable don't alter the
        // parameter definition
        foreach ($method->getParameterList() as $parameter) {
            $variable = clone($parameter);
            $context = $context->withScopeVariable(
                $variable
            );
        }

        return $context;
    }

    /**
     * Visit a node with kind `\ast\AST_PROP_DECL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitPropDecl(Node $node) : Context {
        // Bomb out if we're not in a class context
        $clazz = $this->getContextClass();

        // Get a comment on the property declaration
        $comment = Comment::fromStringInContext(
            $node->children[0]->docComment ?? '',
            $this->context
        );

        foreach($node->children as $i => $child_node) {
            // Ignore children which are not property elements
            if (!$child_node || $child_node->kind != \ast\AST_PROP_ELEM) {
                continue;
            }

            // @var UnionType
            $type = UnionType::fromNode(
                $this->context,
                $child_node->children['default']
            );

            $property_name = $child_node->children['name'];

            assert(is_string($property_name),
                'Property name must be a string. '
                . 'Got '
                . print_r($property_name, true)
                . ' at '
                . $this->context);

            $property =
                new Property(
                    $this->context
                        ->withLineNumberStart($child_node->lineno)
                        ->withLineNumberEnd($child_node->endLineno ?? -1),
                    Comment::fromStringInContext(
                        $child_node->docComment ?? '',
                        $this->context
                    ),
                    is_string($child_node->children['name'])
                        ? $child_node->children['name']
                        : '_error_',
                    $type,
                    $node->flags
                );

            // Set the node type to be the declared type. This may
            // be overridden if a @var sets the type
            $property->setDeclaredUnionType($type);

            // Add the property to the class
            $clazz->addProperty($property);

            // Look for any @var declarations
            if ($variable = $comment->getVariableList()[$i] ?? null) {
                if ((string)$type != 'null'
                    && !$type->canCastToUnionType($variable->getUnionType())
                ) {
                    Log::err(Log::ETYPE,
                        "assigning $type to property but {$property->getFQSEN()} is {$variable->getUnionType()}",
                        $this->context->getFile(),
                        $child_node->lineno
                    );
                }

                // Set the declared type to the doc-comment type and add
                // |null if the default value is null
                $property->getUnionType()->addUnionType(
                    $variable->getUnionType()
                );

                $property->setDeclaredUnionType(
                    $variable->getUnionType()
                );
            }
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_CLASS_CONST_DECL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClassConstDecl(Node $node) : Context {
        $clazz = $this->getContextClass();

        foreach($node->children as $child_node) {
            $constant = new Constant(
                $this->context
                    ->withLineNumberStart($child_node->lineno ?? 0)
                    ->withLineNumberEnd($child_node->endLineno ?? 0),
                Comment::fromStringInContext(
                    $child_node->docComment ?? '',
                    $this->context
                ),
                $child_node->children['name'],
                UnionType::fromNode(
                    $this->context,
                    $child_node->children['value']
                ),
                $child_node->flags ?? 0
            );

            $clazz->addConstant($constant);
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_FUNC_DECL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitFuncDecl(Node $node) : Context {
        $function_name = $node->name;

        // Hunt for an un-taken alternate ID
        $alternate_id = 0;
        $function_fqsen = null;
        do {
            $function_fqsen =
                $this->context->getScopeFQSEN()
                    ->withFunctionName(
                        $this->context,
                        $function_name
                    )
                    ->withNamespace($this->context->getNamespace())
                    ->withAlternateId($alternate_id++);
        } while($this->context->getCodeBase()
            ->hasMethodWithFQSEN($function_fqsen));

        $method = Method::fromNode(
            $this->context
                ->withLineNumberStart($node->lineno ?? 0)
                ->withLineNumberEnd($node->endLineno ?? 0),
            $node
        );

        $method->setFQSEN($function_fqsen);
        $this->context->getCodeBase()->addFunction($method);

        return
            $this->context->withMethodFQSEN($function_fqsen);
    }

    /**
     * Visit a node with kind `\ast\AST_CALL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCall(Node $node) : Context {

        if(Configuration::instance()->backward_compatibility_checks) {
            AST::backwardCompatibilityCheck($this->context, $node);
        }

        // If this is a call to a method that indicates that we
        // are treating the method in scope as a varargs method,
        // then set its optional args to something very high so
        // it can be called with anything.
        $expression = $node->children['expr'];
        if($expression->kind === \ast\AST_NAME
            && $this->context->isMethodScope()
            && in_array($expression->children['name'], [
                'func_get_args', 'func_get_arg', 'func_num_args'
            ])
        ) {
            $this->context->getMethodInScope()
                ->setNumberOfOptionalParameters(999999);
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_STATIC_CALL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitStaticCall(Node $node) : Context {
        $call = $node->children['class'];

        if($call->kind == \ast\AST_NAME) {
            $func_name = strtolower($call->children['name']);
            if($func_name == 'parent') {
                $meth = strtolower($node->children['method']);

                if($meth == '__construct') {
                    $clazz = $this->getContextClass();
                    $clazz->setIsParentConstructorCalled(true);
                }
            }
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_RETURN`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitReturn(Node $node) : Context {
        if (Configuration::instance()->backward_compatibility_checks) {
            AST::backwardCompatibilityCheck($this->context, $node);
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_PRINT`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitPrint(Node $node) : Context {
        return $this->visitReturn($node);
    }

    /**
     * Visit a node with kind `\ast\AST_ECHO`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitEcho(Node $node) : Context {
        return $this->visitReturn($node);
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD_CALL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethodCall(Node $node) : Context {
        return $this->visitReturn($node);
    }

    /**
     * @return Clazz
     * Get the class on this scope or fail real hard
     */
    private function getContextClass() : Clazz {
        return $this->context->getClassInScope();
    }
}
