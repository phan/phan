<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\Configuration;
use \Phan\Debug;
use \Phan\Deprecated;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\Element\{Clazz, Comment, Constant, Method, Property};
use \Phan\Language\FQSEN;
use \Phan\Language\Type;
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
class ParseVisitor extends KindVisitorImplementation {
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
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visit(Node $node) : Context {
        // Many nodes don't change the context and we
        // don't need to read them.
        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_NAMESPACE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitNamespace(Node $node) : Context {
        return $this->context->withNamespace(
            (string)$node->children[0]
        );
    }

    /**
     * Visit a node with kind `\ast\AST_IF`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIf(Node $node) : Context {
        $context = $this->context->withIsConditional(true);
        $context->getCodeBase()->incrementConditionals();
        return $context;
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
        if (!Configuration::instance()->bc_checks) {
            return $this->context;
        }

        if(!($node->children[0] instanceof Node
            && $node->children[0]->children[0] instanceof Node)
        ) {
            return $this->context;
        }

        // check for $$var[]
        if($node->children[0]->kind == \ast\AST_VAR
            && $node->children[0]->children[0]->kind == \ast\AST_VAR
        ) {
            $temp = $node->children[0]->children[0];
            $depth = 1;
            while($temp instanceof Node) {
                $temp = $temp->children[0];
                $depth++;
            }
            $dollars = str_repeat('$',$depth);
            $ftemp = new \SplFileObject($file);
            $ftemp->seek($node->lineno-1);
            $line = $ftemp->current();
            unset($ftemp);
            if(strpos($line,'{') === false
                || strpos($line,'}') === false
            ) {
                Log::err(
                    Log::ECOMPAT,
                    "{$dollars}{$temp}[] expression may not be PHP 7 compatible",
                    $file,
                    $node->lineno
                );
            }

        // $foo->$bar['baz'];
        } else if(!empty($node->children[0]->children[1])
            && ($node->children[0]->children[1] instanceof Node)
            && ($node->children[0]->kind == \ast\AST_PROP)
            && ($node->children[0]->children[0]->kind == \ast\AST_VAR)
            && ($node->children[0]->children[1]->kind == \ast\AST_VAR)
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
     * Visit a node with kind `\ast\AST_USE`
     * such as `use \ast\Node;`.
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitUse(Node $node) : Context {
        $context = $this->context;

        foreach($node->children as $elem) {
            $target = $elem->children[0];
            if(empty($elem->children[1])) {
                if(($pos=strrpos($target, '\\'))!==false) {
                    $alias = substr($target, $pos + 1);
                } else {
                    $alias = $target;
                }
            } else {
                $alias = $elem->children[1];
            }

            $context = $context->withNamespaceMap(
                $node->flags, $alias, $target
            );
        }

        return $context;
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

        // Get an FQSEN for this class
        $class_name = $node->name;

        if (!$class_name) {
            print $this->context . "\n";
            return $this->context;
        }

        assert(!empty($class_name), "Class name cannot be null");


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
            Comment::fromString($node->docComment ?: ''),
            $node->name,
            new Type([$node->name]),
            $node->flags
        );

        // Override the FQSEN with the found alternate ID
        $clazz->setFQSEN($class_fqsen);

        // Add the class to the code base as a globally
        // accessible object
        $this->context->getCodeBase()->addClass($clazz);
        $this->context->getCodeBase()->incrementClasses();

        // Look to see if we have a parent class
        if(!empty($node->children[0])) {
            $parent_class_name =
                $node->children[0]->children[0];

            // Check to see if the name isn't fully qualified
            if($node->children[0]->flags & \ast\flags\NAME_NOT_FQ) {
                if ($this->context->hasNamespaceMapFor(
                    T_CLASS,
                    $parent_class_name
                )) {
                    // Get a fully-qualified name
                    $parent_class_name =
                        (string)$this->context->getNamespaceMapfor(
                            T_CLASS,
                            $parent_class_name
                        );
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
            static::astQualifiedNameList(
                $this->context,
                $node->children[0]
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
        while($clazz->hasMethodWithFQSEN($method_fqsen)) {
            $method_fqsen = $method_fqsen->withAlternateId(++$alternate_id);
        }

        $method =
            Method::fromNode($this->context, $node);

        // Override the FQSEN with the found alternate ID
        $method->setFQSEN($method_fqsen);

        $clazz->addMethod($method);
        $this->context->getCodeBase()->addMethod($method);
        $this->context->getCodeBase()->incrementMethods();

        if ('__construct' === $method_name) {
            $clazz->setIsParentConstructorCalled(false);
        }

        if ('__invoke' === $method_name) {
            $clazz->getType()->addTypeName('callable');
        }

        // Send the context into the method
        $context = $this->context->withMethodFQSEN(
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
        $comment = Comment::fromString($node->docComment ?? '');

        foreach($node->children as $i=>$node) {
            // Ignore children which are not property elements
            if (!$node
                || $node->kind != \ast\AST_PROP_ELEM) {
                continue;
            }

            // @var Type
            $type = Type::typeFromNode(
                $this->context,
                $node->children[1]
            );

            $property_name = $node->children[0];

            assert(is_string($property_name),
                'Property name must be a string. '
                . 'Got '
                . print_r($property_name, true)
                . ' at '
                . $this->context);

            $property =
                new Property(
                    $this->context
                        ->withLineNumberStart($node->lineno)
                        ->withLineNumberEnd($node->endLineno ?? -1),
                    Comment::fromString($node->docComment ?? ''),
                    is_string($node->children[0])
                    ? $node->children[0]
                    : '_error_',
                    $type,
                    $node->flags
                );

            // Set the node type to be the declared type. This may
            // be overridden if a @var sets the type
            $property->setDeclaredType($type);

            // Add the property to the class
            $clazz->addProperty($property);

            // Look for any @var declarations
            foreach ($comment->getVariableList() as $i => $variable) {
                if ((string)$type != 'null'
                    && !$type->canCastToTypeInContext(
                        $variable->getType(),
                        $this->context
                    )
                ) {
                    Log::err(Log::ETYPE,
                        "property is declared to be {$variable->getType()} but was assigned $type",
                        $this->context->getFile(),
                        $node->lineno
                    );
                }

                // Set the declared type to the doc-comment type and add
                // |null if the default value is null

                $property->getType()->addType(
                    $variable->getType()
                );

                $property->setDeclaredType(
                    $variable->getType()
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

        foreach($node->children as $node) {
            $constant = new Constant(
                $this->context
                    ->withLineNumberStart($node->lineno ?? 0)
                    ->withLineNumberEnd($node->endLineno ?? 0),
                Comment::fromString($node->docComment ?? ''),
                $node->children[0],
                Type::typeFromNode(
                    $this->context,
                    $node->children[1]
                ),
                $node->flags ?? 0
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

        $function_fqsen =
            $this->context->getScopeFQSEN()->withFunctionName(
                $this->context,
                $function_name
            );

        // Hunt for an un-taken alternate ID
        $alternate_id = 0;
        while($this->context->getCodeBase()->hasMethodWithFQSEN($function_fqsen)) {
            $function_fqsen =
                $function_fqsen->withAlternateId(++$alternate_id);
        }

        $method = Method::fromNode(
            $this->context
                ->withLineNumberStart($node->lineno ?? 0)
                ->withLineNumberEnd($node->endLineno ?? 0),
            $node
        );

        $method->setFQSEN($function_fqsen);

        $this->context->getCodeBase()->addMethod($method);
        $this->context->getCodeBase()->incrementFunctions();

        $context =
            $this->context->withMethodFQSEN($function_fqsen);

        return $context;
    }

    /**
     * Visit a node with kind `\ast\AST_CLOSURE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClosure(Node $node) : Context {
        $this->context->getCodeBase()->incrementClosures();

        return
            $this->context->withClosureFQSEN(
                $this->context->getScopeFQSEN()->withClosureName(
                    'closure'
                )
            );
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
        $found = false;
        $call_node = $node->children[0];

        if($call_node->kind == \ast\AST_NAME) {

            $function_name = $call_node->children[0];

            $method_fqsen =
                $this->context->getScopeFQSEN()
                ->withFunctionName($this->context, $function_name);

            if (!$this->context->getCodeBase()->hasMethodWithFQSEN(
                $method_fqsen
            )) {
                // TODO: There are missing methods like 'apache_note'
                //       that we'll want to do something with other
                //       than ignoring.
                // assert(false, "Method with FQSEN $method_fqsen not found.");
                return $this->context;
            }

            // Get the current method in scope or fail real hard
            // if we're in an impossible state
            $method = $this->context->getCodeBase()->getMethodByFQSEN(
                $method_fqsen
            );

            // if($func_name == 'func_get_args' || $func_name == 'func_get_arg' || $func_name == 'func_num_args') {
            if (in_array($function_name, [
                'func_get_args',
                'func_get_arg',
                'func_num_args'
            ])) {

                // TODO: We don't actually have to check the class
                //       scope. Scoped methods can be method or
                //       functions.

                // if(!empty($current_class)) {
                if ($this->context->isClassScope()) {
                    $method->setNumberOfOptionalParameters(999999);
                    // $classes[$lc]['methods'][strtolower($current_function)]['optional'] = 999999;

                } else {
                    $method->setNumberOfOptionalParameters(999999);
                    // $functions[strtolower($current_function)]['optional'] = 999999;
                }
            }
        }

        if(Configuration::instance()->bc_checks) {
            \Phan\Deprecated::bc_check(
                $this->context->getFile(),
                $node
            );
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
        $call = $node->children[0];

        if($call->kind == \ast\AST_NAME) {
            $func_name = strtolower($call->children[0]);
            if($func_name == 'parent') {
                $meth = strtolower($node->children[1]);

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
        if (Configuration::instance()->bc_checks) {
            Deprecated::bc_check($this->context->getFile(), $node);
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
