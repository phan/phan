<?php declare(strict_types=1);
namespace Phan\Parse;

use Phan\AST\ContextNode;
use Phan\Analysis\ScopeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Daemon;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Util;
use Phan\Language\Context;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Comment;
use Phan\Language\Element\Func;
use Phan\Language\Element\GlobalConstant;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\FutureUnionType;
use Phan\Language\Type;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;
use Phan\Library\None;
use ast\Node;

/**
 * The class is a visitor for AST nodes that does parsing. Each
 * visitor populates the $code_base with any
 * globally accessible structural elements and will return a
 * possibly new context as modified by the given node.
 *
 * @property-read CodeBase $code_base
 */
class ParseVisitor extends ScopeVisitor
{

    /**
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param CodeBase $code_base
     * The global code base in which we store all
     * state
     */
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        parent::__construct($code_base, $context);
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
    public function visitClass(Node $node) : Context
    {
        if ($node->flags & \ast\flags\CLASS_ANONYMOUS) {
            $class_name = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getUnqualifiedNameForAnonymousClass();
        } else {
            $class_name = (string)$node->children['name'];
        }

        // This happens now and then and I have no idea
        // why.
        if (empty($class_name)) {
            return $this->context;
        }

        $class_fqsen = FullyQualifiedClassName::fromStringInContext(
            $class_name,
            $this->context
        );

        \assert($class_fqsen instanceof FullyQualifiedClassName,
            "The class FQSEN must be a FullyQualifiedClassName");

        // Hunt for an available alternate ID if necessary
        $alternate_id = 0;
        while ($this->code_base->hasClassWithFQSEN($class_fqsen)) {
            $class_fqsen = $class_fqsen->withAlternateId(++$alternate_id);
        }

        if ($alternate_id > 0) {
            Daemon::debugf("Using an alternate for %s: %d\n", $class_fqsen, $alternate_id);
        }

        // Build the class from what we know so far
        $class_context = $this->context
            ->withLineNumberStart($node->lineno ?? 0)
            ->withLineNumberEnd(Util::getEndLineno($node) ?? 0);

        $class = new Clazz(
            $class_context,
            $class_name,
            $class_fqsen->asUnionType(),
            $node->flags ?? 0,
            $class_fqsen
        );

        // Set the scope of the class's context to be the
        // internal scope of the class
        $class_context = $class_context->withScope(
            $class->getInternalScope()
        );

        // Get a comment on the class declaration
        $comment = Comment::fromStringInContext(
            $node->children['docComment'] ?? '',
            $this->code_base,
            $this->context,
            $node->lineno ?? 0,
            Comment::ON_CLASS
        );

        // Add any template types parameterizing a generic class
        foreach ($comment->getTemplateTypeList() as $template_type) {
            $class->getInternalScope()->addTemplateType($template_type);
        }

        $class->setIsDeprecated($comment->isDeprecated());
        $class->setIsNSInternal($comment->isNSInternal());

        $class->setSuppressIssueList(
            $comment->getSuppressIssueList()
        );

        // Add the class to the code base as a globally
        // accessible object
        $this->code_base->addClass($class);

        // Depends on code_base for checking existence of __get and __set.
        // TODO: Add a check in analyzeClasses phase that magic @property declarations
        // are limited to classes with either __get or __set declared (or interface/abstract
        $class->setMagicPropertyMap(
            $comment->getMagicPropertyMap(),
            $this->code_base,
            $this->context
        );

        // Depends on code_base for checking existence of __call or __callStatic.
        // TODO: Add a check in analyzeClasses phase that magic @method declarations
        // are limited to classes with either __get or __set declared (or interface/abstract)
        $class->setMagicMethodMap(
            $comment->getMagicMethodMap(),
            $this->code_base,
            $this->context
        );

        // usually used together with magic @property annotations
        $class->setForbidUndeclaredMagicProperties($comment->getForbidUndeclaredMagicProperties());

        // usually used together with magic @method annotations
        $class->setForbidUndeclaredMagicMethods($comment->getForbidUndeclaredMagicMethods());

        // Look to see if we have a parent class
        if (!empty($node->children['extends'])) {
            $parent_class_name =
                $node->children['extends']->children['name'];

            // Check to see if the name isn't fully qualified
            if ($node->children['extends']->flags & \ast\flags\NAME_NOT_FQ) {
                if ($this->context->hasNamespaceMapFor(
                    \ast\flags\USE_NORMAL,
                    $parent_class_name
                )) {
                    // Get a fully-qualified name
                    $parent_class_name =
                        (string)($this->context->getNamespaceMapFor(
                            \ast\flags\USE_NORMAL,
                            $parent_class_name
                        ));
                } else {
                    $parent_class_name =
                        $this->context->getNamespace() . '\\' . $parent_class_name;
                }
            }

            // The name is fully qualified. Make sure it looks
            // like it is
            if (0 !== \strpos($parent_class_name, '\\')) {
                $parent_class_name = '\\' . $parent_class_name;
            }

            $parent_fqsen = FullyQualifiedClassName::fromStringInContext(
                $parent_class_name,
                $this->context
            );

            // Set the parent for the class
            $class->setParentType($parent_fqsen->asType());
        }

        // If the class explicitly sets its overriding extension type,
        // set that on the class
        $inherited_type_option = $comment->getInheritedTypeOption();
        if ($inherited_type_option->isDefined()) {
            $class->setParentType($inherited_type_option->get());
        }

        // Add any implemeneted interfaces
        if (!empty($node->children['implements'])) {
            $interface_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['implements']
            ))->getQualifiedNameList();

