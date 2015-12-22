<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\AST\ContextNode;
use \Phan\AST\UnionTypeVisitor;
use \Phan\AST\Visitor\Element;
use \Phan\AST\Visitor\KindVisitorImplementation;
use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Debug;
use \Phan\Language\Context;
use \Phan\Language\Element\{Clazz, Comment, Constant, Method, Property};
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\Scope;
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
use \ast\Node\Decl;

/**
 * The class is a visitor for AST nodes that does parsing. Each
 * visitor populates the $code_base with any
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
     *
     * @param CodeBase $code_base
     * The global code base in which we store all
     * state
     */
    public function __construct(Context $context, CodeBase $code_base) {
        parent::__construct($context, $code_base);
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
    public function visitClass(Decl $node) : Context {

        if ($node->flags & \ast\flags\CLASS_ANONYMOUS) {
            $class_name = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getUnqualifiedNameForAnonymousClass();
        } else {
            $class_name = (string)$node->name;
        }

        // This happens now and then and I have no idea
        // why.
        if (empty($class_name)) {
            return $this->context;
        }

        assert(!empty($class_name),
            "Class must have name in {$this->context}");

        $class_fqsen =
            FullyQualifiedClassName::fromStringInContext(
                $class_name,
                $this->context
            );

        // Hunt for an available alternate ID if necessary
        $alternate_id = 0;
        while($this->code_base->hasClassWithFQSEN($class_fqsen)) {
            $class_fqsen = $class_fqsen->withAlternateId(++$alternate_id);
        }

        // Build the class from what we know so far
        $clazz = new Clazz(
            $this->context
                ->withLineNumberStart($node->lineno ?? 0)
                ->withLineNumberEnd($node->endLineno ?? -1),
            $class_name,
            UnionType::fromStringInContext(
                $class_name,
                $this->context
            ),
            $node->flags ?? 0
        );

        // Override the FQSEN with the found alternate ID
        $clazz->setFQSEN($class_fqsen);

        // Add the class to the code base as a globally
        // accessible object
        $this->code_base->addClass($clazz);

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
                FullyQualifiedClassName::fromStringInContext(
                    $parent_class_name,
                    $this->context
                );

            // Set the parent for the class
            $clazz->setParentClassFQSEN($parent_fqsen);
        }

        // Add any implemeneted interfaces
        if (!empty($node->children['implements'])) {
            $interface_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['implements']
            ))->getQualifiedNameList();

            foreach ($interface_list as $name) {
                $clazz->addInterfaceClassFQSEN(
                    FullyQualifiedClassName::fromFullyQualifiedString(
                        $name
                    )
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

        $trait_fqsen_string_list = (new ContextNode(
            $this->code_base,
            $this->context,
            $node->children['traits']
        ))->getQualifiedNameList();

        // Add each trait to the class
        foreach ($trait_fqsen_string_list as $trait_fqsen_string) {
            $trait_fqsen =
                FullyQualifiedClassName::fromStringInContext(
                    $trait_fqsen_string,
                    $clazz->getContext()
                );

            $clazz->addTraitFQSEN($trait_fqsen);
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethod(Decl $node) : Context {
        // Bomb out if we're not in a class context
        $clazz = $this->getContextClass();

        $method_name = (string)$node->name;

        $method_fqsen =
            FullyQualifiedMethodName::fromStringInContext(
                $method_name,
                $this->context
            );

        // Hunt for an available alternate ID if necessary
        $alternate_id = 0;
        while($this->code_base->hasMethod($method_fqsen)) {
            $method_fqsen =
                $method_fqsen->withAlternateId(++$alternate_id);
        }

        // Create a new context with a new scope
        $context = $this->context->withScope(new Scope);

        // Add $this to the scope of non-static methods
        if (!($node->flags & \ast\flags\MODIFIER_STATIC)) {
            assert($clazz->getContext()->getScope()
                ->hasVariableWithName('this'),
                "Classes must have a \$this variable.");

            $context = $context->withScopeVariable(
                $clazz->getContext()->getScope()
                ->getVariableWithName('this')
            );
        }

        $method = Method::fromNode(
            $context,
            $this->code_base,
            $node
        );

        // Override the FQSEN with the found alternate ID
        $method->setFQSEN($method_fqsen);

        $clazz->addMethod($this->code_base, $method);

        if ('__construct' === $method_name) {
            $clazz->setIsParentConstructorCalled(false);
        }
        else if ('__invoke' === $method_name) {
            $clazz->getUnionType()->addType(
                CallableType::instance()
            );
        }
        else if ('__toString' === $method_name) {
            $clazz->getUnionType()->addType(
                StringType::instance()
            );
        }

        // Add each method parameter to the scope. We clone it
        // so that changes to the variable don't alter the
        // parameter definition
        foreach ($method->getParameterList() as $parameter) {
            $method->getContext()->addScopeVariable(clone($parameter));
        }

        // Send the context into the method and reset the scope
        $context = $method->getContext()->withMethodFQSEN(
            $method->getFQSEN()
        );

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

        foreach($node->children ?? [] as $i => $child_node) {
            // Ignore children which are not property elements
            if (!$child_node || $child_node->kind != \ast\AST_PROP_ELEM) {
                continue;
            }

            // Get the type of the default
            $union_type = UnionType::fromNode(
                $this->context,
                $this->code_base,
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
                        ->withLineNumberStart($child_node->lineno ?? 0)
                        ->withLineNumberEnd($child_node->endLineno ?? -1),
                    is_string($child_node->children['name'])
                        ? $child_node->children['name']
                        : '_error_',
                    $union_type,
                    $node->flags ?? 0
                );

            // Add the property to the class
            $clazz->addProperty($this->code_base, $property);

            // Look for any @var declarations
            if ($variable = $comment->getVariableList()[$i] ?? null) {
                if ((string)$union_type != 'null'
                    && !$union_type->canCastToUnionType($variable->getUnionType())
                ) {
                    Log::err(Log::ETYPE,
                        "assigning $union_type to property but {$property->getFQSEN()} is {$variable->getUnionType()}",
                        $this->context->getFile(),
                        $child_node->lineno
                    );
                }

                // Set the declared type to the doc-comment type and add
                // |null if the default value is null
                $property->getUnionType()->addUnionType(
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

        foreach($node->children ?? [] as $child_node) {
            $union_type = UnionType::fromNode(
                $this->context,
                $this->code_base,
                $child_node->children['value']
            );

            $constant = new Constant(
                $this->context
                    ->withLineNumberStart($child_node->lineno ?? 0)
                    ->withLineNumberEnd($child_node->endLineno ?? 0),
                $child_node->children['name'],
                $union_type,
                $child_node->flags ?? 0
            );

            $clazz->addConstant(
                $this->code_base,
                $constant
            );
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
    public function visitFuncDecl(Decl $node) : Context {
        $function_name = (string)$node->name;

        // Hunt for an un-taken alternate ID
        $alternate_id = 0;
        $function_fqsen = null;
        do {
            $function_fqsen =
                FullyQualifiedFunctionName::fromStringInContext(
                    $function_name,
                    $this->context
                )
                ->withNamespace($this->context->getNamespace())
                ->withAlternateId($alternate_id++);

        } while($this->code_base
            ->hasMethod($function_fqsen));

        $method = Method::fromNode(
            $this->context
                ->withLineNumberStart($node->lineno ?? 0)
                ->withLineNumberEnd($node->endLineno ?? 0),
            $this->code_base,
            $node
        );

        $method->setFQSEN($function_fqsen);
        $this->code_base->addMethod($method);

        // Send the context into the function and reset the scope
        $context = $this->context->withMethodFQSEN(
            $function_fqsen
        )->withScope(new Scope);

        // Add each method parameter to the scope. We clone it
        // so that changes to the variable don't alter the
        // parameter definition
        foreach ($method->getParameterList() as $parameter) {
            $context->addScopeVariable(clone($parameter));
        }

        return $context;
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
            $this->context->getMethodInScope($this->code_base)
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
                // Make sure it is not a crazy dynamic parent method call
                if(!($node->children['method'] instanceof Node)) {
                    $meth = strtolower($node->children['method']);

                    if($meth == '__construct') {
                        $clazz = $this->getContextClass();
                        $clazz->setIsParentConstructorCalled(true);
					}
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
        if (Config::get()->backward_compatibility_checks) {
            (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->analyzeBackwardCompatibility();
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
     * Visit a node with kind `\ast\AST_DECLARE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitDeclare(Node $node) : Context {
        $declares = $node->children['declares'];
        $name = $declares->children[0]->children['name'];
        $value = $declares->children[0]->children['value'];
        if ('strict_types' === $name) {
            return $this->context->withStrictTypes($value);
        }

        return $this->context;
    }


    /**
     * @return Clazz
     * Get the class on this scope or fail real hard
     */
    private function getContextClass() : Clazz {
        return $this->context->getClassInScope($this->code_base);
    }
}
