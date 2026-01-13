<?php

declare(strict_types=1);

namespace Phan\Parse;

use AssertionError;
use ast;
use ast\Node;
use InvalidArgumentException;
use Phan\Analysis\ScopeVisitor;
use Phan\AST\ASTReverter;
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
use Phan\Language\Element\Attribute;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Comment;
use Phan\Language\Element\Comment\Builder;
use Phan\Language\Element\EnumCase;
use Phan\Language\Element\Flags;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionFactory;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\GlobalConstant;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\Property;
use Phan\Language\Element\PropertyHook;
use Phan\Language\ElementContext;
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
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NeverType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use Phan\Library\IncrementalAnalysis\DependencyTracker;
use Phan\Library\None;

use function count;

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
 *
 * @method Context __invoke(Node $node)
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
    public function visitClass(Node $node): Context
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
        if ($class_name === '') {
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

        $class_fqsen->setPreferredName($class_name);

        if ($alternate_id > 0) {
            Daemon::debugf("Using an alternate for %s: %d\n", $class_fqsen, $alternate_id);
        }

        // Build the class from what we know so far
        $class_context = $this->context
            ->withLineNumberStart($node->lineno)
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
        $class->setAttributeList(Attribute::fromNodeForAttributeList(
            $this->code_base,
            $class_context,
            $node->children['attributes'] ?? null
        ));
        if ($class->hasDeprecatedAttribute()) {
            $class->setIsDeprecated(true);
        }
        if ($node->flags & ast\flags\CLASS_ENUM) {
            $this->populateEnumClass($class, $class_context, $node);
        }
        if (($node->flags & ast\flags\CLASS_READONLY) && Config::get_closest_target_php_version_id() < 80200) {
            $this->emitIssue(
                Issue::CompatibleReadonlyClass,
                $node->lineno,
                (string)$class_fqsen
            );
        }

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

            // Track class declaration for incremental analysis
            DependencyTracker::track($class_fqsen->__toString(), 'declares');

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
                $parent_class_name = UnionTypeVisitor::unionTypeFromClassNode($this->code_base, $this->context, $extends_node)->__toString();

                // The name is fully qualified.
                // This will throw an FQSENException if php-ast or the polyfill unexpectedly parsed an invalid class name.
                $parent_fqsen = FullyQualifiedClassName::fromFullyQualifiedString(
                    $parent_class_name
                );

                // Set the parent for the class
                $class->setParentType($parent_fqsen->asType(), $extends_node->lineno);

                // Track parent class dependency for incremental analysis
                DependencyTracker::track($parent_fqsen->__toString(), 'extends');
            }

            // If the class explicitly sets its overriding extension type,
            // set that on the class
            $inherited_type_option = $comment->getInheritedTypeOption();
            if ($inherited_type_option->isDefined()) {
                if ($class->isClass()) {
                    // TODO: Emit issue if this does not match `class ... extends`
                    $class->setParentType($inherited_type_option->get());
                } elseif ($class->isTrait()) {
                    // This seems to work only by accident; traits are not supposed to have parent classes.
                    // However, users rely on it, and it's kind of neat to have. See discussion on #5002.
                    // TODO: Emit issue if this does not match the `use` on the referenced class
                    // TODO: Emit issue if there is a `use` on any other class (and not a subclass)
                    $class->setParentType($inherited_type_option->get());
                } else {
                    // TODO: Support generic interfaces
                    // TODO: Emit issue if this does not match `interface ... extends`
                    // TODO: Support more than one @extends
                }
            }

            $class->setMixinTypes($comment->getMixinTypes());

            // Add any implemented interfaces
            foreach ($node->children['implements']->children ?? [] as $name_node) {
                if (!$name_node instanceof Node) {
                    throw new AssertionError('Expected list of AST_NAME nodes');
                }
                $name = (string)UnionTypeVisitor::unionTypeFromClassNode($this->code_base, $this->context, $name_node);
                $interface_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($name);
                $class->addInterfaceClassFQSEN(
                    $interface_fqsen,
                    $name_node->lineno
                );

                // Track interface dependency for incremental analysis
                DependencyTracker::track($interface_fqsen->__toString(), 'implements');
            }

            // Process @implements annotations to provide template parameters for interfaces
            foreach ($comment->getImplementedTypes() as $implemented_type) {
                // Extract the FQSEN from the type (e.g., Iterator<int, T> -> Iterator)
                $interface_fqsen = FullyQualifiedClassName::fromType($implemented_type);
                $class->setInterfaceType($interface_fqsen, $implemented_type);
            }

            // Process @use annotations to provide template parameters for traits
            foreach ($comment->getUsedTraitTypes() as $used_trait_type) {
                // Extract the FQSEN from the type (e.g., Repository<User> -> Repository)
                $trait_fqsen = FullyQualifiedClassName::fromType($used_trait_type);
                $class->setTraitType($trait_fqsen, $used_trait_type);
            }

            foreach ($comment->getRequiredExtendsTypes() as $required_type) {
                if (!$required_type->isObjectWithKnownFQSEN()) {
                    continue;
                }
                $class->addRequiredExtendsFQSEN(FullyQualifiedClassName::fromType($required_type));
            }

            foreach ($comment->getRequiredImplementsTypes() as $required_type) {
                if (!$required_type->isObjectWithKnownFQSEN()) {
                    continue;
                }
                $class->addRequiredImplementsFQSEN(FullyQualifiedClassName::fromType($required_type));
            }
        } finally {
            $class->setDidFinishParsing(true);
        }

        return $class_context;
    }

    private function populateEnumClass(Clazz $class, Context $class_context, Node $node): void
    {
        $type_node = $node->children['type'] ?? null;
        $class_fqsen = $class->getFQSEN();
        $case_union_type = $class_fqsen->asType()->asRealUnionType();
        $case_field_types = [];
        foreach ($node->children['stmts']->children ?? [] as $case) {
            if ($case instanceof Node && $case->kind === ast\AST_ENUM_CASE) {
                // TODO: If individual enum cases get distinct types in the type system, replace this with that
                $case_field_types[] = $case_union_type;
            }
        }
        $case_count = count($case_field_types);
        if ($type_node) {
            $enum_type = UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $class_context,
                $type_node
            );
            $class->setEnumType($enum_type);

            $from_type = $case_count ? $case_union_type : NeverType::instance(false)->asRealUnionType();
            $value_parameter = new Parameter(
                $class_context,
                'value',
                $enum_type,
                0
            );
            // Note: The Method constructor will clone these parameters for us
            $from_parameters = [$value_parameter];
            $from_method = new Method(
                $class_context,
                'from',
                $from_type,
                ast\flags\MODIFIER_STATIC | ast\flags\MODIFIER_PUBLIC,
                FullyQualifiedMethodName::make($class->getFQSEN(), 'from'),
                $from_parameters
            );
            $from_method->setNumberOfRequiredParameters(1);
            $from_method->setRealParameterList($from_parameters);
            $from_method->setRealReturnType($from_type);
            $from_method->setPhanFlags(Flags::IS_PHP_INTERNAL | Flags::IS_SIDE_EFFECT_FREE);
            $class->addMethod($this->code_base, $from_method, None::instance());

            $try_from_type = $case_count ? $from_type->withIsNullable(true) : NullType::instance(false)->asRealUnionType();
            $try_from_method = new Method(
                $class_context,
                'tryFrom',
                $try_from_type->withIsNullable(true),
                ast\flags\MODIFIER_STATIC | ast\flags\MODIFIER_PUBLIC,
                FullyQualifiedMethodName::make($class->getFQSEN(), 'tryFrom'),
                $from_parameters
            );
            $try_from_method->setNumberOfRequiredParameters(1);
            $try_from_method->setRealParameterList($from_parameters);
            $try_from_method->setRealReturnType($try_from_type);
            $try_from_method->setPhanFlags(Flags::IS_PHP_INTERNAL | Flags::IS_SIDE_EFFECT_FREE);
            $class->addMethod($this->code_base, $try_from_method, None::instance());
        }
        $cases_type = ArrayShapeType::fromFieldTypes($case_field_types, false)->asRealUnionType();
        $cases_method = new Method(
            $class_context,
            'cases',
            $cases_type,
            ast\flags\MODIFIER_STATIC | ast\flags\MODIFIER_PUBLIC,
            FullyQualifiedMethodName::make($class->getFQSEN(), 'cases'),
            []
        );
        $cases_method->setRealReturnType($cases_type);
        $cases_method->setPhanFlags(Flags::IS_PHP_INTERNAL | Flags::IS_SIDE_EFFECT_FREE);
        $class->addMethod($this->code_base, $cases_method, None::instance());

        // @phan-suppress-next-line PhanThrowTypeAbsentForCall
        $base_enum_fqsen = FullyQualifiedClassName::fromFullyQualifiedString(
            $type_node ? '\BackedEnum' : '\UnitEnum'
        );
        $class->addInterfaceClassFQSEN($base_enum_fqsen, $node->lineno);
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
    public function visitUseTrait(Node $node): Context
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
    public function visitMethod(Node $node): Context
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
            clone $context,
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

        $this->emitOverrideAttributeCompatibility(
            $method->getAttributeList(),
            $node->lineno,
            $method->getRepresentationForIssue(),
            80300,
            '8.3+'
        );

        $method_name_lower = \strtolower($method_name);
        if ('__construct' === $method_name_lower) {
            $class->setIsParentConstructorCalled(false);

            // Handle constructor property promotion of __construct parameters
            foreach ($method->getParameterList() as $i => $parameter) {
                if ($parameter->getFlags() & Parameter::PARAM_MODIFIER_FLAGS) {
                    // @phan-suppress-next-line PhanTypeMismatchArgumentNullable kind is AST_PARAM
                    $this->addPromotedConstructorPropertyFromParam($class, $method, $parameter, $node->children['params']->children[$i]);
                }
            }
        } elseif ('__tostring' === $method_name_lower) {
            // Having a __toString method automatically adds the Stringable interface, #4476
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall should not happen, built in type
            $class->addAdditionalType(Type::fromFullyQualifiedString('\Stringable'));
        }


        // Create a new context with a new scope
        return $this->context->withScope($method->getInternalScope());
    }

    /**
     * Add an instance property from constructor property promotion
     * (`__construct(public int $param)`)
     *
     * This heavily duplicates parts of visitPropGroup
     */
    private function addPromotedConstructorPropertyFromParam(
        Clazz $class,
        Method $method,
        Parameter $parameter,
        Node $parameter_node
    ): void {
        $lineno = $parameter_node->lineno;
        $context = (clone $this->context)->withLineNumberStart($lineno);
        if ($parameter_node->flags & ast\flags\PARAM_VARIADIC) {
            $this->emitIssue(
                Issue::InvalidNode,
                $lineno,
                "Cannot declare variadic promoted property"
            );
            return;
        }
        // TODO: this should probably use FutureUnionType instead.
        $doc_comment = $parameter_node->children['docComment'] ?? '';
        $name = $parameter->getName();
        $method_comment = $method->getComment();
        $variable_comment = $method_comment ? ($method_comment->getParameterMap()[$name] ?? null) : null;
        $property_comment = Comment::fromStringInContext(
            $doc_comment,
            $this->code_base,
            $this->context,
            $lineno,
            Comment::ON_PROPERTY
        );
        $attributes = Attribute::fromNodeForAttributeList(
            $this->code_base,
            $this->context,
            $parameter_node->children['attributes']
        );

        $property_variable_comment = null;
        foreach ($property_comment->getVariableList() as $var_comment) {
            $var_name = $var_comment->getName();
            if ($var_name === '' || $var_name === '$' . $name || $var_name === $name) {
                $property_variable_comment = $var_comment;
                break;
            }
        }
        $parameter_union_type = $parameter->getUnionType();
        if ($property_variable_comment && !$property_variable_comment->getUnionType()->isEmpty()) {
            $variable_comment = $property_variable_comment;
            $parameter->setUnionType(
                $property_variable_comment->getUnionType()->withRealTypeSet($parameter_union_type->getRealTypeSet())
            );
        } elseif ($variable_comment && !$variable_comment->getUnionType()->isEmpty()) {
            $parameter->setUnionType(
                $variable_comment->getUnionType()->withRealTypeSet($parameter_union_type->getRealTypeSet())
            );
        }

        $property = $this->addProperty(
            $class,
            $parameter->getName(),
            $parameter_node->children['default'],
            $parameter->getUnionType()->getRealUnionType(),
            $variable_comment,
            $lineno,
            $parameter_node->flags & Parameter::PARAM_MODIFIER_FLAGS,
            $doc_comment,
            $property_comment,
            $attributes,
            true
        );
        if (!$property) {
            // Might be added via PHP doc comments, in which case we still want
            // to handle it
            $property_fqsen = FullyQualifiedPropertyName::make(
                $class->getFQSEN(),
                $parameter->getName()
            );
            if ($this->code_base->hasPropertyWithFQSEN($property_fqsen)) {
                $old_property = $this->code_base->getPropertyByFQSEN($property_fqsen);
                if ($old_property->getDefiningFQSEN() === $property_fqsen
                    && $old_property->isFromPHPDoc()
                ) {
                    $property = $old_property;
                }
            }
        }
        if (!$property) {
            return;
        }
        $property->setAttributeList($parameter->getAttributeList());
        // Ensure the IS_PROMOTED_PROPERTY flag is set even when merging with an existing @property PHPDoc
        $property->setPhanFlags($property->getPhanFlags() | Flags::IS_PROMOTED_PROPERTY);
        // Get a comment on the property declaration
        $property->setHasWriteReference(); // Assigned from within constructor
        $property->addReference($context); // Assigned from within constructor
        if ($class->isImmutable()) {
            if (!$property->isStatic() && !$property->isWriteOnly()) {
                $property->setIsReadOnly(true);
            }
        }
        $default_node = $parameter_node->children['default'];
        if ($default_node instanceof Node) {
            $parameter->setDefaultValueFutureType(new FutureUnionType(
                $this->code_base,
                new ElementContext($property),
                $default_node
            ));
        }
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
    public function visitPropGroup(Node $node): Context
    {
        // Bomb out if we're not in a class context
        ['attributes' => $attributes_node, 'props' => $props_node, 'type' => $type_node] = $node->children;
        if (!$props_node instanceof Node) {
            throw new AssertionError('Expected list of properties to be a node');
        }
        if ($type_node) {
            try {
                // Normalize to normalize php8 union types such as int|false|null to ?int|?false
                $real_union_type = (new UnionTypeVisitor($this->code_base, $this->context))->fromTypeInSignature($type_node)->asNormalizedTypes();
            } catch (IssueException $e) {
                Issue::maybeEmitInstance($this->code_base, $this->context, $e->getIssueInstance());
                $real_union_type = UnionType::empty();
            }
        } else {
            $real_union_type = UnionType::empty();
        }

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
        $attributes = Attribute::fromNodeForAttributeList(
            $this->code_base,
            $this->context,
            $attributes_node
        );

        foreach ($props_node->children as $i => $child_node) {
            // Ignore children which are not property elements
            if (!($child_node instanceof Node)
                || $child_node->kind !== \ast\AST_PROP_ELEM
            ) {
                continue;
            }
            $variable = $comment->getVariableList()[$i] ?? null;
            $default_node = $child_node->children['default'];
            $property_name = $child_node->children['name'];
            if (!\is_string($property_name)) {
                throw new AssertionError(
                    'Property name must be a string. '
                    . 'Got '
                    . \print_r($property_name, true)
                    . ' at '
                    . (clone $this->context)->withLineNumberStart($child_node->lineno)
                );
            }
            $hooks_node = $child_node->children['hooks'] ?? null;
            $property = $this->addProperty(
                $class,
                $property_name,
                $default_node,
                $real_union_type,
                $variable,
                $child_node->lineno,
                $node->flags,
                $doc_comment,
                $comment,
                $attributes,
                false
            );
            // Parse property hooks (PHP 8.4+)
            if ($hooks_node instanceof Node && $property) {
                $this->parsePropertyHooks($property, $hooks_node, $default_node);
            }
        }

        return $this->context;
    }

    /**
     * Emit compatibility warning if #[Override] is used but the target PHP version does not support it.
     *
     * @param list<Attribute> $attributes
     */
    private function emitOverrideAttributeCompatibility(array $attributes, int $lineno, string $element_name, int $minimum_version_id, string $version_label): void
    {
        if (Config::get_closest_target_php_version_id() >= $minimum_version_id) {
            return;
        }
        foreach ($attributes as $attribute) {
            if ($attribute->getFQSEN()->__toString() === '\\Override') {
                $this->emitIssue(
                    Issue::CompatibleOverrideAttribute,
                    $lineno,
                    $element_name,
                    $version_label
                );
                break;
            }
        }
    }

    /**
     * Parse property hooks (PHP 8.4+) and attach them to the property
     *
     * @param Property $property The property to attach hooks to
     * @param Node $hooks_node The AST_STMT_LIST containing property hooks
     * @param Node|string|float|int|null $default_node The default value node if present
     */
    private function parsePropertyHooks(Property $property, Node $hooks_node, Node|float|int|null|string $default_node): void
    {
        if ($hooks_node->kind !== \ast\AST_STMT_LIST) {
            return;
        }

        // Check for hooks with default value - not allowed in PHP
        if ($default_node !== null) {
            $this->emitIssue(
                Issue::PropertyHookWithDefaultValue,
                $property->getContext()->getLineNumberStart(),
                $property->asPropertyFQSENString()
            );
        }

        // Check for readonly property with set hook
        if ($property->isReadOnly()) {
            foreach ($hooks_node->children as $hook_node) {
                if ($hook_node instanceof Node
                    && $hook_node->kind === \ast\AST_PROPERTY_HOOK
                    && $hook_node->children['name'] === 'set'
                ) {
                    $this->emitIssue(
                        Issue::ReadonlyPropertyHasSetHook,
                        $hook_node->lineno,
                        $property->asPropertyFQSENString()
                    );
                }
            }
        }

        foreach ($hooks_node->children as $hook_node) {
            if (!($hook_node instanceof Node) || $hook_node->kind !== \ast\AST_PROPERTY_HOOK) {
                continue;
            }

            $hook_name = $hook_node->children['name'];
            if (!\is_string($hook_name) || !in_array($hook_name, ['get', 'set'], true)) {
                continue;
            }

            // Extract hook parameters (typically only 'set' has params)
            $params_node = $hook_node->children['params'];
            $parameter_list = [];
            if ($params_node instanceof Node) {
                $parameter_list = Parameter::listFromNode(
                    $this->context,
                    $this->code_base,
                    $params_node
                );
            }

            // Extract hook body (can be short-form `get => expr` or full `get { stmts }`)
            $stmts_node = $hook_node->children['stmts'];
            $body_node = $stmts_node instanceof Node ? $stmts_node : null;

            // Create PropertyHook object
            $hook = new PropertyHook(
                $hook_name,
                $property->getFQSEN(),
                $parameter_list,
                $body_node,
                $hook_node->flags,
                (clone $this->context)->withLineNumberStart($hook_node->lineno)
            );

            // Extract and set hook attributes
            // TODO: Implement attribute support for property hooks
            // $hook_attributes = Attribute::fromNodeForAttributeList(
            //     $this->code_base,
            //     $this->context,
            //     $hook_node->children['attributes'] ?? null
            // );

            // Attach hook to property
            if ($hook_name === 'get') {
                $property->setGetHook($hook);
            } else {
                $property->setSetHook($hook);

                // Validate set hook parameter type compatibility
                if (!empty($parameter_list)) {
                    $set_param = $parameter_list[0];
                    $set_param_type = $set_param->getNonVariadicUnionType();
                    $property_type = $property->getUnionType();

                    // Check if set parameter type is compatible with property type
                    // The parameter should accept the property's type (property type can cast to param type)
                    if (!$property_type->isEmpty() && !$set_param_type->isEmpty()) {
                        if (!$property_type->canCastToUnionType($set_param_type, $this->code_base)) {
                            $this->emitIssue(
                                Issue::PropertyHookIncompatibleParamType,
                                $hook_node->lineno,
                                $property->getFQSEN()->getFullyQualifiedClassName(),
                                'set',
                                '$' . $set_param->getName(),
                                $set_param_type,
                                $property_type
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Node|string|float|int|null $default_node
     * @param list<Attribute> $attributes
     */
    private function addProperty(Clazz $class, string $property_name, Node|float|int|null|string $default_node, UnionType $real_union_type, ?Comment\Parameter $variable, int $lineno, int $flags, ?string $doc_comment, Comment $property_comment, array $attributes, bool $from_parameter): ?Property
    {
        if ($class->getFlags() & ast\flags\CLASS_READONLY) {
            $flags |= ast\flags\MODIFIER_READONLY;
        }
        if ($flags & ast\flags\MODIFIER_READONLY) {
            if ($real_union_type->isEmpty()) {
                $this->emitIssue(
                    Issue::ReadonlyPropertyMissingType,
                    $lineno,
                    $property_name
                );
            }
        }
        $variable_has_literals = $variable && $variable->getUnionType()->hasLiterals();

        // If something goes wrong will getting the type of
        // a property, we'll store it as a future union
        // type and try to figure it out later
        $future_union_type_node = null;

        $context_for_property = (clone $this->context)->withLineNumberStart($lineno);
        $real_type_set = $real_union_type->getTypeSet();

        if ($default_node === null) {
            // This is a declaration such as `public $x;` with no $default_node
            // (we don't assume the property is always null, to reduce false positives)
            // We don't need to compare this to the real union type
            $union_type = $real_union_type;
            $default_type = NullType::instance(false)->asRealUnionType();
        } else {
            if ($default_node instanceof Node) {
                $this->checkNodeIsConstExprOrWarn(
                    $default_node,
                    $from_parameter ? self::CONSTANT_EXPRESSION_IN_PARAMETER : self::CONSTANT_EXPRESSION_IN_PROPERTY
                );
                $union_type = $this->resolveDefaultPropertyNode($default_node);
                if (!$union_type) {
                    // We'll type check this union type against the real union type when the future union type is resolved
                    $future_union_type_node = $default_node;
                    $union_type = UnionType::empty();
                }
            } else {
                // Get the type of the default (not a literal)
                // The literal value needs to be known to warn about incompatible composition of traits
                $union_type = Type::fromObject($default_node)->asPHPDocUnionType();
            }
            $default_type = $union_type;
            // Erase the corresponding real type set to avoid false positives such as `$x->prop['field'] === null` is redundant/impossible.
            $union_type = $union_type->asNonLiteralType()->eraseRealTypeSetRecursively();
            if ($real_union_type->isEmpty()) {
                if ($union_type->isType(NullType::instance(false))) {
                    $union_type = UnionType::empty();
                }
            } else {
                if (!$union_type->canCastToUnionType($real_union_type, $this->code_base)) {
                    $this->emitIssue(
                        Issue::TypeMismatchPropertyDefaultReal,
                        $lineno,
                        $real_union_type,
                        $property_name,
                        ASTReverter::toShortString($default_node),
                        $union_type
                    );
                    $union_type = $real_union_type;
                } else {
                    $original_union_type = $union_type;
                    foreach ($real_union_type->getTypeSet() as $type) {
                        if (!$type->asPHPDocUnionType()->isStrictSubtypeOf($this->code_base, $original_union_type)) {
                            $union_type = $union_type->withType($type);
                        }
                    }
                }
                $union_type = $union_type->withRealTypeSet($real_union_type->getTypeSet())->asNormalizedTypes();
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
                    return null;
                }
                $this->emitIssue(
                    Issue::RedefineProperty,
                    $lineno,
                    $property_name,
                    $this->context->getFile(),
                    $lineno,
                    $this->context->getFile(),
                    $old_property->getContext()->getLineNumberStart()
                );
                return null;
            }
        }

        $property = new Property(
            $context_for_property,
            $property_name,
            $union_type,
            $flags,
            $property_fqsen,
            $real_union_type
        );
        $property->setAttributeList($attributes);
        $this->emitOverrideAttributeCompatibility(
            $attributes,
            $lineno,
            $property->getRepresentationForIssue(),
            80500,
            '8.5+'
        );
        if ($variable) {
            $property->setPHPDocUnionType($variable->getUnionType());
        } else {
            $property->setPHPDocUnionType($real_union_type);
        }
        $property->setDefaultType($default_type);

        $phan_flags = $property_comment->getPhanFlagsForProperty();
        if ($flags & ast\flags\MODIFIER_READONLY) {
            $phan_flags |= Flags::IS_READ_ONLY;
        }
        if ($from_parameter) {
            $phan_flags |= Flags::IS_PROMOTED_PROPERTY;
        }
        // Check for #[Override] attribute (PHP 8.5+) in addition to @override PHPDoc
        if ($property->hasOverrideAttribute()) {
            $phan_flags |= Flags::IS_OVERRIDE_INTENDED;
        }
        $property->setPhanFlags($phan_flags);
        $property->setDocComment($doc_comment);

        // Add the property to the class
        $class->addProperty($this->code_base, $property, None::instance());

        $property->setSuppressIssueSet($property_comment->getSuppressIssueSet());

        if ($future_union_type_node instanceof Node) {
            $future_union_type = new FutureUnionType(
                $this->code_base,
                new ElementContext($property),
                //new ElementContext($property),
                $future_union_type_node
            );
        } else {
            $future_union_type = null;
        }
        // Look for any @var declarations
        if ($variable) {
            $original_union_type = $union_type;
            // We try to avoid resolving $future_union_type except when necessary,
            // to avoid issues such as https://github.com/phan/phan/issues/311 and many more.
            if ($future_union_type) {
                try {
                    $original_union_type = $future_union_type->get()->eraseRealTypeSetRecursively();
                    if (!$variable_has_literals) {
                        $original_union_type = $original_union_type->asNonLiteralType();
                    }
                    // We successfully resolved the union type. We no longer need $future_union_type
                    $future_union_type = null;
                } catch (IssueException) {
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

            // XXX during the parse phase, parent classes may be missing.
            if ($default_node !== null &&
                !$original_union_type->isType(NullType::instance(false)) &&
                !$variable->getUnionType()->canCastToUnionType($original_union_type, $this->code_base) &&
                !$original_union_type->canCastToUnionType($variable->getUnionType(), $this->code_base) &&
                !$property->checkHasSuppressIssueAndIncrementCount(Issue::TypeMismatchPropertyDefault)
            ) {
                $this->emitIssue(
                    Issue::TypeMismatchPropertyDefault,
                    $lineno,
                    (string)$variable->getUnionType(),
                    $property->getName(),
                    ASTReverter::toShortString($default_node),
                    (string)$original_union_type
                );
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
            if ($variable_type->hasGenericArray() && !$original_property_type->hasTypeMatchingCallback(static function (Type $type): bool {
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
        return $property;
    }

    /**
     * Resolve the union type of a property's default node.
     * This is being done to resolve the most common cases - e.g. `null`, `false`, and `true`
     */
    private function resolveDefaultPropertyNode(Node $node): ?UnionType
    {
        if ($node->kind === ast\AST_CONST) {
            try {
                return (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getConst()->getUnionType()->eraseRealTypeSetRecursively();
            } catch (IssueException) {
                // ignore
            }
        }
        return null;
    }

    /**
     * Visit a node with kind `\ast\AST_CLASS_CONST_GROUP`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     *
     */
    public function visitClassConstGroup(Node $node): Context
    {
        $class = $this->getContextClass();
        $attributes = Attribute::fromNodeForAttributeList(
            $this->code_base,
            $this->context,
            $node->children['attributes']
        );
        if ($node->flags & (ast\flags\MODIFIER_STATIC | ast\flags\MODIFIER_ABSTRACT)) {
            $this->emitIssue(
                Issue::InvalidNode,
                $node->lineno,
                "Invalid modifiers for class constant group"
            );
        }

        // Extract declared type for typed class constants (PHP 8.3+)
        $type_node = $node->children['type'] ?? null;
        if ($type_node) {
            try {
                // Parse the declared type and normalize union types
                $real_union_type = (new UnionTypeVisitor($this->code_base, $this->context))
                    ->fromTypeInSignature($type_node)
                    ->asNormalizedTypes();
            } catch (IssueException $e) {
                Issue::maybeEmitInstance($this->code_base, $this->context, $e->getIssueInstance());
                $real_union_type = UnionType::empty();
            }
        } else {
            $real_union_type = UnionType::empty();
        }

        foreach ($node->children['const']->children ?? [] as $child_node) {
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
            // Don't warn about typed constants in internal PHP classes - these work fine across PHP versions
            // even though PHP 8.4's reflection may show them as typed
            if (!$real_union_type->isEmpty() && Config::get_closest_target_php_version_id() < 80300 && !$class->isPHPInternal()) {
                $this->emitIssue(
                    Issue::CompatibleTypedClassConstant,
                    $child_node->lineno,
                    (string)$fqsen
                );
            }
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
                $child_node->lineno,
                Comment::ON_CONST
            );

            $line_number_start = $child_node->lineno;
            $flags = $node->flags;

            $constant = new ClassConstant(
                $this->context
                    ->withLineNumberStart($line_number_start)
                    ->withLineNumberEnd($child_node->endLineno ?? $line_number_start),
                $name,
                UnionType::empty(),
                $flags,
                $fqsen
            );
            $constant->setHasDeclaredType(!$real_union_type->isEmpty());

            $constant->setDocComment($doc_comment);
            $constant->setAttributeList($attributes);

            $this->handleClassConstantComment($constant, $comment);

            $value_node = $child_node->children['value'];

            // Infer type from value for PHPDoc union type (backward compatibility)
            if ($value_node instanceof Node) {
                if ($this->checkNodeIsConstExprOrWarn($value_node, self::CONSTANT_EXPRESSION_IN_CLASS_CONSTANT)) {
                    // TODO: Avoid using this when it only contains literals (nothing depending on the CodeBase),
                    $future_type = new FutureUnionType(
                        $this->code_base,
                        new ElementContext($constant),
                        $value_node
                    );
                    $constant->setFutureUnionType($future_type);
                    // If there's a declared type, we'll set the real type after FutureUnionType resolves
                    // For now, store the real_union_type in a way that can be used later
                    if (!$real_union_type->isEmpty()) {
                        // Set an initial union type with the real type set
                        $constant->setUnionType(UnionType::empty()->withRealTypeSet($real_union_type->getTypeSet()));
                    }
                } else {
                    if (!$real_union_type->isEmpty()) {
                        $constant->setUnionType(MixedType::instance(false)->asPHPDocUnionType()->withRealTypeSet($real_union_type->getTypeSet()));
                    } else {
                        $constant->setUnionType(MixedType::instance(false)->asPHPDocUnionType());
                    }
                }
            } else {
                // This is a literal scalar value.
                // Assume that this is the only definition of the class constant and that it's not a stub for something that depends on configuration.
                //
                // TODO: What about internal stubs (isPHPInternal()) - if Phan would treat those like being from phpdoc,
                // it should do the same for FutureUnionType
                if ($real_union_type->isEmpty()) {
                    // No declared type - infer from value as real type (preserves literal types for narrowing)
                    $constant->setUnionType(Type::fromObject($value_node)->asRealUnionType());
                } else {
                    // Has declared type - use it as real type, value type as PHPDoc type
                    $inferred_type = Type::fromObject($value_node)->asPHPDocUnionType();
                    $constant->setUnionType($inferred_type->withRealTypeSet($real_union_type->getTypeSet()));
                }
            }
            $constant->setNodeForValue($value_node);

            $class->addConstant($this->code_base, $constant);
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_ENUM_CASE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitEnumCase(Node $node): Context
    {
        $class = $this->getContextClass();
        $attributes = Attribute::fromNodeForAttributeList(
            $this->code_base,
            $this->context,
            $node->children['attributes']
        );

        $name = $node->children['name'];
        if (!\is_string($name)) {
            throw new AssertionError('expected enum case name to be a string');
        }
        $fqsen = FullyQualifiedClassConstantName::make($class->getFQSEN(), $name);
        $lineno = $node->lineno;

        if (!$class->isEnum()) {
            $this->emitIssue(Issue::InvalidNode, $lineno, 'Cannot declare an enum case statement in a non-enum');
            return $this->context;
        }

        if ($this->code_base->hasClassConstantWithFQSEN($fqsen)) {
            $old_constant = $this->code_base->getClassConstantByFQSEN($fqsen);
            if ($old_constant->getDefiningFQSEN() === $fqsen) {
                $this->emitIssue(
                    Issue::RedefineClassConstant,
                    $lineno,
                    $name,
                    $this->context->getFile(),
                    $lineno,
                    $this->context->getFile(),
                    $old_constant->getContext()->getLineNumberStart()
                );
                return $this->context;
            }
        }

        // Get a comment on the declaration
        $doc_comment = $node->children['docComment'] ?? '';
        $comment = Comment::fromStringInContext(
            $doc_comment,
            $this->code_base,
            $this->context,
            $lineno,
            Comment::ON_CONST
        );

        $constant = new EnumCase(
            $this->context
                ->withLineNumberStart($lineno)
                ->withLineNumberEnd($node->endLineno ?? $lineno),
            $name,
            UnionType::empty(),
            $node->flags,
            $fqsen
        );

        $constant->setDocComment($doc_comment);
        $constant->setAttributeList($attributes);

        $this->handleClassConstantComment($constant, $comment);

        $value_node = $node->children['expr'];
        if ($value_node instanceof Node) {
            // NOTE: In php itself, the same types of operations are allowed as other constant expressions (i.e. isConstExpr is the correct check).
            //
            // However, const expressions for enum cases are evaluated when compiling an enum,
            // including looking up global constants and class constants,
            // and if that can't be evaluated then it's a fatal compile error.
            $this->checkNodeIsConstExprOrWarn($value_node, self::CONSTANT_EXPRESSION_IN_CLASS_CONSTANT);
        }
        $constant->setUnionType($class->getFQSEN()->asType()->asRealUnionType());
        $constant->setNodeForValue($value_node);
        $constant->setHasDeclaredType(true);

        $class->addEnumCase($this->code_base, $constant);

        foreach ($comment->getVariableList() as $var) {
            if ($var->getUnionType()->hasTemplateTypeRecursive()) {
                $this->emitIssue(
                    Issue::TemplateTypeConstant,
                    $constant->getFileRef()->getLineNumberStart(),
                    (string)$constant->getFQSEN()
                );
                break;
            }
        }

        return $this->context;
    }

    private function handleClassConstantComment(ClassConstant $constant, Comment $comment): void
    {
        $constant->setIsDeprecated($comment->isDeprecated());
        // Check for #[Deprecated] attribute (PHP 8.4+)
        if ($constant->hasDeprecatedAttribute()) {
            $constant->setIsDeprecated(true);
        }
        $constant->setIsNSInternal($comment->isNSInternal());
        $constant->setIsOverrideIntended($comment->isOverrideIntended());
        $constant->setIsPHPDocAbstract($comment->isPHPDocAbstract());
        $constant->setSuppressIssueSet($comment->getSuppressIssueSet());
        $constant->setComment($comment);
        foreach ($comment->getVariableList() as $var) {
            if ($var->getUnionType()->hasTemplateTypeRecursive()) {
                $this->emitIssue(
                    Issue::TemplateTypeConstant,
                    $constant->getFileRef()->getLineNumberStart(),
                    (string)$constant->getFQSEN()
                );
                break;
            }
        }
    }

    /**
     * Visit a node with kind `\ast\AST_STATIC` (a static variable)
     */
    public function visitStatic(Node $node): Context
    {
        $default = $node->children['default'];
        if ($default instanceof Node) {
            $this->checkNodeIsConstExprOrWarn($default, self::CONSTANT_EXPRESSION_IN_STATIC_VARIABLE);
        }
        $context = $this->context;
        // Make sure we're actually returning from a method.
        if ($context->isInFunctionLikeScope()) {
            // Get the method/function/closure we're in
            $method = $context->getFunctionLikeInScope($this->code_base);

            // Mark the method as having a static variable
            $method->setHasStaticVariable(true);
        }

        return $context;
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
    public function visitConstDecl(Node $node): Context
    {
        foreach ($node->children as $child_node) {
            if (!$child_node instanceof Node) {
                throw new AssertionError("Expected global constant element to be a Node");
            }
            if ($child_node->kind === ast\AST_ATTRIBUTE_LIST) {
                // Skip attribute lists appended to AST_CONST_DECL
                continue;
            }

            $value_node = $child_node->children['value'];
            if ($value_node instanceof Node && !$this->checkNodeIsConstExprOrWarn($value_node, self::CONSTANT_EXPRESSION_IN_CONSTANT)) {
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
    public function visitFuncDecl(Node $node): Context
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
                // Notify plugins about the stub-loaded function (e.g., for CallableParamPlugin)
                $code_base->notifyPluginsOnInternalFunctionLoad($func_variant);
            }
        } else {
            $code_base->addFunction($func);
        }

        // Track function declaration for incremental analysis
        DependencyTracker::track($function_fqsen->__toString(), 'declares');

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
    public function visitClosure(Node $node): Context
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
    public function visitArrowFunc(Node $node): Context
    {
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
    public function visitCall(Node $node): Context
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
        return $this->context;
    }

    private function analyzeDefine(Node $node): void
    {
        $args = $node->children['args']->children ?? [];
        if (\count($args) < 2) {
            // Ignore first-class callables and calls with too few arguments.
            return;
        }
        $name = $args[0];
        if ($name instanceof Node) {
            try {
                $name_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $name, false);
            } catch (IssueException) {
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
            $args[1],
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
    public function visitStaticCall(Node $node): Context
    {
        $call = $node->children['class'];

        if ($call instanceof Node && $call->kind === \ast\AST_NAME) {
            $func_name = \strtolower($call->children['name']);
            if ($func_name === 'parent') {
                // Make sure it is not a crazy dynamic parent method call
                if (!($node->children['method'] instanceof Node)) {
                    $meth = \strtolower($node->children['method']);

                    if ($meth === '__construct' && $this->context->isInClassScope()) {
                        $class = $this->getContextClass();
                        $class->setIsParentConstructorCalled(true);
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
     *
     * TODO: Defer analysis of the inside of methods until the class gets hydrated.
     */
    public function visitReturn(Node $node): Context
    {
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
    public function visitYield(Node $node): Context
    {
        return $this->analyzeYield();
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
    public function visitYieldFrom(Node $node): Context
    {
        return $this->analyzeYield();
    }


    /**
     * Visit a node with kind `\ast\AST_YIELD_FROM` or kind `\ast_YIELD`
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    private function analyzeYield(): Context
    {
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
        mixed $value,
        int $flags,
        string $comment_string,
        bool $use_future_union_type,
        bool $is_fully_qualified = false
    ): void {
        $i = \strrpos($name, '\\');
        if ($i !== false) {
            $name_fragment = \substr($name, $i + 1);
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
        } catch (InvalidArgumentException | FQSENException) {
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
            // NOTE: With enums, global constants can be assigned to enums,
            // so this can be any valid type.
            UnionType::fromFullyQualifiedRealString('mixed'),
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
                // TODO: Avoid using this when it only contains literals (nothing depending on the CodeBase),
                // e.g. `['key' => 'value']`
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

        // Track constant declaration for incremental analysis
        DependencyTracker::track($fqsen->__toString(), 'declares');
    }

    /**
     * @return Clazz
     * Get the class on this scope or fail real hard
     */
    private function getContextClass(): Clazz
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
    private function recordClassAlias(Node $node): void
    {
        $args = $node->children['args']->children ?? [];
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
    public function visitNamespace(Node $node): Context
    {
        $context = $this->context;
        // @phan-suppress-next-line PhanAccessMethodInternal addParsedNamespaceMap and getNamespaceMap
        $this->code_base->addParsedNamespaceMap($context->getFile(), $context->getNamespace(), $context->getNamespaceId(), $context->getNamespaceMap());
        return parent::visitNamespace($node);
    }

    // common no-ops
    public function visitArrayElem(Node $node): Context
    {
        return $this->context;
    }
    public function visitVar(Node $node): Context
    {
        return $this->context;
    }
    public function visitName(Node $node): Context
    {
        return $this->context;
    }
    public function visitCallableConvert(Node $node): Context
    {
        return $this->context;
    }
    public function visitArgList(Node $node): Context
    {
        return $this->context;
    }
    public function visitStmtList(Node $node): Context
    {
        foreach ($node->children as $c) {
            if (\is_string($c) && str_contains($c, '@phan-type')) {
                $this->analyzePhanTypeAliasStatement($c);
            }
        }
        return $this->context;
    }

    private function analyzePhanTypeAliasStatement(string $text): void
    {
        // @phan-suppress-next-line PhanAccessClassConstantInternal
        if (\preg_match_all(Builder::PHAN_TYPE_ALIAS_REGEX, $text, $matches, \PREG_SET_ORDER) > 0) {
            foreach ($matches as $group) {
                $alias_name = $group[1];
                $union_type_string = $group[2];
                // @phan-suppress-next-line PhanAccessMethodInternal
                Builder::addTypeAliasMapping($this->code_base, $this->context, $alias_name, $union_type_string);
            }
        }
    }

    public function visitNullsafeProp(Node $node): Context
    {
        return $this->context;
    }
    public function visitProp(Node $node): Context
    {
        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_PROPERTY_HOOK`
     * Property hooks are parsed in parsePropertyHooks(), so we don't need to do anything here
     */
    public function visitPropertyHook(Node $node): Context
    {
        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_PROPERTY_HOOK_SHORT_BODY`
     * Property hooks are parsed in parsePropertyHooks(), so we don't need to do anything here
     */
    public function visitPropertyHookShortBody(Node $node): Context
    {
        return $this->context;
    }

    public function visitArray(Node $node): Context
    {
        return $this->context;
    }
    public function visitBinaryOp(Node $node): Context
    {
        return $this->context;
    }

    /**
     * @internal
     */
    public const ALLOWED_CONST_EXPRESSION_KINDS = [
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
        ast\AST_NULLSAFE_PROP => true,
        ast\AST_PROP => true,
        ast\AST_UNARY_OP => true,
        ast\AST_UNPACK => true,
    ];

    /**
     * @internal
     */
    public const ALLOWED_CONST_EXPRESSION_KINDS_WITH_NEW = [
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
        ast\AST_NULLSAFE_PROP => true,
        ast\AST_PROP => true,
        ast\AST_UNARY_OP => true,
        ast\AST_UNPACK => true,

        ast\AST_NEW => true,
        ast\AST_ARG_LIST => true,
        ast\AST_NAMED_ARG => true,
    ];

    public const CONSTANT_EXPRESSION_IN_ATTRIBUTE = 1;
    public const CONSTANT_EXPRESSION_IN_PARAMETER = self::CONSTANT_EXPRESSION_IN_ATTRIBUTE;
    public const CONSTANT_EXPRESSION_IN_CONSTANT = self::CONSTANT_EXPRESSION_IN_ATTRIBUTE;
    public const CONSTANT_EXPRESSION_IN_STATIC_VARIABLE = self::CONSTANT_EXPRESSION_IN_ATTRIBUTE;

    public const CONSTANT_EXPRESSION_IN_CLASS_CONSTANT = 2;
    public const CONSTANT_EXPRESSION_IN_PROPERTY = self::CONSTANT_EXPRESSION_IN_CLASS_CONSTANT;
    public const CONSTANT_EXPRESSION_FORBID_NEW_EXPRESSION = self::CONSTANT_EXPRESSION_IN_CLASS_CONSTANT;

    /**
     * If the expression $node contains invalid AST kinds for a constant expression, then this warns.
     *
     * @param 1|2 $const_expr_context determines what ast node kinds can be used in a constant expression
     */
    public function checkNodeIsConstExprOrWarn(Node $node, int $const_expr_context): bool
    {
        try {
            self::checkIsAllowedInConstExpr($node, $const_expr_context);
            // After validating the basic structure, check for enum property access (PHP 8.2+)
            $this->checkEnumPropertyAccessInConstExpr($node);
            return true;
        } catch (InvalidArgumentException $e) {
            $this->emitIssue(
                Issue::InvalidConstantExpression,
                $node->lineno,
                $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Check for enum property access in constant expressions (PHP 8.2+ feature).
     * Emits compatibility warning if targeting PHP < 8.2.
     * Emits error if the left-hand side is not an enum.
     *
     * @param Node|string|float|int|bool|null $node
     */
    private function checkEnumPropertyAccessInConstExpr(Node|bool|float|int|null|string $node): void
    {
        if (!($node instanceof Node)) {
            return;
        }

        // Check if this is an enum property access node
        if ($node->kind === ast\AST_PROP || $node->kind === ast\AST_NULLSAFE_PROP) {
            // Emit compatibility warning for PHP < 8.2
            if (Config::get_closest_target_php_version_id() < 80200) {
                $this->emitIssue(
                    Issue::CompatibleEnumPropertyInConstExpression,
                    $node->lineno,
                    ASTReverter::toShortString($node)
                );
            }

            // Check if the left-hand side is an enum
            $expr_node = $node->children['expr'];
            if ($expr_node instanceof Node) {
                try {
                    $expr_type = UnionTypeVisitor::unionTypeFromNode(
                        $this->code_base,
                        $this->context,
                        $expr_node,
                        false
                    );

                    // For nullsafe property access, null is allowed
                    if ($node->kind === ast\AST_NULLSAFE_PROP) {
                        $expr_type = $expr_type->nonNullableClone();
                    }

                    // Check if all types are enums
                    $has_non_enum = false;
                    foreach ($expr_type->getTypeSet() as $type) {
                        // Skip checking for null in nullsafe access
                        if ($type instanceof NullType) {
                            continue;
                        }

                        // Check if this type corresponds to an enum class
                        if ($type->isObjectWithKnownFQSEN()) {
                            $fqsen = FullyQualifiedClassName::fromType($type);
                            if ($this->code_base->hasClassWithFQSEN($fqsen)) {
                                $class = $this->code_base->getClassByFQSEN($fqsen);
                                if (!$class->isEnum()) {
                                    $has_non_enum = true;
                                    break;
                                }
                            } else {
                                // Unknown class - might be enum, don't warn
                                continue;
                            }
                        } else {
                            // Not a class type (e.g., mixed, object, etc.)
                            $has_non_enum = true;
                            break;
                        }
                    }

                    if ($has_non_enum) {
                        $this->emitIssue(
                            Issue::NonEnumPropertyInConstExpression,
                            $node->lineno,
                            $expr_type
                        );
                    }
                } catch (IssueException) {
                    // If we can't determine the type, don't emit a warning
                    // The issue will be caught during analysis phase
                }
            }
        }

        // Recursively check child nodes
        foreach ($node->children as $child_node) {
            $this->checkEnumPropertyAccessInConstExpr($child_node);
        }
    }

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
    public static function checkIsAllowedInConstExpr(Node|bool|float|int|null|string $n, int $const_expr_context): void
    {
        if (!($n instanceof Node)) {
            return;
        }
        if (
            !\array_key_exists($n->kind, self::ALLOWED_CONST_EXPRESSION_KINDS) &&
            !(
                $const_expr_context === self::CONSTANT_EXPRESSION_IN_STATIC_VARIABLE &&
                \array_key_exists($n->kind, self::ALLOWED_CONST_EXPRESSION_KINDS_WITH_NEW)
            )
        ) {
            throw new InvalidArgumentException(ASTReverter::toShortString($n));
        }
        foreach ($n->children as $child_node) {
            self::checkIsAllowedInConstExpr($child_node, $const_expr_context);
        }
    }

    /**
     * @param Node|string|float|int|bool|null $n
     * @param 1|2 $const_expr_context
     * @param string &$error_message $error_message @phan-output-reference
     * @return bool - If true, then $n is a valid constant AST.
     */
    public static function isConstExpr(Node|bool|float|int|null|string $n, int $const_expr_context, string &$error_message = ''): bool
    {
        try {
            self::checkIsAllowedInConstExpr($n, $const_expr_context);
            return true;
        } catch (InvalidArgumentException $e) {
            $error_message = $e->getMessage();
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
        ast\AST_NULLSAFE_PROP => true,
        ast\AST_PROP => true,
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
    private static function checkIsNonVariableExpression(Node|bool|float|int|null|string $n): void
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
    public static function isNonVariableExpr(Node|bool|float|int|null|string $n): bool
    {
        try {
            self::checkIsNonVariableExpression($n);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