            foreach ($interface_list as $name) {
                $class->addInterfaceClassFQSEN(
                    FullyQualifiedClassName::fromFullyQualifiedString(
                        $name
                    )
                );
            }
        }

        return $class_context;
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
    public function visitUseTrait(Node $node) : Context
    {
        // Bomb out if we're not in a class context
        $class = $this->getContextClass();

        $trait_fqsen_list = (new ContextNode(
            $this->code_base,
            $this->context,
            $node->children['traits']
        ))->getTraitFQSENList();

        // Add each trait to the class
        foreach ($trait_fqsen_list as $trait_fqsen) {
            $class->addTraitFQSEN($trait_fqsen);
        }

        // Get the adaptations for those traits
        // Pass in the corresponding FQSENs for those traits.
        $trait_adaptations_map = (new ContextNode(
            $this->code_base,
            $this->context,
            $node->children['adaptations']
        ))->getTraitAdaptationsMap($trait_fqsen_list);

        foreach ($trait_adaptations_map as $trait_adaptations) {
            $class->addTraitAdaptations($trait_adaptations);
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
    public function visitMethod(Node $node) : Context
    {
        // Bomb out if we're not in a class context
        $class = $this->getContextClass();

        $method_name = (string)$node->children['name'];

        $method_fqsen = FullyQualifiedMethodName::fromStringInContext(
            $method_name, $this->context
        );

        // Hunt for an available alternate ID if necessary
        $alternate_id = 0;
        while ($this->code_base->hasMethodWithFQSEN($method_fqsen)) {
            $method_fqsen =
                $method_fqsen->withAlternateId(++$alternate_id);
        }

        $method = Method::fromNode(
            clone($this->context),
            $this->code_base,
            $node,
            $method_fqsen
        );

        $class->addMethod($this->code_base, $method, new None);

        if ('__construct' === $method_name) {
            $class->setIsParentConstructorCalled(false);

            if ($class->isGeneric()) {

                // Get the set of template type identifiers defined on
                // the class
                $template_type_identifiers = \array_keys(
                    $class->getTemplateTypeMap()
                );

                // Get the set of template type identifiers defined
                // across all parameter types
                $parameter_template_type_identifiers = [];
                foreach ($method->getParameterList() as $parameter) {
                    foreach ($parameter->getUnionType()->getTypeSet()
                        as $type
                    ) {
                        if ($type instanceof TemplateType) {
                            $parameter_template_type_identifiers[] =
                                $type->getName();
                        }
                    }
                }

                $missing_template_type_identifiers = \array_diff(
                    $template_type_identifiers,
                    $parameter_template_type_identifiers
                );

                if ($missing_template_type_identifiers) {
                    $this->emitIssue(
                        Issue::GenericConstructorTypes,
                        $node->lineno ?? 0,
                        implode(',', $missing_template_type_identifiers),
                        (string)$class->getFQSEN()
                    );
                }
            }


        } elseif ('__invoke' === $method_name) {
            $class->getUnionType()->addType(
                CallableType::instance(false)
            );
        } elseif ('__toString' === $method_name
            && !$this->context->getIsStrictTypes()
        ) {
            $class->getUnionType()->addType(
                StringType::instance(false)
            );
        }


        // Create a new context with a new scope
        return $this->context->withScope(
            $method->getInternalScope()
        );
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
     *
     * @suppress PhanUndeclaredProperty - A property element can have a docComment - it's an exception
     */
    public function visitPropDecl(Node $node) : Context
    {
        // Bomb out if we're not in a class context
        $class = $this->getContextClass();
        $docComment = '';
        $first_child_node = $node->children[0] ?? null;
        if ($first_child_node instanceof Node) {
            $docComment = $first_child_node->children['docComment'] ?? '';
        }
        // Get a comment on the property declaration
        $comment = Comment::fromStringInContext(
            $docComment,
            $this->code_base,
            $this->context,
            $node->lineno ?? 0,
            Comment::ON_PROPERTY
        );

        foreach ($node->children ?? [] as $i => $child_node) {
            // Ignore children which are not property elements
            if (!$child_node
                || $child_node->kind != \ast\AST_PROP_ELEM
            ) {
                continue;
            }
            \assert($child_node instanceof Node, 'expected property element to be Node');

            // If something goes wrong will getting the type of
            // a property, we'll store it as a future union
            // type and try to figure it out later
            $future_union_type = null;

            try {
                // Get the type of the default
                $union_type = UnionType::fromNode(
                    $this->context,
                    $this->code_base,
                    $child_node->children['default'],
                    false
                );
            } catch (IssueException $exception) {
                // TODO: (enhancement/bugfix) In daemon mode, make any user-defined types or
                // types from constants/other files a FutureUnionType, 100% of the time?
                // This will make analysis slower.
                $future_union_type = new FutureUnionType(
                    $this->code_base,
                    $this->context,
                    $child_node->children['default']
                );
                $union_type = new UnionType();
            }

            // Don't set 'null' as the type if that's the default
            // given that its the default default.
            if ($union_type->isType(NullType::instance(false))) {
                $union_type = new UnionType();
            }

            $property_name = $child_node->children['name'];

            \assert(
                \is_string($property_name),
                'Property name must be a string. '
                . 'Got '
                . print_r($property_name, true)
                . ' at '
                . $this->context
            );

            $property_name = \is_string($child_node->children['name'])
                ? $child_node->children['name']
                : '_error_';

            $property_fqsen = FullyQualifiedPropertyName::make(
                $class->getFQSEN(),
                $property_name
            );

            $property = new Property(
                clone($this->context
                    ->withLineNumberStart($child_node->lineno ?? 0)),
                $property_name,
                $union_type,
                $node->flags ?? 0,
                $property_fqsen
            );

            // Add the property to the class
            $class->addProperty($this->code_base, $property, new None);

            $property->setSuppressIssueList(
                $comment->getSuppressIssueList()
            );

            // Look for any @var declarations
            if ($variable = $comment->getVariableList()[$i] ?? null) {
                if ((string)$union_type != 'null'
                    && !$union_type->canCastToUnionType($variable->getUnionType())
                    && !$property->hasSuppressIssue(Issue::TypeMismatchProperty)
                ) {
                    $this->emitIssue(
                        Issue::TypeMismatchProperty,
                        $child_node->lineno ?? 0,
                        (string)$union_type,
                        (string)$property->getFQSEN(),
                        (string)$variable->getUnionType()
                    );
                }

                // Set the declared type to the doc-comment type and add
                // |null if the default value is null
                $property->getUnionType()->addUnionType(
                    $variable->getUnionType()
                );
            }

            $property->setIsDeprecated($comment->isDeprecated());
            $property->setIsNSInternal($comment->isNSInternal());

            // Wait until after we've added the (at)var type
            // before setting the future so that calling
            // $property->getUnionType() doesn't force the
            // future to be reified.
            if ($future_union_type instanceof FutureUnionType) {
                $property->setFutureUnionType($future_union_type);
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
     *
     * @suppress PhanUndeclaredProperty - class const elements are exceptions, and can have docComment properties.
     *                                    They can't have endLineno, but may have it in the future.
     */
    public function visitClassConstDecl(Node $node) : Context
    {
        $class = $this->getContextClass();

        foreach ($node->children ?? [] as $child_node) {
            \assert($child_node instanceof Node, 'expected class const element to be a Node');
            $name = $child_node->children['name'];

            $fqsen = FullyQualifiedClassConstantName::fromStringInContext(
                $name,
                $this->context
            );

            // Get a comment on the declaration
            $comment = Comment::fromStringInContext(
                $child_node->children['docComment'] ?? '',
                $this->code_base,
                $this->context,
                $child_node->lineno ?? 0,
                Comment::ON_CONST
            );

            $line_number_start = $child_node->lineno ?? 0;
            $constant = new ClassConstant(
                $this->context
                    ->withLineNumberStart($line_number_start)
                    ->withLineNumberEnd($child_node->endLineno ?? $line_number_start),
                $name,
                new UnionType(),
                $node->flags ?? 0,
                $fqsen
            );

            $constant->setIsDeprecated($comment->isDeprecated());
            $constant->setIsNSInternal($comment->isNSInternal());
            $constant->setIsOverrideIntended($comment->isOverrideIntended());
            $constant->setSuppressIssueList($comment->getSuppressIssueList());

            $constant->setFutureUnionType(
                new FutureUnionType(
                    $this->code_base,
                    $this->context,
                    $child_node->children['value']
                )
            );

            $class->addConstant(
                $this->code_base,
                $constant
            );
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_CONST`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     *
     * @suppress PhanUndeclaredProperty - const elements are Nodes, but can have docComment.
     */
    public function visitConstDecl(Node $node) : Context
    {
        foreach ($node->children ?? [] as $child_node) {
            \assert($child_node instanceof Node);
            $this->addConstant(
                $child_node,
                $child_node->children['name'],
                $child_node->children['value'],
                $child_node->flags ?? 0,
                $child_node->children['docComment'] ?? ''
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
    public function visitFuncDecl(Node $node) : Context
    {
        $function_name = (string)$node->children['name'];

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

        } while ($this->code_base->hasFunctionWithFQSEN(
            $function_fqsen
        ));

        $func = Func::fromNode(
            $this->context
                ->withLineNumberStart($node->lineno ?? 0)
                ->withLineNumberEnd(Util::getEndLineno($node) ?? 0),
            $this->code_base,
            $node,
            $function_fqsen
        );

        $this->code_base->addFunction($func);

        // Send the context into the function and reset the scope
        $context = $this->context->withScope(
            $func->getInternalScope()
        );

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
    public function visitClosure(Node $node) : Context
    {
        $closure_fqsen = FullyQualifiedFunctionName::fromClosureInContext(
            $this->context->withLineNumberStart($node->lineno ?? 0),
            $node
        );

        $func = Func::fromNode(
            $this->context,
            $this->code_base,
            $node,
            $closure_fqsen
        );

        $this->code_base->addFunction($func);

        // Send the context into the function and reset the scope
        // (E.g. to properly check for the presence of `return` statements.
        $context = $this->context->withScope(
            $func->getInternalScope()
        );

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
    public function visitCall(Node $node) : Context
    {
        // If this is a call to a method that indicates that we
        // are treating the method in scope as a varargs method,
        // then set its optional args to something very high so
        // it can be called with anything.
        $expression = $node->children['expr'];

        if ($expression->kind === \ast\AST_NAME) {
            $function_name = \strtolower($expression->children['name']);
            if (\in_array($function_name, [
                'func_get_args', 'func_get_arg', 'func_num_args'
            ], true)) {
                if ($this->context->isInFunctionLikeScope()) {
                    $this->context->getFunctionLikeInScope($this->code_base)
                                  ->setNumberOfOptionalParameters(999999);
                }
            } else if ($function_name === 'define') {
                $args = $node->children['args'];
                if ($args->kind === \ast\AST_ARG_LIST
                    && isset($args->children[0])
                    && \is_string($args->children[0])
                ) {
                    $this->addConstant(
                        $node,
                        $args->children[0],
                        $args->children[1] ?? null,
                        0,
                        ''
                    );
                }
            } else if ($function_name === 'class_alias') {
                if (Config::getValue('enable_class_alias_support') && $this->context->isInGlobalScope()) {
                    $this->recordClassAlias($node);
                }
            }
        }
        if (Config::get_backward_compatibility_checks()) {
            $this->analyzeBackwardCompatibility($node);

            foreach ($node->children['args']->children ?? [] as $arg_node) {
                if ($arg_node instanceof Node) {
                    $this->analyzeBackwardCompatibility($arg_node);
                }
            }
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
    public function visitStaticCall(Node $node) : Context
    {
        $call = $node->children['class'];

        if ($call->kind == \ast\AST_NAME) {
            $func_name = strtolower($call->children['name']);
            if ($func_name == 'parent') {
                // Make sure it is not a crazy dynamic parent method call
                if (!($node->children['method'] instanceof Node)) {
                    $meth = \strtolower($node->children['method']);

                    if ($meth == '__construct') {
                        $class = $this->getContextClass();
                        $class->setIsParentConstructorCalled(true);
                    }
                }
            }
        }

        return $this->context;
    }

    /**
     * Analyze a node for syntax backward compatibility, if that option is enabled
     * @return void
     */
    private function analyzeBackwardCompatibility(Node $node)
    {
        if (Config::get_backward_compatibility_checks()) {
            (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->analyzeBackwardCompatibility();
        }
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
    public function visitReturn(Node $node) : Context
    {
        $this->analyzeBackwardCompatibility($node);

        // Make sure we're actually returning from a method.
        if (!$this->context->isInFunctionLikeScope()) {
            return $this->context;
        }

        // Get the method/function/closure we're in
        $method = $this->context->getFunctionLikeInScope(
            $this->code_base
        );

        \assert(!empty($method),
            "We're supposed to be in either method or closure scope."
        );

        // Mark the method as returning something
        if (($node->children['expr'] ?? null) !== null) {
            $method->setHasReturn(true);
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_YIELD`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitYield(Node $node) : Context
    {
        return $this->analyzeYield($node);
    }

    /**
     * Visit a node with kind `\ast\AST_YIELD_FROM`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitYieldFrom(Node $node) : Context
    {
        return $this->analyzeYield($node);
    }


    /**
     * Visit a node with kind `\ast\AST_YIELD_FROM` or kind `\ast_YIELD`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    private function analyzeYield(Node $node) : Context {
        $this->analyzeBackwardCompatibility($node);

        // Make sure we're actually returning from a method.
        if (!$this->context->isInFunctionLikeScope()) {
            return $this->context;
        }

        // Get the method/function/closure we're in
        $method = $this->context->getFunctionLikeInScope(
            $this->code_base
        );

        \assert(!empty($method),
            "We're supposed to be in either method or closure scope."
        );

        // Mark the method as yielding something (and returning a generator)
        $method->setHasYield(true);
        $method->setHasReturn(true);

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
    public function visitPrint(Node $node) : Context
    {
        // Analyze backward compatibility for the arguments of this print statement.
        $this->analyzeBackwardCompatibility($node);
        return $this->context;
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
    public function visitEcho(Node $node) : Context
    {
        // Analyze backward compatibility for the arguments of this echo statement.
        $this->analyzeBackwardCompatibility($node);
        return $this->context;
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
    public function visitMethodCall(Node $node) : Context
    {
        // Analyze backward compatibility for the arguments of this method call
        $this->analyzeBackwardCompatibility($node);
        return $this->context;
    }

    public function visitAssign(Node $node) : Context
    {
        if (!Config::get_backward_compatibility_checks()) {
            return $this->context;
        }
        // Analyze the assignment for compatibility with some
        // breaking changes betweeen PHP5 and PHP7.
        $var_node = $node->children['var'];
        if ($var_node instanceof Node) {
            $this->analyzeBackwardCompatibility($var_node);
        }
        $expr_node = $node->children['expr'];
        if ($expr_node instanceof Node) {
            $this->analyzeBackwardCompatibility($expr_node);
        }
        return $this->context;
    }

    public function visitDim(Node $node) : Context
    {
        if (!Config::get_backward_compatibility_checks()) {
            return $this->context;
        }

        if (!($node->children['expr'] instanceof Node
            && ($node->children['expr']->children['name'] ?? null) instanceof Node)
        ) {
            return $this->context;
        }

        // check for $$var[]
        if ($node->children['expr']->kind == \ast\AST_VAR
            && $node->children['expr']->children['name']->kind == \ast\AST_VAR
        ) {
            $temp = $node->children['expr']->children['name'];
            $depth = 1;
            while ($temp instanceof Node) {
                \assert(
                    isset($temp->children['name']),
                    "Expected to find a name in context, something else found."
                );
                $temp = $temp->children['name'];
                $depth++;
            }
            $dollars = str_repeat('$', $depth);
            $ftemp = new \SplFileObject($this->context->getFile());
            $ftemp->seek($node->lineno-1);
            $line = $ftemp->current();
            \assert(\is_string($line));
            unset($ftemp);
            if (strpos($line, '{') === false
                || strpos($line, '}') === false
            ) {
                $this->emitIssue(
                    Issue::CompatibleExpressionPHP7,
                    $node->lineno ?? 0,
                    "{$dollars}{$temp}[]"
                );
            }

        // $foo->$bar['baz'];
        } elseif (!empty($node->children['expr']->children[1])
            && ($node->children['expr']->children[1] instanceof Node)
            && ($node->children['expr']->kind == \ast\AST_PROP)
            && ($node->children['expr']->children[0]->kind == \ast\AST_VAR)
            && ($node->children['expr']->children[1]->kind == \ast\AST_VAR)
        ) {
            $ftemp = new \SplFileObject($this->context->getFile());
            $ftemp->seek($node->lineno-1);
            $line = $ftemp->current();
            \assert(\is_string($line));
            unset($ftemp);
            if (\strpos($line, '{') === false
                || \strpos($line, '}') === false
            ) {
                $this->emitIssue(
                    Issue::CompatiblePHP7,
                    $node->lineno ?? 0
                );
            }
        }

        return $this->context;
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
    public function visitDeclare(Node $node) : Context
    {
        $declares = $node->children['declares'];
        $name = $declares->children[0]->children['name'];
        $value = $declares->children[0]->children['value'];
        if ('strict_types' === $name) {
            return $this->context->withStrictTypes($value);
        }

        return $this->context;
    }

    /**
     * @param Node $node
     * The node where the constant was found
     *
     * @param string $name
     * The name of the constant
     *
     * @param Node|mixed $value
     * Either a node or a constant to be used as the value of
     * the constant.
     *
     * @param int $flags
     * Any flags on the definition of the constant
     *
     * @param string $comment_string
     * A possibly empty comment string on the declaration
     *
     * @return void
     */
    private function addConstant(
        Node $node,
        string $name,
        $value,
        int $flags = 0,
        string $comment_string
    ) {
        // Give it a fully-qualified name
        $fqsen = FullyQualifiedGlobalConstantName::fromStringInContext(
            $name,
            $this->context
        );

        // Create the constant
        $constant = new GlobalConstant(
            $this->context
                ->withLineNumberStart($node->lineno ?? 0),
            $name,
            new UnionType(),
            $flags,
            $fqsen
        );

        // Get a comment on the declaration
        $comment = Comment::fromStringInContext(
            $comment_string,
            $this->code_base,
            $this->context,
            $node->lineno ?? 0,
            Comment::ON_CONST
        );

        $constant->setFutureUnionType(
            new FutureUnionType(
                $this->code_base,
                $this->context,
                $value
            )
        );

        $constant->setIsDeprecated($comment->isDeprecated());
        $constant->setIsNSInternal($comment->isNSInternal());

        $this->code_base->addGlobalConstant(
            $constant
        );
    }

    /**
     * @return Clazz
     * Get the class on this scope or fail real hard
     */
    private function getContextClass() : Clazz
    {
        \assert($this->context->isInClassScope(),
            "Must be in class scope");
        return $this->context->getClassInScope($this->code_base);
    }

    /**
     * Return the existence of a class_alias from one FQSEN to the other.
     * Modifies $this->codebase if successful.
     *
     * @param Node $node - An AST_CALL node with name 'class_alias' to attempt to resolve
     * @return void
     */
    private function recordClassAlias(Node $node)
    {
        $args = $node->children['args']->children;
        if (\count($args) < 2 || \count($args) > 3) {
            return;
        }
        try {
            $original_fqsen = $this->resolveClassNameInContext($args[0]);
            $alias_fqsen = $this->resolveClassNameInContext($args[1]);
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
            return;
        }

        if ($original_fqsen === null || $alias_fqsen === null) {
            return;
        }

        // Add the class alias during parse phase.
        // Figure out if any of the aliases are wrong after analysis phase.
        $this->code_base->addClassAlias($original_fqsen, $alias_fqsen, $this->context, $node->lineno ?? 0);
    }

    /**
     * @param Node|string|float|int $arg
     * A function argument to resolve into an FQSEN
     *
     * @return ?FullyQualifiedClassName
     * @throws IssueException if the list of possible classes couldn't be determined.
     */
    private function resolveClassNameInContext($arg)
    {
        if (\is_string($arg)) {
            // Class_alias treats arguments as fully qualified strings.
            return FullyQualifiedClassName::fromFullyQualifiedString($arg);
        }
        if ($arg instanceof Node
            && $arg->kind === \ast\AST_CLASS_CONST
            && \strcasecmp($arg->children['const'], 'class') === 0
        ) {
            $class_type = (new ContextNode(
                $this->code_base,
                $this->context,
                $arg->children['class']
            ))->getClassUnionType();

            // If we find a class definition, then return it. There should be 0 or 1.
            // (Expressions such as 'int::class' are syntactically valid, but would have 0 results).
            foreach ($class_type->asClassFQSENList($this->context) as $class_fqsen) {
                return $class_fqsen;
            }
        }

        return null;
    }
}
