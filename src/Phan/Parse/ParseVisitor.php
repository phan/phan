<?php declare(strict_types=1);

namespace Phan\Parse;

use AssertionError;
use ast;
use ast\Node;
use InvalidArgumentException;
use Phan\Analysis\ScopeVisitor;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Daemon;
use Phan\Exception\FQSENException;
use Phan\Exception\IssueException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Comment;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionFactory;
use Phan\Language\Element\FunctionInterface;
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
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use Phan\Library\FileCache;
use Phan\Library\None;

/**
 * The class is a visitor for AST nodes that does parsing. Each
 * visitor populates the $code_base with any
 * globally accessible structural elements and will return a
 * possibly new context as modified by the given node.
 *
 * @property-read CodeBase $code_base
 *
 * @phan-file-suppress PhanUnusedPublicMethodParameter implementing faster no-op methods for common visit*
 * @phan-file-suppress PhanPartialTypeMismatchArgument
 * @phan-file-suppress PhanPartialTypeMismatchArgumentInternal
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
    /*
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        parent::__construct($code_base, $context);
    }
     */

    /**
     * Visit a node with kind `\ast\AST_CLASS`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     * @throws FQSENException if the node has invalid names
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
        if (!$class_name) {
            return $this->context;
        }

        $class_fqsen = FullyQualifiedClassName::fromStringInContext(
            $class_name,
            $this->context
        );

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
            ->withLineNumberEnd($node->endLineno ?? 0);

        $class = new Clazz(
            $class_context,
            $class_name,
            $class_fqsen->asRealUnionType(),
            $node->flags,
            $class_fqsen
        );
        $class->setDeclId($node->children['__declId']);
        $class->setDidFinishParsing(false);
        try {
            // Set the scope of the class's context to be the
            // internal scope of the class
            $class_context = $class_context->withScope(
                $class->getInternalScope()
            );

            $doc_comment = $node->children['docComment'] ?? '';
            $class->setDocComment($doc_comment);

            // Add the class to the code base as a globally
            // accessible object
            // This must be done before Comment::fromStringInContext
            // so that the class definition is available there.
            $this->code_base->addClass($class);

            // Get a comment on the class declaration
            $comment = Comment::fromStringInContext(
                $doc_comment,
                $this->code_base,
                $class_context,
                $node->lineno,
                Comment::ON_CLASS
            );

            // Add any template types parameterizing a generic class
            foreach ($comment->getTemplateTypeList() as $template_type) {
                $class->getInternalScope()->addTemplateType($template_type);
            }

            // Handle @phan-immutable, @deprecated, @internal,
            // @phan-forbid-undeclared-magic-properties, and @phan-forbid-undeclared-magic-methods
            $class->setPhanFlags($class->getPhanFlags() | $comment->getPhanFlagsForClass());

            $class->setSuppressIssueSet(
                $comment->getSuppressIssueSet()
            );

            // Depends on code_base for checking existence of __get and __set.
            // TODO: Add a check in analyzeClasses phase that magic @property declarations
            // are limited to classes with either __get or __set declared (or interface/abstract
            $class->setMagicPropertyMap(
                $comment->getMagicPropertyMap(),
                $this->code_base
            );

            // Depends on code_base for checking existence of __call or __callStatic.
            // TODO: Add a check in analyzeClasses phase that magic @method declarations
            // are limited to classes with either __get or __set declared (or interface/abstract)
            $class->setMagicMethodMap(
                $comment->getMagicMethodMap(),
                $this->code_base
            );

            // Look to see if we have a parent class
            $extends_node = $node->children['extends'] ?? null;
            if ($extends_node instanceof Node) {
                $parent_class_name = (string)UnionTypeVisitor::unionTypeFromClassNode($this->code_base, $this->context, $extends_node);

                // The name is fully qualified.
                $parent_fqsen = FullyQualifiedClassName::fromFullyQualifiedString(
                    $parent_class_name
                );

                // Set the parent for the class
                $class->setParentType($parent_fqsen->asType(), $extends_node->lineno);
            }

            // If the class explicitly sets its overriding extension type,
            // set that on the class
            $inherited_type_option = $comment->getInheritedTypeOption();
            if ($inherited_type_option->isDefined()) {
                $class->setParentType($inherited_type_option->get());
            }
            $class->setMixinTypes($comment->getMixinTypes());

            // Add any implemented interfaces
            foreach ($node->children['implements']->children ?? [] as $name_node) {
                if (!$name_node instanceof Node) {
                    throw new AssertionError('Expected list of AST_NAME nodes');
                }
                $name = (string)UnionTypeVisitor::unionTypeFromClassNode($this->code_base, $this->context, $name_node);
                $class->addInterfaceClassFQSEN(
                    FullyQualifiedClassName::fromFullyQualifiedString(
                        $name
                    ),
                    $name_node->lineno
                );
            }
        } finally {
            $class->setDidFinishParsing(true);
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
     *
     * @throws UnanalyzableException if saw an invalid AST node (e.g. from polyfill)
     */
    public function visitUseTrait(Node $node) : Context
    {
        // Bomb out if we're not in a class context
        $class = $this->getContextClass();

        // @phan-suppress-next-line PhanThrowTypeMismatchForCall should be impossible
        $trait_fqsen_list = (new ContextNode(
            $this->code_base,
            $this->context,
            $node->children['traits']
        ))->getTraitFQSENList();

        // Add each trait to the class
        foreach ($trait_fqsen_list as $trait_fqsen) {
            $class->addTraitFQSEN($trait_fqsen, $node->children['traits']->lineno ?? 0);
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
        $context = $this->context;
        $code_base = $this->code_base;

        $method_name = (string)$node->children['name'];

        $method_fqsen = FullyQualifiedMethodName::make(
            $class->getFQSEN(),
            $method_name
        );

        // Hunt for an available alternate ID if necessary
        $alternate_id = 0;
        while ($code_base->hasMethodWithFQSEN($method_fqsen)) {
            $method_fqsen =
                $method_fqsen->withAlternateId(++$alternate_id);
        }

        $method = Method::fromNode(
            clone($context),
            $code_base,
            $node,
            $method_fqsen,
            $class
        );

        if ($context->isPHPInternal()) {
            // only for stubs
            foreach (FunctionFactory::functionListFromFunction($method) as $method_variant) {
                if (!($method_variant instanceof Method)) {
                    throw new AssertionError("Expected variants of Method to be Method");
                }
                $class->addMethod($code_base, $method_variant, None::instance());
            }
        } else {
            $class->addMethod($code_base, $method, None::instance());
        }

        if ('__construct' === $method_name) {
            $class->setIsParentConstructorCalled(false);
        } elseif ('__invoke' === $method_name) {
            // TODO: More precise callable shape
            $class->addAdditionalType(CallableType::instance(false));
        } elseif ('__toString' === $method_name
            && !$this->context->isStrictTypes()
        ) {
            $class->addAdditionalType(StringType::instance(false));
        }


        // Create a new context with a new scope
        return $this->context->withScope(
            $method->getInternalScope()
        );
    }

    /**
     * Visit a node with kind `\ast\AST_PROP_GROUP`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitPropGroup(Node $node) : Context
    {
        // Bomb out if we're not in a class context
        ['props' => $props_node, 'type' => $type_node] = $node->children;
        if (!$props_node instanceof Node) {
            throw new AssertionError('Expected list of properties to be a node');
        }
        if ($type_node) {
            try {
                $real_union_type = (new UnionTypeVisitor($this->code_base, $this->context))->fromTypeInSignature($type_node);
            } catch (IssueException $e) {
                Issue::maybeEmitInstance($this->code_base, $this->context, $e->getIssueInstance());
                $real_union_type = UnionType::empty();
            }
            if (Config::get_closest_target_php_version_id() < 70400) {
                $this->emitIssue(
                    Issue::CompatibleTypedProperty,
                    $type_node->lineno,
                    ((string)$real_union_type) ?: '(unknown)'
                );
            }
        } else {
            $real_union_type = UnionType::empty();
        }
        $real_type_set = $real_union_type->getTypeSet();

        $class = $this->getContextClass();
        $doc_comment = '';
        $first_child_node = $props_node->children[0] ?? null;
        if ($first_child_node instanceof Node) {
            $doc_comment = $first_child_node->children['docComment'] ?? '';
        }
        // Get a comment on the property declaration
        $comment = Comment::fromStringInContext(
            $doc_comment,
            $this->code_base,
            $this->context,
            $props_node->lineno ?? 0,
            Comment::ON_PROPERTY
        );

        foreach ($props_node->children as $i => $child_node) {
            // Ignore children which are not property elements
            if (!($child_node instanceof Node)
                || $child_node->kind != \ast\AST_PROP_ELEM
            ) {
                continue;
            }
            $variable = $comment->getVariableList()[$i] ?? null;
            $variable_has_literals = $variable && $variable->getUnionType()->hasLiterals();

            // If something goes wrong will getting the type of
            // a property, we'll store it as a future union
            // type and try to figure it out later
            $future_union_type = null;

            $default_node = $child_node->children['default'];

            $context_for_property = (clone($this->context))->withLineNumberStart($child_node->lineno ?? 0);

            $property_name = $child_node->children['name'];

            if (!\is_string($property_name)) {
                throw new AssertionError(
                    'Property name must be a string. '
                    . 'Got '
                    . \print_r($property_name, true)
                    . ' at '
                    . $context_for_property
                );
            }


            if ($default_node === null) {
                // This is a declaration such as `public $x;` with no $default_node
                // (we don't assume the property is always null, to reduce false positives)
                // We don't need to compare this to the real union type
                $union_type = $real_union_type;
                $default_type = NullType::instance(false)->asRealUnionType();
            } else {
                if ($default_node instanceof Node) {
                    $this->checkNodeIsConstExpr($default_node);
                    $union_type = $this->resolveDefaultPropertyNode($default_node);
                    if (!$union_type) {
                        // We'll type check this union type against the real union type when the future union type is resolved
                        $future_union_type = new FutureUnionType(
                            $this->code_base,
                            $context_for_property,
                            $default_node
                        );
                        $union_type = UnionType::empty();
                    }
                } else {
                    // Get the type of the default (not a literal)
                    if ($variable_has_literals) {
                        $union_type = Type::fromObject($default_node)->asPHPDocUnionType();
                    } else {
                        $union_type = Type::nonLiteralFromObject($default_node)->asPHPDocUnionType();
                    }
                }
                $default_type = $union_type;
                // Erase the corresponding real type set to avoid false positives such as `$x->prop['field'] === null` is redundant/impossible.
                $union_type = $union_type->eraseRealTypeSetRecursively();
                if ($real_union_type->isEmpty()) {
                    if ($union_type->isType(NullType::instance(false))) {
                        $union_type = UnionType::empty();
                    }
                } else {
                    if (!$union_type->isStrictSubtypeOf($this->code_base, $real_union_type)) {
                        $this->emitIssue(
                            Issue::TypeInvalidPropertyDefaultReal,
                            $context_for_property->getLineNumberStart(),
                            $real_union_type,
                            $property_name,
                            $union_type
                        );
                        $union_type = $real_union_type;
                    }
                    $union_type = $union_type->withRealTypeSet($real_union_type->getTypeSet());
                }
            }

            $property_fqsen = FullyQualifiedPropertyName::make(
                $class->getFQSEN(),
                $property_name
            );
            if ($this->code_base->hasPropertyWithFQSEN($property_fqsen)) {
                $old_property = $this->code_base->getPropertyByFQSEN($property_fqsen);
                if ($old_property->getDefiningFQSEN() === $property_fqsen) {
                    // Note: PHPDoc properties are parsed by Phan before real properties, so they take precedence (e.g. they are more visible)
                    // PhanRedefineMagicProperty is a separate check.
                    if ($old_property->isFromPHPDoc()) {
                        continue;
                    }
                    $this->emitIssue(
                        Issue::RedefineProperty,
                        $child_node->lineno,
                        $property_name,
                        $this->context->getFile(),
                        $child_node->lineno,
                        $this->context->getFile(),
                        $old_property->getContext()->getLineNumberStart()
                    );
                    continue;
                }
            }

            $property = new Property(
                $context_for_property,
                $property_name,
                $union_type,
                $node->flags,
                $property_fqsen,
                $real_union_type
            );
            if ($variable) {
                $property->setPHPDocUnionType($variable->getUnionType());
            } elseif ($real_union_type) {
                $property->setPHPDocUnionType($real_union_type);
            }
            $property->setDefaultType($default_type);

            $property->setPhanFlags($comment->getPhanFlagsForProperty());
            $property->setDocComment($doc_comment);

            // Add the property to the class
            $class->addProperty($this->code_base, $property, None::instance());

            $property->setSuppressIssueSet(
                $comment->getSuppressIssueSet()
            );

            // Look for any @var declarations
            if ($variable) {
                $original_union_type = $union_type;
                // We try to avoid resolving $future_union_type except when necessary,
                // to avoid issues such as https://github.com/phan/phan/issues/311 and many more.
                if ($future_union_type !== null) {
                    try {
                        $original_union_type = $future_union_type->get()->eraseRealTypeSetRecursively();
                        if (!$variable_has_literals) {
                            $original_union_type = $original_union_type->asNonLiteralType();
                        }
                        // We successfully resolved the union type. We no longer need $future_union_type
                        $future_union_type = null;
                    } catch (IssueException $_) {
                        // Do nothing
                    }
                    if ($future_union_type === null) {
                        if ($original_union_type->isType(ArrayShapeType::empty())) {
                            $union_type = ArrayType::instance(false)->asPHPDocUnionType();
                        } elseif ($original_union_type->isType(NullType::instance(false))) {
                            $union_type = UnionType::empty();
                        } else {
                            $union_type = $original_union_type;
                        }
                        // Replace the empty union type with the resolved union type.
                        $property->setUnionType($union_type->withRealTypeSet($real_type_set));
                    }
                }

                if (!$original_union_type->isType(NullType::instance(false))
                    && !$original_union_type->canCastToUnionType($variable->getUnionType())
                    && !$property->checkHasSuppressIssueAndIncrementCount(Issue::TypeMismatchProperty)
                ) {
                    $this->emitIssue(
                        Issue::TypeMismatchProperty,
                        $child_node->lineno ?? 0,
                        (string)$original_union_type,
                        $property->asPropertyFQSENString(),
                        (string)$variable->getUnionType()
                    );
                }

                // Don't set 'null' as the type if that's the default
                // given that its the default default.
                if ($union_type->isType(NullType::instance(false))) {
                    $union_type = UnionType::empty();
                }

                $original_property_type = $property->getUnionType();
                $original_variable_type = $variable->getUnionType();
                $variable_type = $original_variable_type->withStaticResolvedInContext($this->context);
                if ($variable_type !== $original_variable_type) {
                    // Instance properties with (at)var static will have the same type as the class they're in
                    // TODO: Support `static[]` as well when inheriting
                    if ($property->isStatic()) {
                        $this->emitIssue(
                            Issue::StaticPropIsStaticType,
                            $variable->getLineno(),
                            $property->getRepresentationForIssue(),
                            $original_variable_type,
                            $variable_type
                        );
                    } else {
                        $property->setHasStaticInUnionType(true);
                    }
                }
                if ($variable_type->hasGenericArray() && !$original_property_type->hasTypeMatchingCallback(static function (Type $type) : bool {
                    return \get_class($type) !== ArrayType::class;
                })) {
                    // Don't convert `/** @var T[] */ public $x = []` to union type `T[]|array`
                    $property->setUnionType($variable_type->withRealTypeSet($real_type_set));
                } else {
                    // Set the declared type to the doc-comment type and add
                    // |null if the default value is null
                    $property->setUnionType($original_property_type->withUnionType($variable_type)->withRealTypeSet($real_type_set));
                }
            }

            // Wait until after we've added the (at)var type
            // before setting the future so that calling
            // $property->getUnionType() doesn't force the
            // future to be reified.
            if ($future_union_type instanceof FutureUnionType) {
                $property->setFutureUnionType($future_union_type);
            }
            if ($class->isImmutable()) {
                if (!$property->isStatic() && !$property->isWriteOnly()) {
                    $property->setIsReadOnly(true);
                }
            }
        }

        return $this->context;
    }

    /**
     * Resolve the union type of a property's default node.
     * This is being done to resolve the most common cases - e.g. `null`, `false`, and `true`
     *
     * FIXME: Handle 2+2, -1 (unary op), etc.
     */
    private function resolveDefaultPropertyNode(Node $node) : ?UnionType
    {
        if ($node->kind === ast\AST_CONST) {
            try {
                return (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getConst()->getUnionType()->eraseRealTypeSetRecursively();
            } catch (IssueException $_) {
                // ignore
            }
        }
        return null;
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
     */
    public function visitClassConstDecl(Node $node) : Context
    {
        $class = $this->getContextClass();

        foreach ($node->children as $child_node) {
            if (!$child_node instanceof Node) {
                throw new AssertionError('expected class const element to be a Node');
            }
            $name = $child_node->children['name'];
            if (!\is_string($name)) {
                throw new AssertionError('expected class const name to be a string');
            }

            $fqsen = FullyQualifiedClassConstantName::make(
                $class->getFQSEN(),
                $name
            );
            if ($this->code_base->hasClassConstantWithFQSEN($fqsen)) {
                $old_constant = $this->code_base->getClassConstantByFQSEN($fqsen);
                if ($old_constant->getDefiningFQSEN() === $fqsen) {
                    $this->emitIssue(
                        Issue::RedefineClassConstant,
                        $child_node->lineno,
                        $name,
                        $this->context->getFile(),
                        $child_node->lineno,
                        $this->context->getFile(),
                        $old_constant->getContext()->getLineNumberStart()
                    );
                    continue;
                }
            }

            // Get a comment on the declaration
            $doc_comment = $child_node->children['docComment'] ?? '';
            $comment = Comment::fromStringInContext(
                $doc_comment,
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
                UnionType::empty(),
                $node->flags ?? 0,
                $fqsen
            );

            $constant->setDocComment($doc_comment);
            $constant->setIsDeprecated($comment->isDeprecated());
            $constant->setIsNSInternal($comment->isNSInternal());
            $constant->setIsOverrideIntended($comment->isOverrideIntended());
            $constant->setSuppressIssueSet($comment->getSuppressIssueSet());
            $value_node = $child_node->children['value'];
            if ($value_node instanceof Node) {
                try {
                    self::checkIsAllowedInConstExpr($value_node);
                    $constant->setFutureUnionType(
                        new FutureUnionType(
                            $this->code_base,
                            $this->context,
                            $value_node
                        )
                    );
                } catch (InvalidArgumentException $_) {
                    $constant->setUnionType(MixedType::instance(false)->asPHPDocUnionType());
                    $this->emitIssue(
                        Issue::InvalidConstantExpression,
                        $value_node->lineno
                    );
                }
            } else {
                // This is a literal scalar value.
                // Assume that this is the only definition of the class constant and that it's not a stub for something that depends on configuration.
                //
                // TODO: What about internal stubs (isPHPInternal()) - if Phan would treat those like being from phpdoc,
                // it should do the same for FutureUnionType
                $constant->setUnionType(Type::fromObject($value_node)->asRealUnionType());
            }
            $constant->setNodeForValue($value_node);

            $class->addConstant(
                $this->code_base,
                $constant
            );
            foreach ($comment->getVariableList() as $var) {
                if ($var->getUnionType()->hasTemplateTypeRecursive()) {
                    $this->emitIssue(
                        Issue::TemplateTypeConstant,
                        $constant->getFileRef()->getLineNumberStart(),
                        (string)$constant->getFQSEN()
                    );
                }
            }
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_STATIC` (a static variable)
     */
    public function visitStatic(Node $node) : Context
    {
        $default = $node->children['default'];
        if ($default instanceof Node) {
            $this->checkNodeIsConstExpr($default);
        }
        return $this->context;
    }

    private function checkNodeIsConstExpr(Node $node) : void
    {
        try {
            self::checkIsAllowedInConstExpr($node);
        } catch (InvalidArgumentException $_) {
            $this->emitIssue(
                Issue::InvalidConstantExpression,
                $node->lineno
            );
        }
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
     */
    public function visitConstDecl(Node $node) : Context
    {
        foreach ($node->children as $child_node) {
            if (!$child_node instanceof Node) {
                throw new AssertionError("Expected global constant element to be a Node");
            }

            $value_node = $child_node->children['value'];
            try {
                self::checkIsAllowedInConstExpr($value_node);
            } catch (InvalidArgumentException $_) {
                // InvalidArgumentException was caused by an invalid node kind in a constant expression (value_node should be a Node but Phan can't tell)
                $this->emitIssue(
                    Issue::InvalidConstantExpression,
                    $value_node->lineno ?? $child_node->lineno
                );
                // Note: Global constants with invalid value expressions aren't declared.
                // However, class constants are declared with placeholders to make inheritance checks, etc. easier.
                // Both will emit PhanInvalidConstantExpression
                continue;
            }
            self::addConstant(
                $this->code_base,
                $this->context,
                $child_node->lineno,
                $child_node->children['name'],
                $value_node,
                $child_node->flags ?? 0,
                $child_node->children['docComment'] ?? '',
                true
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
        $context = $this->context;
        $code_base = $this->code_base;

        // Hunt for an un-taken alternate ID
        $alternate_id = 0;
        do {
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall this is valid
            $function_fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString(
                \rtrim($context->getNamespace(), '\\') . '\\' . $function_name
            )->withAlternateId($alternate_id++);
        } while ($code_base->hasFunctionWithFQSEN($function_fqsen));

        $func = Func::fromNode(
            $context
                ->withLineNumberStart($node->lineno ?? 0)
                ->withLineNumberEnd($node->endLineno ?? 0),
            $code_base,
            $node,
            $function_fqsen
        );

        if ($context->isPHPInternal()) {
            // only for stubs
            foreach (FunctionFactory::functionListFromFunction($func) as $func_variant) {
                if (!($func_variant instanceof Func)) {
                    throw new AssertionError("Expecteded variant of Func to be a Func");
                }
                $code_base->addFunction($func_variant);
            }
        } else {
            $code_base->addFunction($func);
        }

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
            $this->context->withLineNumberStart($node->lineno),
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
     * Visit a node with kind `\ast\AST_ARROW_FUNC`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitArrowFunc(Node $node) : Context
    {
        if (!isset($node->children['params'])) {
            $msg = "php-ast 1.0.2 or newer is required to correctly parse short arrow functions, but an older version is installed. A short arrow function was seen at $this->context";
            \fwrite(\STDERR, $msg . \PHP_EOL);
            throw new AssertionError($msg);
        }
        return $this->visitClosure($node);
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

        if ($expression instanceof Node && $expression->kind === \ast\AST_NAME) {
            $function_name = \strtolower($expression->children['name']);
            if (\in_array($function_name, [
                'func_get_args', 'func_get_arg', 'func_num_args'
            ], true)) {
                if ($this->context->isInFunctionLikeScope()) {
                    $this->context->getFunctionLikeInScope($this->code_base)
                                  ->setNumberOfOptionalParameters(FunctionInterface::INFINITE_PARAMETERS);
                }
            } elseif ($function_name === 'define') {
                $this->analyzeDefine($node);
            } elseif ($function_name === 'class_alias') {
                if (Config::getValue('enable_class_alias_support') && $this->context->isInGlobalScope()) {
                    $this->recordClassAlias($node);
                }
            }
        }
        if (Config::get_backward_compatibility_checks()) {
            $this->analyzeBackwardCompatibility($node);

            foreach ($node->children['args']->children as $arg_node) {
                if ($arg_node instanceof Node) {
                    $this->analyzeBackwardCompatibility($arg_node);
                }
            }
        }
        return $this->context;
    }

    private function analyzeDefine(Node $node) : void
    {
        $args = $node->children['args'];
        if (\count($args->children) < 2) {
            return;
        }
        $name = $args->children[0];
        if ($name instanceof Node) {
            try {
                $name_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $name, false);
            } catch (IssueException $_) {
                // If this is really an issue, we'll emit it in the analysis phase when we have all of the element definitions.
                return;
            }
            $name = $name_type->asSingleScalarValueOrNull();
        }

        if (!\is_string($name)) {
            return;
        }
        self::addConstant(
            $this->code_base,
            $this->context,
            $node->lineno,
            $name,
            $args->children[1],
            0,
            '',
            true,
            true
        );
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

        if ($call instanceof Node && $call->kind == \ast\AST_NAME) {
            $func_name = \strtolower($call->children['name']);
            if ($func_name == 'parent') {
                // Make sure it is not a crazy dynamic parent method call
                if (!($node->children['method'] instanceof Node)) {
                    $meth = \strtolower($node->children['method']);

                    if ($meth == '__construct' && $this->context->isInClassScope()) {
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
     */
    private function analyzeBackwardCompatibility(Node $node) : void
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
     *
     * TODO: Defer analysis of the inside of methods until the class gets hydrated.
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

        // Mark the method as returning something if expr is not null
        if (isset($node->children['expr'])) {
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
     *
     * TODO: Defer analysis of the inside of methods until the method/function gets hydrated.
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
    private function analyzeYield(Node $node) : Context
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
        // breaking changes between PHP5 and PHP7.
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

        $expr = $node->children['expr'];
        if (!($expr instanceof Node)) {
            return $this->context;
        }

        // check for $$var[]
        if ($expr->kind === \ast\AST_VAR
            && ($expr->children['name']->kind ?? null) === \ast\AST_VAR
        ) {
            $temp = $expr->children['name'];
            $depth = 1;
            while ($temp instanceof Node) {
                if (!isset($temp->children['name'])) {
                    throw new AssertionError("Expected to find a name in context, something else found.");
                }
                $temp = $temp->children['name'];
                $depth++;
            }
            $dollars = \str_repeat('$', $depth);
            $cache_entry = FileCache::getOrReadEntry($this->context->getFile());
            $line = $cache_entry->getLine($node->lineno);
            if (!\is_string($line)) {
                return $this->context;
            }
            if (\strpos($line, '{') === false
                || \strpos($line, '}') === false
            ) {
                $this->emitIssue(
                    Issue::CompatibleExpressionPHP7,
                    $node->lineno ?? 0,
                    "{$dollars}{$temp}[]"
                );
            }

        // $foo->$bar['baz'];
        } elseif ($expr->kind === \ast\AST_PROP &&
            ($expr->children['expr']->kind ?? null) === ast\AST_VAR &&
            ($expr->children['prop']->kind ?? null) === ast\AST_VAR
        ) {
            $cache_entry = FileCache::getOrReadEntry($this->context->getFile());
            $line = $cache_entry->getLines()[$node->lineno] ?? null;
            if (!\is_string($line)) {
                return $this->context;
            }
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
     * Add a constant to the codebase
     *
     * @param CodeBase $code_base
     * The global code base in which we store all
     * state
     *
     * @param Context $context
     * The context of the parser at the node which declares the constant
     *
     * @param int $lineno
     * The line number where the node declaring the constant was found
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
     * @param bool $use_future_union_type
     * Should this lazily resolve the value of the constant declaration?
     *
     * @param bool $is_fully_qualified
     * Is the provided $name already fully qualified?
     */
    public static function addConstant(
        CodeBase $code_base,
        Context $context,
        int $lineno,
        string $name,
        $value,
        int $flags,
        string $comment_string,
        bool $use_future_union_type,
        bool $is_fully_qualified = false
    ) : void {
        $i = \strrpos($name, '\\');
        if ($i !== false) {
            $name_fragment = (string)\substr($name, $i + 1);
        } else {
            $name_fragment = $name;
        }
        if (\in_array(\strtolower($name_fragment), ['true', 'false', 'null'], true)) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::ReservedConstantName,
                $lineno,
                $name
            );
            return;
        }
        try {
            // Give it a fully-qualified name
            if ($is_fully_qualified) {
                $fqsen = FullyQualifiedGlobalConstantName::fromFullyQualifiedString(
                    $name
                );
            } else {
                $fqsen = FullyQualifiedGlobalConstantName::fromStringInContext(
                    $name,
                    $context
                );
            }
        } catch (InvalidArgumentException $_) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::InvalidConstantFQSEN,
                $lineno,
                $name
            );
            return;
        } catch (FQSENException $_) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::InvalidConstantFQSEN,
                $lineno,
                $name
            );
            return;
        }

        // Create the constant
        $constant = new GlobalConstant(
            $context->withLineNumberStart($lineno),
            $name,
            UnionType::fromFullyQualifiedRealString('array|bool|float|int|string|resource|null'),
            $flags,
            $fqsen
        );
        // $is_fully_qualified is true for define('name', $value)
        // define() is typically used to conditionally set constants or to set them to variable values.
        // TODO: Could add 'configuration_constant_set' to add additional constants to treat as dynamic such as PHP_OS, PHP_VERSION_ID, etc. (convert literals to non-literal types?)
        $constant->setIsDynamicConstant($is_fully_qualified);

        if ($code_base->hasGlobalConstantWithFQSEN($fqsen)) {
            $other_constant = $code_base->getGlobalConstantByFQSEN($fqsen);
            $other_context = $other_constant->getContext();
            if (!$other_context->equals($context)) {
                // Be consistent about the constant's type and only track the first declaration seen when parsing (or redeclarations)
                // Note that global constants don't have alternates.
                return;
            }
            // Keep track of old references to the new constant
            $constant->copyReferencesFrom($other_constant);

            // Otherwise, add the constant now that we know about all of the elements in the codebase
        }

        // Get a comment on the declaration
        $comment = Comment::fromStringInContext(
            $comment_string,
            $code_base,
            $context,
            $lineno,
            Comment::ON_CONST
        );

        if ($use_future_union_type) {
            if ($value instanceof Node) {
                $constant->setFutureUnionType(
                    new FutureUnionType(
                        $code_base,
                        $context,
                        $value
                    )
                );
            } else {
                $constant->setUnionType(Type::fromObject($value)->asRealUnionType());
            }
        } else {
            $constant->setUnionType(UnionTypeVisitor::unionTypeFromNode($code_base, $context, $value));
        }

        $constant->setNodeForValue($value);
        $constant->setDocComment($comment_string);

        $constant->setIsDeprecated($comment->isDeprecated());
        $constant->setIsNSInternal($comment->isNSInternal());

        $code_base->addGlobalConstant(
            $constant
        );
    }

    /**
     * @return Clazz
     * Get the class on this scope or fail real hard
     */
    private function getContextClass() : Clazz
    {
        // throws AssertionError if not in class scope
        return $this->context->getClassInScope($this->code_base);
    }

    /**
     * Return the existence of a class_alias from one FQSEN to the other.
     * Modifies $this->codebase if successful.
     *
     * Supports 'MyClass' and MyClass::class
     *
     * @param Node $node - An AST_CALL node with name 'class_alias' to attempt to resolve
     */
    private function recordClassAlias(Node $node) : void
    {
        $args = $node->children['args']->children;
        if (\count($args) < 2 || \count($args) > 3) {
            return;
        }
        $code_base = $this->code_base;
        $context = $this->context;
        try {
            $original_fqsen = (new ContextNode($code_base, $context, $args[0]))->resolveClassNameInContext();
            $alias_fqsen = (new ContextNode($code_base, $context, $args[1]))->resolveClassNameInContext();
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $code_base,
                $context,
                $exception->getIssueInstance()
            );
            return;
        }

        if ($original_fqsen === null || $alias_fqsen === null) {
            return;
        }

        // Add the class alias during parse phase.
        // Figure out if any of the aliases are wrong after analysis phase.
        $this->code_base->addClassAlias($original_fqsen, $alias_fqsen, $context, $node->lineno ?? 0);
    }

    /**
     * Visit a node with kind `\ast\AST_NAMESPACE`
     * Store the maps for use statements in the CodeBase to use later during analysis.
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new context resulting from parsing the node
     */
    public function visitNamespace(Node $node) : Context
    {
        $context = $this->context;
        // @phan-suppress-next-line PhanAccessMethodInternal addParsedNamespaceMap and getNamespaceMap
        $this->code_base->addParsedNamespaceMap($context->getFile(), $context->getNamespace(), $context->getNamespaceId(), $context->getNamespaceMap());
        return parent::visitNamespace($node);
    }

    // common no-ops
    public function visitArrayElem(Node $node) : Context
    {
        return $this->context;
    }
    public function visitVar(Node $node) : Context
    {
        return $this->context;
    }
    public function visitName(Node $node) : Context
    {
        return $this->context;
    }
    public function visitArgList(Node $node) : Context
    {
        return $this->context;
    }
    public function visitStmtList(Node $node) : Context
    {
        return $this->context;
    }
    public function visitProp(Node $node) : Context
    {
        return $this->context;
    }
    public function visitArray(Node $node) : Context
    {
        return $this->context;
    }
    public function visitBinaryOp(Node $node) : Context
    {
        return $this->context;
    }

    /**
     * @internal
     */
    const ALLOWED_CONST_EXPRESSION_KINDS = [
        ast\AST_ARRAY_ELEM => true,
        ast\AST_ARRAY => true,
        ast\AST_BINARY_OP => true,
        ast\AST_CLASS_CONST => true,
        ast\AST_CLASS_NAME => true,
        ast\AST_CONDITIONAL => true,
        ast\AST_CONST => true,
        ast\AST_DIM => true,
        ast\AST_MAGIC_CONST => true,
        ast\AST_NAME => true,
        ast\AST_UNARY_OP => true,
        ast\AST_UNPACK => true,
    ];

    /**
     * This is meant to avoid causing errors in Phan where Phan expects a constant to be found.
     *
     * @param Node|string|float|int|bool|null $n
     *
     * @return void - If this doesn't throw, then $n is a valid constant AST.
     *
     * @throws InvalidArgumentException if this is not allowed in a constant expression
     * Based on zend_bool zend_is_allowed_in_const_expr from Zend/zend_compile.c
     *
     * @internal
     */
    public static function checkIsAllowedInConstExpr($n) : void
    {
        if (!($n instanceof Node)) {
            return;
        }
        if (!\array_key_exists($n->kind, self::ALLOWED_CONST_EXPRESSION_KINDS)) {
            throw new InvalidArgumentException();
        }
        foreach ($n->children as $child_node) {
            self::checkIsAllowedInConstExpr($child_node);
        }
    }

    /**
     * @param Node|string|float|int|bool|null $n
     * @return bool - If true, then $n is a valid constant AST.
     */
    public static function isConstExpr($n) : bool
    {
        try {
            self::checkIsAllowedInConstExpr($n);
            return true;
        } catch (InvalidArgumentException $_) {
            return false;
        }
    }

    protected const ALLOWED_NON_VARIABLE_EXPRESSION_KINDS = [
        // Contains everything from ALLOWED_CONST_EXPRESSION_KINDS
        ast\AST_ARRAY_ELEM => true,
        ast\AST_ARRAY => true,
        ast\AST_BINARY_OP => true,
        ast\AST_CLASS_CONST => true,
        ast\AST_CLASS_NAME => true,
        ast\AST_CONDITIONAL => true,
        ast\AST_CONST => true,
        ast\AST_DIM => true,
        ast\AST_MAGIC_CONST => true,
        ast\AST_NAME => true,
        ast\AST_UNARY_OP => true,

        // In addition to expressions where the real type can be statically inferred (assuming types of child nodes were correctly inferred)
        ast\AST_ARG_LIST => true,
        ast\AST_CALL => true,
        ast\AST_CLONE => true,
        ast\AST_EMPTY => true,
        ast\AST_ISSET => true,
        ast\AST_NEW => true,
        ast\AST_PRINT => true,
        ast\AST_SHELL_EXEC => true,
        ast\AST_STATIC_CALL => true,
        ast\AST_STATIC_PROP => true,
        ast\AST_UNPACK => true,

        // Stop here
        ast\AST_CLOSURE => false,
        ast\AST_CLASS => false,
    ];

    /**
     * This is meant to tell Phan expects an expression not depending on the current scope (e.g. global, loop) to be found.
     *
     * @param Node|string|float|int|bool|null $n
     *
     * @return void - If this doesn't throw, then $n is a valid constant AST.
     *
     * @throws InvalidArgumentException if this is not allowed in a constant expression
     * Based on zend_bool zend_is_allowed_in_const_expr from Zend/zend_compile.c
     *
     * @internal
     */
    private static function checkIsNonVariableExpression($n) : void
    {
        if (!($n instanceof Node)) {
            return;
        }
        $value = self::ALLOWED_NON_VARIABLE_EXPRESSION_KINDS[$n->kind] ?? null;
        if ($value === true) {
            foreach ($n->children as $child_node) {
                self::checkIsNonVariableExpression($child_node);
            }
            return;
        }
        if ($value !== false) {
            throw new InvalidArgumentException();
        }
        // Skip checking child nodes for anonymous classes, closures
    }

    /**
     * @param Node|string|float|int|bool|null $n
     * @return bool - If true, then the inferred type for $n does not depend on the current scope, but isn't necessarily constant (e.g. static method invocation in loop, global)
     */
    public static function isNonVariableExpr($n) : bool
    {
        try {
            self::checkIsNonVariableExpression($n);
            return true;
        } catch (InvalidArgumentException $_) {
            return false;
        }
    }
}
