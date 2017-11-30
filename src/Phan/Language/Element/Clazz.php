<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Analysis\AbstractMethodAnalyzer;
use Phan\Analysis\CompositionAnalyzer;
use Phan\Analysis\DuplicateClassAnalyzer;
use Phan\Analysis\ClassInheritanceAnalyzer;
use Phan\Analysis\ParentConstructorCalledAnalyzer;
use Phan\Analysis\PropertyTypesAnalyzer;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope\ClassScope;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Type;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;
use Phan\Library\None;
use Phan\Library\Option;
use Phan\Library\Some;
use Phan\Plugin\ConfigPluginSet;

class Clazz extends AddressableElement
{
    use \Phan\Memoize;
    use ClosedScopeElement;

    /**
     * @var Type|null
     * The type of the parent of this class if it extends
     * anything, else null.
     */
    private $parent_type = null;

    /**
     * @var FullyQualifiedClassName[]
     * A possibly empty list of interfaces implemented
     * by this class
     */
    private $interface_fqsen_list = [];

    /**
     * @var FullyQualifiedClassName[]
     * A possibly empty list of traits used by this class
     */
    private $trait_fqsen_list = [];

    /**
     * @var TraitAdaptations[]
     * Maps lowercase fqsen of a method to the trait names which are hidden
     * and the trait aliasing info
     */
    private $trait_adaptations_map = [];

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param string $name
     * The name of the typed structural element
     *
     * @param UnionType $type
     * A '|' delimited set of types satisfied by this
     * typed structural element.
     *
     * @param int $flags
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     *
     * @param FullyQualifiedClassName $fqsen
     * A fully qualified name for this class
     *
     * @param Type|null $parent_type
     * @param FullyQualifiedClassName[] $interface_fqsen_list
     * @param FullyQualifiedClassName[] $trait_fqsen_list
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FullyQualifiedClassName $fqsen,
        Type $parent_type = null,
        array $interface_fqsen_list = [],
        array $trait_fqsen_list = []
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags,
            $fqsen
        );

        $this->parent_type = $parent_type;
        $this->interface_fqsen_list = $interface_fqsen_list;
        $this->trait_fqsen_list = $trait_fqsen_list;

        $this->setInternalScope(new ClassScope(
            $context->getScope(),
            $fqsen
        ));
    }

    /**
     * @param CodeBase $code_base
     * A reference to the entire code base in which this
     * context exists
     *
     * @param string $class_name
     * The name of a builtin class to build a new Class structural
     * element from.
     *
     * @return Clazz
     * A Class structural element representing the given named
     * builtin.
     */
    public static function fromClassName(
        CodeBase $code_base,
        string $class_name
    ) : Clazz {
        return self::fromReflectionClass(
            $code_base,
            new \ReflectionClass($class_name)
        );
    }

    /**
     * @param CodeBase $code_base
     * A reference to the entire code base in which this
     * context exists
     *
     * @param \ReflectionClass $class
     * A reflection class representing a builtin class.
     *
     * @return Clazz
     * A Class structural element representing the given named
     * builtin.
     */
    public static function fromReflectionClass(
        CodeBase $code_base,
        \ReflectionClass $class
    ) : Clazz {
        // Build a set of flags based on the constitution
        // of the built-in class
        $flags = 0;
        if ($class->isFinal()) {
            $flags = \ast\flags\CLASS_FINAL;
        } elseif ($class->isInterface()) {
            $flags = \ast\flags\CLASS_INTERFACE;
        } elseif ($class->isTrait()) {
            $flags = \ast\flags\CLASS_TRAIT;
        }
        if ($class->isAbstract()) {
            $flags |= \ast\flags\CLASS_ABSTRACT;
        }

        $context = new Context;

        $class_name = $class->getName();
        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($class_name);

        // Build a base class element
        $clazz = new Clazz(
            $context,
            $class_name,
            UnionType::fromFullyQualifiedString('\\' . $class_name),
            $flags,
            $class_fqsen
        );

        // If this class has a parent class, add it to the
        // class info
        if (($parent_class = $class->getParentClass())) {
            $parent_class_fqsen =
                FullyQualifiedClassName::fromFullyQualifiedString(
                    '\\' . $parent_class->getName()
                );

            $parent_type = $parent_class_fqsen->asType();

            $clazz->setParentType($parent_type);
        }

        if ($class_name === "Traversable") {
            // Make sure that canCastToExpandedUnionType() works as expected for Traversable and its subclasses
            $clazz->getUnionType()->addType(IterableType::instance(false));
        }

        // Note: If there are multiple calls to Clazz->addProperty(),
        // the UnionType from the first one will be used, subsequent calls to addProperty()
        // will have no effect.
        // As a result, we set the types from phan's documented internal property types first,
        // preferring them over the default values (which may be null, etc.).
        foreach (UnionType::internalPropertyMapForClassName(
            $clazz->getName()
        ) as $property_name => $property_type_string) {
            // An asterisk indicates that the class supports
            // dynamic properties
            if ($property_name === '*') {
                $clazz->setHasDynamicProperties(true);
                continue;
            }

            $property_context = $context->withScope(
                new ClassScope(new GlobalScope, $clazz->getFQSEN())
            );

            $property_type =
                UnionType::fromStringInContext(
                    $property_type_string,
                    new Context,
                    Type::FROM_TYPE
                );

            $property_fqsen = FullyQualifiedPropertyName::make(
                $clazz->getFQSEN(),
                $property_name
            );

            $property = new Property(
                $property_context,
                $property_name,
                $property_type,
                0,
                $property_fqsen
            );

            $clazz->addProperty($code_base, $property, new None);
        }

        // n.b.: public properties on internal classes don't get
        //       listed via reflection until they're set unless
        //       they have a default value. Therefore, we don't
        //       bother iterating over `$class->getProperties()`
        //       `$class->getStaticProperties()`.

        foreach ($class->getDefaultProperties() as $name => $value) {
            $property_context = $context->withScope(
                new ClassScope(new GlobalScope, $clazz->getFQSEN())
            );

            $property_fqsen = FullyQualifiedPropertyName::make(
                $clazz->getFQSEN(),
                $name
            );

            $property = new Property(
                $property_context,
                $name,
                Type::fromObject($value)->asUnionType(),
                0,
                $property_fqsen
            );

            $clazz->addProperty($code_base, $property, new None);
        }

        foreach ($class->getInterfaceNames() as $name) {
            $clazz->addInterfaceClassFQSEN(
                FullyQualifiedClassName::fromFullyQualifiedString(
                    '\\' . $name
                )
            );
        }

        foreach ($class->getTraitNames() as $name) {
            // TODO: optionally, support getTraitAliases()? This is low importance for internal PHP modules,
            // it would be uncommon to see traits in internal PHP modules.
            $clazz->addTraitFQSEN(
                FullyQualifiedClassName::fromFullyQualifiedString(
                    '\\' . $name
                )
            );
        }

        foreach ($class->getConstants() as $name => $value) {
            $constant_fqsen = FullyQualifiedClassConstantName::make(
                $clazz->getFQSEN(),
                $name
            );

            $constant = new ClassConstant(
                $context,
                $name,
                Type::fromObject($value)->asUnionType(),
                0,
                $constant_fqsen
            );
            $constant->setNodeForValue($value);

            $clazz->addConstant($code_base, $constant);
        }

        foreach ($class->getMethods() as $reflection_method) {
            $method_context = $context->withScope(
                new ClassScope(new GlobalScope, $clazz->getFQSEN())
            );

            $method_list =
                FunctionFactory::methodListFromReflectionClassAndMethod(
                    $method_context,
                    $code_base,
                    $class,
                    $reflection_method
                );

            foreach ($method_list as $method) {
                $clazz->addMethod($code_base, $method, new None);
            }
        }

        return $clazz;
    }

    /**
     * @param Type|null $parent_type
     * The type of the parent (extended) class of this class.
     *
     * @return void
     */
    public function setParentType(Type $parent_type = null)
    {
        if ($this->getInternalScope()->hasAnyTemplateType()) {
            // Get a reference to the local list of templated
            // types. We'll use this to map templated types on the
            // parent to locally templated types.
            $template_type_map =
                $this->getInternalScope()->getTemplateTypeMap();

            // Figure out if the given parent type contains any template
            // types.
            $contains_templated_type = false;
            foreach ($parent_type->getTemplateParameterTypeList() as $union_type) {
                foreach ($union_type->getTypeSet() as $type) {
                    if (isset($template_type_map[$type->getName()])) {
                        $contains_templated_type = true;
                        break 2;
                    }
                }
            }

            // If necessary, map the template parameter type list through the
            // local list of templated types.
            if ($contains_templated_type) {
                $parent_type = Type::fromType(
                    $parent_type,
                    \array_map(function (UnionType $union_type) use ($template_type_map) : UnionType {
                        return new UnionType(
                            \array_map(function (Type $type) use ($template_type_map) : Type {
                                return $template_type_map[$type->getName()] ?? $type;
                            }, $union_type->getTypeSet())
                        );
                    }, $parent_type->getTemplateParameterTypeList())
                );
            }
        }

        $this->parent_type = $parent_type;

        // Add the parent to the union type of this class
        $this->getUnionType()->addUnionType(
            $parent_type->asUnionType()
        );
    }

    /**
     * @return bool
     * True if this class has a parent class
     */
    public function hasParentType() : bool
    {
        return !empty($this->parent_type);
    }

    /**
     * @return Option<Type>
     * If a parent type is defined, get Some<Type>, else None.
     */
    public function getParentTypeOption()
    {
        if ($this->hasParentType()) {
            return new Some($this->parent_type);
        }

        return new None;
    }

    /**
     * @return FullyQualifiedClassName
     * The parent class of this class if one exists
     *
     * @throws \Exception
     * An exception is thrown if this class has no parent
     */
    public function getParentClassFQSEN() : FullyQualifiedClassName
    {
        $parent_type_option = $this->getParentTypeOption();

        if (!$parent_type_option->isDefined()) {
            throw new \Exception("Class $this has no parent");
        }

        return $parent_type_option->get()->asFQSEN();
    }

    /**
     * @return Clazz
     * The parent class of this class if defined
     *
     * @throws \Exception
     * An exception is thrown if this class has no parent
     */
    public function getParentClass(CodeBase $code_base) : Clazz
    {
        $parent_type_option = $this->getParentTypeOption();

        if (!$parent_type_option->isDefined()) {
            throw new \Exception("Class $this has no parent");
        }

        $parent_fqsen = $parent_type_option->get()->asFQSEN();
        \assert($parent_fqsen instanceof FullyQualifiedClassName);

        // invoking hasClassWithFQSEN also has the side effect of lazily loading the parent class definition.
        if (!$code_base->hasClassWithFQSEN($parent_fqsen)) {
            throw new \Exception("Failed to load parent Class $parent_fqsen of Class $this");
        }

        return $code_base->getClassByFQSEN(
            $parent_fqsen
        );
    }

    public function isSubclassOf(CodeBase $code_base, Clazz $other) : bool
    {
        if (!$this->hasParentType()) {
            return false;
        }

        if (!$code_base->hasClassWithFQSEN(
            $this->getParentClassFQSEN()
        )) {
            // Let this emit an issue elsewhere for the
            // parent not existing
            return false;
        }

        // Get the parent class
        $parent = $this->getParentClass($code_base);

        if ($parent === $other) {
            return true;
        }

        return $parent->isSubclassOf($code_base, $other);
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return int
     * This class's depth in the class hierarchy
     */
    public function getHierarchyDepth(CodeBase $code_base) : int
    {
        if (!$this->hasParentType()) {
            return 0;
        }

        if (!$code_base->hasClassWithFQSEN(
            $this->getParentClassFQSEN()
        )) {
            // Let this emit an issue elsewhere for the
            // parent not existing
            return 0;
        }

        // Get the parent class
        $parent = $this->getParentClass($code_base);

        // Prevent infinite loops
        if ($parent == $this) {
            return 0;
        }

        return (1 + $parent->getHierarchyDepth($code_base));
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return FullyQualifiedClassName
     * The FQSEN of the root class on this class's hierarchy
     */
    public function getHierarchyRootFQSEN(
        CodeBase $code_base
    ) : FullyQualifiedClassName {
        if (!$this->hasParentType()) {
            return $this->getFQSEN();
        }

        if (!$code_base->hasClassWithFQSEN(
            $this->getParentClassFQSEN()
        )) {
            // Let this emit an issue elsewhere for the
            // parent not existing
            return $this->getFQSEN();
        }

        // Get the parent class
        $parent = $this->getParentClass($code_base);

        // Prevent infinite loops
        if ($parent == $this) {
            return $this->getFQSEN();
        }

        return $parent->getHierarchyRootFQSEN($code_base);
    }

    /**
     * @param FQSEN $fqsen
     * Add the given FQSEN to the list of implemented
     * interfaces for this class
     *
     * @return void
     */
    public function addInterfaceClassFQSEN(FQSEN $fqsen)
    {
        $this->interface_fqsen_list[] = $fqsen;

        // Add the interface to the union type of this
        // class
        $this->getUnionType()->addUnionType(
            UnionType::fromFullyQualifiedString((string)$fqsen)
        );
    }

    /**
     * @return FullyQualifiedClassName[]
     * Get the list of interfaces implemented by this class
     */
    public function getInterfaceFQSENList() : array
    {
        return $this->interface_fqsen_list;
    }

    /**
     * Add a property to this class
     *
     * @param CodeBase $code_base
     * A reference to the code base in which the ancestor exists
     *
     * @param Property $property
     * The property to copy onto this class
     *
     * @param Option<Type>|None $type_option
     * A possibly defined type used to define template
     * parameter types when importing the property
     *
     * @return void
     */
    public function addProperty(
        CodeBase $code_base,
        Property $property,
        $type_option
    ) {
        // Ignore properties we already have
        // TODO: warn about private properties in subclass overriding ancestor private property.
        $property_name = $property->getName();
        if ($this->hasPropertyWithName($code_base, $property_name)) {
            // original_property is the one that the class is using.
            // We added $property after that (so it likely in a base class, or a trait's property added after this property was added)
            // $overriding_property = $this->getPropertyMap($code_base)[$property_name];;
            // TODO: implement https://github.com/phan/phan/issues/615 in another PR, see below comment
            /**
            if ($overriding_property->isStatic() != $property->isStatic()) {
                if ($overriding_property->isStatic()) {
                    // emit warning about redefining non-static as static $overriding_property
                } else {
                    // emit warning about redefining static as
                }
            }
             */
            return;
        }

        $property_fqsen = FullyQualifiedPropertyName::make(
            $this->getFQSEN(),
            $property_name
        );

        // TODO: defer template properties until the analysis phase? They might not be parsed or resolved yet.
        if ($property->getFQSEN() !== $property_fqsen) {
            $property = clone($property);
            $property->setFQSEN($property_fqsen);

            try {
                // If we have a parent type defined, map the property's
                // type through it
                if ($type_option->isDefined()
                    && $property->getUnionType()->hasTemplateType()
                ) {
                    $property->setUnionType(
                        $property->getUnionType()->withTemplateParameterTypeMap(
                            $type_option->get()->getTemplateParameterTypeMap(
                                $code_base
                            )
                        )
                    );
                }
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $code_base,
                    $property->getContext(),
                    $exception->getIssueInstance()
                );
            }
        }

        $code_base->addProperty($property);
    }

    /**
     * @param \Phan\Language\Element\Comment\Parameter[] $magic_property_map mapping from property name to this
     * @param CodeBase $code_base
     * @return bool whether or not we defined it.
     */
    public function setMagicPropertyMap(
        array $magic_property_map,
        CodeBase $code_base
    ) : bool {
        if (count($magic_property_map) === 0) {
            return true;  // Vacuously true.
        }
        $class_fqsen = $this->getFQSEN();
        $context = $this->getContext()->withScope(
            $this->getInternalScope()
        );
        foreach ($magic_property_map as $comment_parameter) {
            // $flags is the same as the flags for `public` and non-internal?
            // Or \ast\flags\MODIFIER_PUBLIC.
            $flags = 0;
            $property_name = $comment_parameter->getName();
            $property_fqsen = FullyQualifiedPropertyName::make(
                $class_fqsen,
                $property_name
            );
            $property = new Property(
                $context,
                $property_name,
                $comment_parameter->getUnionType(),
                $flags,
                $property_fqsen
            );

            $this->addProperty($code_base, $property, new None);
        }
        return true;
    }

    /**
     * @param \Phan\Language\Element\Comment\Method[] $magic_method_map mapping from method name to this.
     * @param CodeBase $code_base
     * @return bool whether or not we defined it.
     */
    public function setMagicMethodMap(
        array $magic_method_map,
        CodeBase $code_base
    ) : bool {
        if (count($magic_method_map) === 0) {
            return true;  // Vacuously true.
        }
        $class_fqsen = $this->getFQSEN();
        $context = $this->getContext()->withScope(
            $this->getInternalScope()
        );
        foreach ($magic_method_map as $comment_method) {
            // $flags is the same as the flags for `public` and non-internal?
            // Or \ast\flags\MODIFIER_PUBLIC.
            $flags = \ast\flags\MODIFIER_PUBLIC;
            if ($comment_method->isStatic()) {
                $flags |= \ast\flags\MODIFIER_STATIC;
            }
            $method_name = $comment_method->getName();
            if ($this->hasMethodWithName($code_base, $method_name)) {
                // No point, and this would hurt inference accuracy.
                continue;
            }
            $method_fqsen = FullyQualifiedMethodName::make(
                $class_fqsen,
                $method_name
            );
            $method = new Method(
                $context,
                $method_name,
                $comment_method->getUnionType(),
                $flags,
                $method_fqsen
            );
            $real_parameter_list = \array_map(function (\Phan\Language\Element\Comment\Parameter $parameter) use ($context) : Parameter {
                return $parameter->asRealParameter($context);
            }, $comment_method->getParameterList());

            $method->setParameterList($real_parameter_list);
            $method->setRealParameterList($real_parameter_list);
            $method->setNumberOfRequiredParameters($comment_method->getNumberOfRequiredParameters());
            $method->setNumberOfOptionalParameters($comment_method->getNumberOfOptionalParameters());
            $method->setIsFromPHPDoc(true);

            $this->addMethod($code_base, $method, new None);
        }
        return true;
    }

    /**
     * @return bool
     */
    public function hasPropertyWithName(
        CodeBase $code_base,
        string $name
    ) : bool {
        return $code_base->hasPropertyWithFQSEN(
            FullyQualifiedPropertyName::make(
                $this->getFQSEN(),
                $name
            )
        );
    }

    /**
     * @return Property[]
     * The list of properties defined on this class
     */
    public function getPropertyList(
        CodeBase $code_base
    ) {
        return $code_base->getPropertyMapByFullyQualifiedClassName(
            $this->getFQSEN()
        );
    }

    /**
     * @param CodeBase $code_base
     * A reference to the entire code base in which the
     * property exists.
     *
     * @param string $name
     * The name of the property
     *
     * @param Context $context
     * The context of the caller requesting the property
     *
     * @return Property
     * A property with the given name
     *
     * @throws IssueException
     * An exception may be thrown if the caller does not
     * have access to the given property from the given
     * context
     */
    public function getPropertyByNameInContext(
        CodeBase $code_base,
        string $name,
        Context $context,
        bool $is_static
    ) : Property {

        // Get the FQSEN of the property we're looking for
        $property_fqsen = FullyQualifiedPropertyName::make(
            $this->getFQSEN(),
            $name
        );

        $property = null;

        // Figure out if we have the property
        $has_property =
            $code_base->hasPropertyWithFQSEN($property_fqsen);

        // Figure out if the property is accessible
        $is_property_accessible = false;
        if ($has_property) {
            $property = $code_base->getPropertyByFQSEN(
                $property_fqsen
            );
            if ($is_static != $property->isStatic()) {
                if ($is_static) {
                    throw new IssueException(
                        Issue::fromType(Issue::AccessPropertyNonStaticAsStatic)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [ "{$this->getFQSEN()}->\${$property->getName()}" ]
                        )
                    );
                } else {
                    throw new IssueException(
                        Issue::fromType(Issue::AccessPropertyStaticAsNonStatic)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [ "{$this->getFQSEN()}::\${$property->getName()}" ]
                        )
                    );
                }
            }

            $is_remote_access = (
                !$context->isInClassScope()
                || !$context->getClassInScope($code_base)
                    ->getUnionType()->canCastToExpandedUnionType(
                        $this->getUnionType(),
                        $code_base
                    )
            );

            $is_property_accessible = (
                !$is_remote_access
                || $property->isPublic()
            );
        }

        // If the property exists and is accessible, return it
        if ($is_property_accessible) {
            return $property;
        }

        // Check to see if we can use a __get magic method
        if (!$is_static && $this->hasMethodWithName($code_base, '__get')) {
            $method = $this->getMethodByName($code_base, '__get');

            // Make sure the magic method is accessible
            if ($method->isPrivate()) {
                throw new IssueException(
                    Issue::fromType(Issue::AccessPropertyPrivate)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [ (string)$property_fqsen ]
                    )
                );
            } elseif ($method->isProtected()) {
                throw new IssueException(
                    Issue::fromType(Issue::AccessPropertyProtected)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [ (string)$property_fqsen ]
                    )
                );
            }

            $property = new Property(
                $context,
                $name,
                $method->getUnionType(),
                0,
                $property_fqsen
            );

            $this->addProperty($code_base, $property, new None);

            return $property;
        } elseif ($has_property) {
            // If we have a property, but its inaccessible, emit
            // an issue
            if ($property->isPrivate()) {
                throw new IssueException(
                    Issue::fromType(Issue::AccessPropertyPrivate)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [ "{$this->getFQSEN()}::\${$property->getName()}" ]
                    )
                );
            }
            if ($property->isProtected()) {
                throw new IssueException(
                    Issue::fromType(Issue::AccessPropertyProtected)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [ "{$this->getFQSEN()}::\${$property->getName()}" ]
                    )
                );
            }
        }

        // Check to see if missing properties are allowed
        // or we're working with a class with dynamic
        // properties such as stdclass.
        if (!$is_static && (Config::getValue('allow_missing_properties')
            || $this->getHasDynamicProperties($code_base))
        ) {
            $property = new Property(
                $context,
                $name,
                new UnionType(),
                0,
                $property_fqsen
            );

            $this->addProperty($code_base, $property, new None);

            return $property;
        }

        // TODO: should be ->, to be consistent with other uses for instance properties?
        throw new IssueException(
            Issue::fromType(Issue::UndeclaredProperty)(
                $context->getFile(),
                $context->getLineNumberStart(),
                [ "{$this->getFQSEN()}::\$$name}" ]
            )
        );
    }

    /**
     * @return Property[]
     * The list of properties on this class
     */
    public function getPropertyMap(CodeBase $code_base) : array
    {
        return $code_base->getPropertyMapByFullyQualifiedClassName(
            $this->getFQSEN()
        );
    }

    /**
     * Inherit a class constant from an ancestor class
     *
     * @return void
     */
    public function inheritConstant(
        CodeBase $code_base,
        ClassConstant $constant
    ) {
        $constant_fqsen = FullyQualifiedClassConstantName::make(
            $this->getFQSEN(),
            $constant->getName()
        );

        if ($code_base->hasClassConstantWithFQSEN($constant_fqsen)) {
            // If the constant with that name already exists, mark it as an override.
            $overriding_constant = $code_base->getClassConstantByFQSEN($constant_fqsen);
            $overriding_constant->setIsOverride(true);
            return;
        }

        // Update the FQSEN if its not associated with this
        // class yet (always true)
        if ($constant->getFQSEN() !== $constant_fqsen) {
            $constant = clone($constant);
            $constant->setFQSEN($constant_fqsen);
        }

        $code_base->addClassConstant($constant);
    }

    /**
     * Add a class constant
     *
     * @return void
     */
    public function addConstant(
        CodeBase $code_base,
        ClassConstant $constant
    ) {
        $constant_fqsen = FullyQualifiedClassConstantName::make(
            $this->getFQSEN(),
            $constant->getName()
        );

        // Update the FQSEN if its not associated with this
        // class yet
        if ($constant->getFQSEN() !== $constant_fqsen) {
            $constant = clone($constant);
            $constant->setFQSEN($constant_fqsen);
        }

        $code_base->addClassConstant($constant);
    }

    /**
     * @return bool
     * True if a constant with the given name is defined
     * on this class.
     */
    public function hasConstantWithName(
        CodeBase $code_base,
        string $name
    ) : bool {
        return $code_base->hasClassConstantWithFQSEN(
            FullyQualifiedClassConstantName::make(
                $this->getFQSEN(),
                $name
            )
        );
    }

    /**
     * @param CodeBase $code_base
     * A reference to the entire code base in which the
     * property exists.
     *
     * @param string $name
     * The name of the class constant
     *
     * @param Context $context
     * The context of the caller requesting the property
     *
     * @return ClassConstant
     * A constant with the given name
     *
     * @throws IssueException
     * An exception may be thrown if the caller does not
     * have access to the given property from the given
     * context
     */
    public function getConstantByNameInContext(
        CodeBase $code_base,
        string $name,
        Context $context
    ) : ClassConstant {

        $constant_fqsen = FullyQualifiedClassConstantName::make(
            $this->getFQSEN(),
            $name
        );

        if (!$code_base->hasClassConstantWithFQSEN($constant_fqsen)) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredClassConstant)(
                    $context->getFile(),
                    $context->getLineNumberStart(),
                    [
                        (string)$constant_fqsen,
                        (string)$this->getFQSEN()
                    ]
                )
            );
        }

        $constant = $code_base->getClassConstantByFQSEN(
            $constant_fqsen
        );

        // Are we within a class referring to the class
        // itself?
        $is_local_access = (
            $context->isInClassScope()
            && $context->getClassInScope($code_base) === $constant->getClass($code_base)
        );

        // Are we within a class or an extending sub-class
        // referring to the class?
        $is_local_or_remote_access = (
            $is_local_access
            || (
                $context->isInClassScope()
                && $context->getClassInScope($code_base)
                ->getUnionType()->canCastToExpandedUnionType(
                    $this->getUnionType(),
                    $code_base
                )
            )
        );

        // If we have the constant, but its inaccessible, emit
        // an issue
        if (!$is_local_access && $constant->isPrivate()) {
            throw new IssueException(
                Issue::fromType(Issue::AccessClassConstantPrivate)(
                    $context->getFile(),
                    $context->getLineNumberStart(),
                    [
                        (string)$constant_fqsen,
                        $constant->getContext()->getFile(),
                        $constant->getContext()->getLineNumberStart()
                    ]
                )
            );
        } elseif (!$is_local_or_remote_access && $constant->isProtected()) {
            throw new IssueException(
                Issue::fromType(Issue::AccessClassConstantProtected)(
                    $context->getFile(),
                    $context->getLineNumberStart(),
                    [
                        (string)$constant_fqsen,
                        $constant->getContext()->getFile(),
                        $constant->getContext()->getLineNumberStart()
                    ]
                )
            );
        }

        return $constant;
    }

    /**
     * @return ClassConstant[]
     * The constants associated with this class
     */
    public function getConstantMap(CodeBase $code_base) : array
    {
        return $code_base->getClassConstantMapByFullyQualifiedClassName(
            $this->getFQSEN()
        );
    }

    /**
     * Add a method to this class
     *
     * @param CodeBase $code_base
     * A reference to the code base in which the ancestor exists
     *
     * @param Method $method
     * The method to copy onto this class
     *
     * @param Option<Type>|None $type_option
     * A possibly defined type used to define template
     * parameter types when importing the method
     *
     * @return void
     */
    public function addMethod(
        CodeBase $code_base,
        Method $method,
        $type_option
    ) {
        $method_fqsen = FullyQualifiedMethodName::make(
            $this->getFQSEN(),
            $method->getName(),
            $method->getFQSEN()->getAlternateId()
        );

        $is_override = $code_base->hasMethodWithFQSEN($method_fqsen);
        // Don't overwrite overridden methods with
        // parent methods
        if ($is_override) {
            // Note that we're overriding something
            // (but only do this if it's abstract)
            // TODO: Consider all permutations of abstract and real methods on classes, interfaces, and traits.
            $existing_method =
                $code_base->getMethodByFQSEN($method_fqsen);
            // Note: For private/protected methods, the defining FQSEN is set to the FQSEN of the inheriting class.
            // So, when multiple traits are inherited, they may identical defining FQSENs, but some may be abstract, and others may be implemented.
            if ($method->getDefiningFQSEN() === $existing_method->getDefiningFQSEN() && $method->isAbstract() === $existing_method->isAbstract()) {
                return;
            }

            if ($method->isAbstract() || !$existing_method->isAbstract() || $existing_method->getIsNewConstructor()) {
                // TODO: What if both of these are abstract, and those get combined into an abstract class?
                //       Should phan check compatibility of the abstract methods it inherits?
                $existing_method->setIsOverride(true);

                // Don't add the method
                return;
            }
        }

        if ($method->getFQSEN() !== $method_fqsen) {
            $method = clone($method);
            $method->setFQSEN($method_fqsen);
            // When we inherit it from the ancestor class, it may be an override in the ancestor class,
            // but that doesn't imply it's an override in *this* class.
            $method->setIsOverride($is_override);

            // Clone the parameter list, so that modifying the parameters on the first call won't modify the others.
            // TODO: Make the parameter list immutable and have immutable types (e.g. getPhpdocParameterList(), setPhpdocParameterList()
            // and use a clone of all of the parameters for analysis (quick_mode=false has different values).
            // TODO: If they're immutable, they can be shared without cloning with less worry.
            $method->setParameterList(
                \array_map(
                    function (Parameter $parameter) : Parameter {
                        return clone($parameter);
                    },
                    $method->getParameterList()
                )
            );

            // If we have a parent type defined, map the method's
            // return type and parameter types through it
            if ($type_option->isDefined()) {
                // Map the method's return type
                if ($method->getUnionType()->hasTemplateType()) {
                    $method->setUnionType(
                        $method->getUnionType()->withTemplateParameterTypeMap(
                            $type_option->get()->getTemplateParameterTypeMap(
                                $code_base
                            )
                        )
                    );
                }

                // Map each method parameter
                $method->setParameterList(
                    \array_map(function (Parameter $parameter) use ($type_option, $code_base) : Parameter {

                        if (!$parameter->getUnionType()->hasTemplateType()) {
                            return $parameter;
                        }

                        $mapped_parameter = clone($parameter);

                        $mapped_parameter->setUnionType(
                            $mapped_parameter->getUnionType()->withTemplateParameterTypeMap(
                                $type_option->get()->getTemplateParameterTypeMap(
                                    $code_base
                                )
                            )
                        );

                        return $mapped_parameter;
                    }, $method->getParameterList())
                );
            }
        }
        if ($method->getHasYield()) {
            // There's no phpdoc standard for template types of Generators at the moment.
            $newType = UnionType::fromFullyQualifiedString('\\Generator');
            if (!$newType->canCastToUnionType($method->getUnionType())) {
                $method->setUnionType($newType);
            }
        }

        // Methods defined on interfaces are always abstract, but don't have that flag set.
        // NOTE: __construct is special for the following reasons:
        // 1. We automatically add __construct to class-like definitions (Not sure why it's done for interfaces)
        // 2. If it's abstract, then PHP would enforce that signatures are compatible
        if ($this->isInterface() && !$method->getIsNewConstructor()) {
            $method->setFlags(Flags::bitVectorWithState($method->getFlags(), \ast\flags\MODIFIER_ABSTRACT, true));
        }

        if ($is_override) {
            $method->setIsOverride(true);
        }

        $code_base->addMethod($method);
    }

    /**
     * @return bool
     * True if this class has a method with the given name
     */
    public function hasMethodWithName(
        CodeBase $code_base,
        string $name,
        bool $is_direct_invocation = false
    ) : bool {
        // All classes have a constructor even if it hasn't
        // been declared yet
        if (!$is_direct_invocation && '__construct' === strtolower($name)) {
            return true;
        }

        $method_fqsen = FullyQualifiedMethodName::make(
            $this->getFQSEN(),
            $name
        );

        return $code_base->hasMethodWithFQSEN($method_fqsen);
    }

    /**
     * @return Method
     * The method with the given name
     */
    public function getMethodByName(
        CodeBase $code_base,
        string $name
    ) : Method {
        $method_fqsen = FullyQualifiedMethodName::make(
            $this->getFQSEN(),
            $name
        );

        if (!$code_base->hasMethodWithFQSEN($method_fqsen)) {
            if ('__construct' === $name) {
                // Create a default constructor if its requested
                // but doesn't exist yet
                $default_constructor =
                    Method::defaultConstructorForClass(
                        $this,
                        $code_base
                    );

                $this->addMethod($code_base, $default_constructor, $this->getParentTypeOption());

                return $default_constructor;
            }

            throw new CodeBaseException(
                $method_fqsen,
                "Method with name $name does not exist for class {$this->getFQSEN()}."
            );
        }

        return $code_base->getMethodByFQSEN($method_fqsen);
    }

    /**
     * @return Method[]
     * A list of methods on this class
     */
    public function getMethodMap(CodeBase $code_base) : array
    {
        return $code_base->getMethodMapByFullyQualifiedClassName(
            $this->getFQSEN()
        );
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__call' method
     */
    public function hasCallMethod(CodeBase $code_base) : bool
    {
        return $this->hasMethodWithName($code_base, '__call');
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__call' method,
     * and (at)phan-forbid-undeclared-magic-methods doesn't exist on this class or ancestors
     */
    public function allowsCallingUndeclaredInstanceMethod(CodeBase $code_base)
    {
        return $this->hasCallMethod($code_base) &&
            !$this->getForbidUndeclaredMagicMethods($code_base);
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return Method
     * The magic `__call` method
     */
    public function getCallMethod(CodeBase $code_base)
    {
        return $this->getMethodByName($code_base, '__call');
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__callStatic' method
     */
    public function hasCallStaticMethod(CodeBase $code_base) : bool
    {
        return $this->hasMethodWithName($code_base, '__callStatic');
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__callStatic' method,
     * and (at)phan-forbid-undeclared-magic-methods doesn't exist on this class or ancestors.
     */
    public function allowsCallingUndeclaredStaticMethod(CodeBase $code_base)
    {
        return $this->hasCallStaticMethod($code_base) &&
            !$this->getForbidUndeclaredMagicMethods($code_base);
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return Method
     * The magic `__callStatic` method
     */
    public function getCallStaticMethod(CodeBase $code_base)
    {
        return $this->getMethodByName($code_base, '__callStatic');
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__call' or '__callStatic'
     * method
     */
    public function hasCallOrCallStaticMethod(CodeBase $code_base)
    {
        return (
            $this->hasCallMethod($code_base)
            || $this->hasCallStaticMethod($code_base)
        );
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__get' method
     */
    public function hasGetMethod(CodeBase $code_base) : bool
    {
        return $this->hasMethodWithName($code_base, '__get');
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__set' method
     */
    public function hasSetMethod(CodeBase $code_base) : bool
    {
        return $this->hasMethodWithName($code_base, '__set');
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__get' or '__set'
     * method
     */
    public function hasGetOrSetMethod(CodeBase $code_base)
    {
        return (
            $this->hasGetMethod($code_base)
            || $this->hasSetMethod($code_base)
        );
    }

    /**
     * @return void
     */
    public function addTraitFQSEN(FQSEN $fqsen)
    {
        $this->trait_fqsen_list[] = $fqsen;

        // Add the trait to the union type of this class
        $this->getUnionType()->addUnionType(
            UnionType::fromFullyQualifiedString((string)$fqsen)
        );
    }

    /**
     * @return void
     */
    public function addTraitAdaptations(TraitAdaptations $trait_adaptations)
    {
        $this->trait_adaptations_map[strtolower($trait_adaptations->getTraitFQSEN()->__toString())] = $trait_adaptations;
    }

    /**
     * @return FullyQualifiedClassName[]
     * A list of FQSEN's for included traits
     */
    public function getTraitFQSENList() : array
    {
        return $this->trait_fqsen_list;
    }

    /**
     * @return bool
     * True if this class calls its parent constructor
     */
    public function getIsParentConstructorCalled() : bool
    {
        return Flags::bitVectorHasState(
            $this->getPhanFlags(),
            Flags::IS_PARENT_CONSTRUCTOR_CALLED
        );
    }

    /**
     * @return void
     */
    public function setIsParentConstructorCalled(
        bool $is_parent_constructor_called
    ) {
        $this->setPhanFlags(Flags::bitVectorWithState(
            $this->getPhanFlags(),
            Flags::IS_PARENT_CONSTRUCTOR_CALLED,
            $is_parent_constructor_called
        ));
    }

    /**
     * Check if this class or its ancestors forbids undeclared magic properties.
     */
    public function getForbidUndeclaredMagicProperties(CodeBase $code_base) : bool
    {
        return (
            Flags::bitVectorHasState(
                $this->getPhanFlags(),
                Flags::CLASS_FORBID_UNDECLARED_MAGIC_PROPERTIES
            )
            ||
            (
                $this->hasParentType()
                && $code_base->hasClassWithFQSEN($this->getParentClassFQSEN())
                && $this->getParentClass($code_base)->getForbidUndeclaredMagicProperties($code_base)
            )
        );
    }

    /**
     * Set whether undeclared magic properties are forbidden
     * (properties accessed through __get or __set, with no (at)property annotation on parent class)
     * @param bool $forbid_undeclared_dynamic_properties - set to true to forbid.
     * @return void
     */
    public function setForbidUndeclaredMagicProperties(
        bool $forbid_undeclared_dynamic_properties
    ) {
        $this->setPhanFlags(Flags::bitVectorWithState(
            $this->getPhanFlags(),
            Flags::CLASS_FORBID_UNDECLARED_MAGIC_PROPERTIES,
            $forbid_undeclared_dynamic_properties
        ));
    }

    /**
     * Check if this class or its ancestors forbids undeclared magic methods.
     */
    public function getForbidUndeclaredMagicMethods(CodeBase $code_base) : bool
    {
        return (
            Flags::bitVectorHasState(
                $this->getPhanFlags(),
                Flags::CLASS_FORBID_UNDECLARED_MAGIC_METHODS
            )
            ||
            (
                $this->hasParentType()
                && $code_base->hasClassWithFQSEN($this->getParentClassFQSEN())
                && $this->getParentClass($code_base)->getForbidUndeclaredMagicMethods($code_base)
            )
        );
    }

    /**
     * Set whether undeclared magic methods are forbidden
     * (methods accessed through __call or __callStatic, with no (at)method annotation on class)
     * @param bool $forbid_undeclared_magic_methods - set to true to forbid.
     * @return void
     */
    public function setForbidUndeclaredMagicMethods(
        bool $forbid_undeclared_magic_methods
    ) {
        $this->setPhanFlags(Flags::bitVectorWithState(
            $this->getPhanFlags(),
            Flags::CLASS_FORBID_UNDECLARED_MAGIC_METHODS,
            $forbid_undeclared_magic_methods
        ));
    }

    /**
     * @return bool
     * True if this class has dynamic properties. (e.g. stdClass)
     */
    public function getHasDynamicProperties(CodeBase $code_base) : bool
    {
        return (
            Flags::bitVectorHasState(
                $this->getPhanFlags(),
                Flags::CLASS_HAS_DYNAMIC_PROPERTIES
            )
            ||
            (
                $this->hasParentType()
                && $code_base->hasClassWithFQSEN($this->getParentClassFQSEN())
                && $this->getParentClass($code_base)->getHasDynamicProperties($code_base)
            )
        );
    }

    /**
     * @return void
     */
    public function setHasDynamicProperties(
        bool $has_dynamic_properties
    ) {
        $this->setPhanFlags(Flags::bitVectorWithState(
            $this->getPhanFlags(),
            Flags::CLASS_HAS_DYNAMIC_PROPERTIES,
            $has_dynamic_properties
        ));
    }


    /**
     * @return bool
     * True if this is a final class
     */
    public function isFinal() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\CLASS_FINAL
        );
    }

    /**
     * @return bool
     * True if this is an abstract class
     */
    public function isAbstract() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\CLASS_ABSTRACT
        );
    }

    /**
     * @return bool
     * True if this is an interface
     */
    public function isInterface() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\CLASS_INTERFACE
        );
    }

    /**
     * @return bool
     * True if this class is a trait
     */
    public function isTrait() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\CLASS_TRAIT
        );
    }

    /**
     * @return FullyQualifiedClassName
     */
    public function getFQSEN() : FullyQualifiedClassName
    {
        return $this->fqsen;
    }

    /**
     * @return FullyQualifiedClassName[]
     */
    public function getNonParentAncestorFQSENList()
    {
        return \array_merge(
            $this->getInterfaceFQSENList(),
            $this->getTraitFQSENList()
        );
    }

    /**
     * @return FullyQualifiedClassName[]
     */
    public function getAncestorFQSENList()
    {
        $ancestor_list = $this->getNonParentAncestorFQSENList();

        if ($this->hasParentType()) {
            $ancestor_list[] = $this->getParentClassFQSEN();
        }

        return $ancestor_list;
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @param FullyQualifiedClassName[]
     * A list of class FQSENs to turn into a list of
     * Clazz objects
     *
     * @return Clazz[]
     */
    private function getClassListFromFQSENList(
        CodeBase $code_base,
        array $fqsen_list
    ) : array {
        $class_list = [];
        foreach ($fqsen_list as $fqsen) {
            if ($code_base->hasClassWithFQSEN($fqsen)) {
                $class_list[] = $code_base->getClassByFQSEN($fqsen);
            }
        }
        return $class_list;
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return Clazz[]
     */
    public function getAncestorClassList(CodeBase $code_base)
    {
        return $this->getClassListFromFQSENList(
            $code_base,
            $this->getAncestorFQSENList()
        );
    }

    /**
     * Add properties, constants and methods from all
     * ancestors (parents, traits, ...) to this class
     *
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return void
     */
    public function importAncestorClasses(CodeBase $code_base)
    {
        if (!$this->isFirstExecution(__METHOD__)) {
            return;
        }

        foreach ($this->getInterfaceFQSENList() as $fqsen) {
            if (!$code_base->hasClassWithFQSEN($fqsen)) {
                continue;
            }

            $ancestor = $code_base->getClassByFQSEN($fqsen);

            if (!$ancestor->isInterface()) {
                $this->emitWrongInheritanceCategoryWarning($code_base, $ancestor, 'Interface');
            }

            $this->importAncestorClass(
                $code_base,
                $ancestor,
                new None
            );
        }

        foreach ($this->getTraitFQSENList() as $fqsen) {
            if (!$code_base->hasClassWithFQSEN($fqsen)) {
                continue;
            }

            $ancestor = $code_base->getClassByFQSEN($fqsen);
            if (!$ancestor->isTrait()) {
                $this->emitWrongInheritanceCategoryWarning($code_base, $ancestor, 'Trait');
            }

            $this->importAncestorClass(
                $code_base,
                $ancestor,
                new None
            );
        }

        // Copy information from the parent(s)
        $this->importParentClass($code_base);
    }

    /*
     * Add properties, constants and methods from the
     * parent of this class
     *
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return void
     */
    private function importParentClass(CodeBase $code_base)
    {
        if (!$this->isFirstExecution(__METHOD__)) {
            return;
        }

        if (!$this->hasParentType()) {
            return;
        }

        if ($this->getParentClassFQSEN() == $this->getFQSEN()) {
            return;
        }

        // Let the parent class finder worry about this
        if (!$code_base->hasClassWithFQSEN(
            $this->getParentClassFQSEN()
        )) {
            return;
        }

        \assert(
            $code_base->hasClassWithFQSEN($this->getParentClassFQSEN()),
            "Clazz should already have been proven to exist."
        );

        // Get the parent class
        $parent = $this->getParentClass($code_base);

        if ($parent->isTrait() || $parent->isInterface()) {
            $this->emitWrongInheritanceCategoryWarning($code_base, $parent, 'Class');
        }
        if ($parent->isFinal()) {
            $this->emitExtendsFinalClassWarning($code_base, $parent);
        }

        $parent->addReference($this->getContext());

        // Tell the parent to import its own parents first

        // Import elements from the parent
        $this->importAncestorClass(
            $code_base,
            $parent,
            $this->getParentTypeOption()
        );
    }

    /**
     * @return void
     */
    private function emitWrongInheritanceCategoryWarning(
        CodeBase $code_base,
        Clazz $ancestor,
        string $expected_inheritance_category
    ) {
        $context = $this->getContext();
        if ($ancestor->isPHPInternal()) {
            if (!$this->hasSuppressIssue(Issue::AccessWrongInheritanceCategoryInternal)) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::AccessWrongInheritanceCategoryInternal,
                    $context->getLineNumberStart(),
                    (string)$ancestor,
                    $expected_inheritance_category
                );
            }
        } else {
            if (!$this->hasSuppressIssue(Issue::AccessWrongInheritanceCategory)) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::AccessWrongInheritanceCategory,
                    $context->getLineNumberStart(),
                    (string)$ancestor,
                    $ancestor->getFileRef()->getFile(),
                    $ancestor->getFileRef()->getLineNumberStart(),
                    $expected_inheritance_category
                );
            }
        }
    }

    /**
     * @return void
     */
    private function emitExtendsFinalClassWarning(
        CodeBase $code_base,
        Clazz $ancestor
    ) {
        $context = $this->getContext();
        if ($ancestor->isPHPInternal()) {
            if (!$this->hasSuppressIssue(Issue::AccessExtendsFinalClassInternal)) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::AccessExtendsFinalClassInternal,
                    $context->getLineNumberStart(),
                    (string)$ancestor->getFQSEN()
                );
            }
        } else {
            if (!$this->hasSuppressIssue(Issue::AccessExtendsFinalClass)) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::AccessExtendsFinalClass,
                    $context->getLineNumberStart(),
                    (string)$ancestor->getFQSEN(),
                    $ancestor->getFileRef()->getFile(),
                    $ancestor->getFileRef()->getLineNumberStart()
                );
            }
        }
    }

    /**
     * Add properties, constants and methods from the given
     * class to this.
     *
     * @param CodeBase $code_base
     * A reference to the code base in which the ancestor exists
     *
     * @param Clazz $class
     * A class to import from
     *
     * @param Option<Type>|None $type_option
     * A possibly defined ancestor type used to define template
     * parameter types when importing ancestor properties and
     * methods
     *
     * @return void
     */
    public function importAncestorClass(
        CodeBase $code_base,
        Clazz $class,
        $type_option
    ) {
        $key = strtolower((string)$class->getFQSEN());
        if (!$this->isFirstExecution(
            __METHOD__ . ':' . $key
        )) {
            return;
        }

        $class->addReference($this->getContext());

        // Make sure that the class imports its parents first
        $class->hydrate($code_base);
        $is_trait = $class->isTrait();
        $trait_adaptations = $is_trait ? ($this->trait_adaptations_map[$key] ?? null) : null;

        // Copy properties
        foreach ($class->getPropertyMap($code_base) as $property) {
            // TODO: check for conflicts in visibility and default values for traits.
            // TODO: Check for ancestor classes with the same private property?
            $this->addProperty(
                $code_base,
                $property,
                $type_option
            );
        }

        // Copy constants
        foreach ($class->getConstantMap($code_base) as $constant) {
            $this->inheritConstant($code_base, $constant);
        }

        // Copy methods
        foreach ($class->getMethodMap($code_base) as $method) {
            if (!\is_null($trait_adaptations) && count($trait_adaptations->hidden_methods) > 0) {
                $method_name_key = strtolower($method->getName());
                if (isset($trait_adaptations->hidden_methods[$method_name_key])) {
                    // TODO: Record that the method was hidden, and check later on that all method that were hidden were actually defined?
                    continue;
                }
            }
            // Workaround: For private methods, copy the method with a new defining class.
            // If you import a trait's private method, it becomes private **to the class which used the trait** in PHP code.
            // (But preserving the defining FQSEN is fine for this)
            if ($is_trait) {
                $method_flags = $method->getFlags();
                if (Flags::bitVectorHasState($method_flags, \ast\flags\MODIFIER_PRIVATE)) {
                    $method = $method->createUseAlias($this, $method->getName(), \ast\flags\MODIFIER_PRIVATE);
                } elseif (Flags::bitVectorHasState($method_flags, \ast\flags\MODIFIER_PROTECTED)) {
                    $method = $method->createUseAlias($this, $method->getName(), \ast\flags\MODIFIER_PROTECTED);
                }
            }
            $this->addMethod(
                $code_base,
                $method,
                $type_option
            );
        }

        if (!\is_null($trait_adaptations)) {
            $this->importTraitAdaptations($code_base, $class, $trait_adaptations, $type_option);
        }
    }

    /**
     * @param CodeBase $code_base
     * @param Clazz $class
     * @param TraitAdaptations $trait_adaptations
     * @param Option<Type>|None $type_option
     * A possibly defined ancestor type used to define template
     * parameter types when importing ancestor properties and
     * methods
     *
     * @return void
     */
    private function importTraitAdaptations(
        CodeBase $code_base,
        Clazz $class,
        TraitAdaptations $trait_adaptations,
        $type_option
    ) {
        foreach ($trait_adaptations->alias_methods ?? [] as $alias_method_name => $original_trait_alias_source) {
            $source_method_name = $original_trait_alias_source->getSourceMethodName();
            if (!$class->hasMethodWithName($code_base, $source_method_name)) {
                Issue::maybeEmit(
                    $code_base,
                    $this->getContext(),
                    Issue::UndeclaredAliasedMethodOfTrait,
                    $original_trait_alias_source->getAliasLineno(),  // TODO: Track line number in TraitAdaptation
                    sprintf('%s::%s', (string)$this->getFQSEN(), $alias_method_name),
                    sprintf('%s::%s', (string)$class->getFQSEN(), $source_method_name),
                    $class->getName()
                );
                continue;
            }
            $source_method = $class->getMethodByName($code_base, $source_method_name);
            $alias_method = $source_method->createUseAlias(
                $this,
                $alias_method_name,
                $original_trait_alias_source->getAliasVisibilityFlags()
            );
            $this->addMethod($code_base, $alias_method, $type_option);
        }
    }

    /**
     * @return int
     * The number of references to this typed structural element
     */
    public function getReferenceCount(
        CodeBase $code_base
    ) : int {
        $count = parent::getReferenceCount($code_base);

        // A function that maps a list of elements to the
        // total reference count for all elements
        $list_count = function (array $list) use ($code_base) {
            return \array_reduce($list, function (
                int $count,
                AddressableElement $element
            ) use ($code_base) {
                return (
                    $count
                    + $element->getReferenceCount($code_base)
                );
            }, 0);
        };

        // Sum up counts for all dependent elements
        $count += $list_count($this->getPropertyList($code_base));
        $count += $list_count($this->getMethodMap($code_base));
        $count += $list_count($this->getConstantMap($code_base));

        return $count;
    }

    /**
     * @return bool
     * True if this class contains generic types
     */
    public function isGeneric() : bool
    {
        return $this->getInternalScope()->hasAnyTemplateType();
    }

    /**
     * @return TemplateType[]
     * The set of all template types parameterizing this generic
     * class
     */
    public function getTemplateTypeMap() : array
    {
        return $this->getInternalScope()->getTemplateTypeMap();
    }

    /**
     * @return string
     * A string describing this class
     */
    public function __toString() : string
    {
        $string = '';

        if ($this->isFinal()) {
            $string .= 'final ';
        }

        if ($this->isAbstract()) {
            $string .= 'abstract ';
        }

        if ($this->isInterface()) {
            $string .= 'Interface ';
        } elseif ($this->isTrait()) {
            $string .= 'Trait ';
        } else {
            $string .= 'Class ';
        }

        $string .= (string)$this->getFQSEN()->getCanonicalFQSEN();

        return $string;
    }

    private function toStubSignature(CodeBase $code_base) : string
    {
        $string = '';

        if ($this->isFinal()) {
            $string .= 'final ';
        }

        if ($this->isAbstract() && !$this->isInterface()) {
            $string .= 'abstract ';
        }

        if ($this->isInterface()) {
            $string .= 'interface ';
        } elseif ($this->isTrait()) {
            $string .= 'trait ';
        } else {
            $string .= 'class ';
        }

        $string .= (string)$this->getFQSEN()->getName();

        $extend_types = [];
        $implements_types = [];
        $parent_implements_types = [];

        $parent_type = $this->parent_type;
        if ($parent_type) {
            $extend_types[] = $parent_type->asFQSEN();
            $parent_class = $this->getParentClass($code_base);
            $parent_implements_types = $parent_class->interface_fqsen_list;
        }

        if (count($this->interface_fqsen_list) > 0) {
            if ($this->isInterface()) {
                $extend_types = array_merge($extend_types, $this->interface_fqsen_list);
            } else {
                $implements_types = $this->interface_fqsen_list;
                if (count($parent_implements_types) > 0) {
                    $implements_types = array_diff($implements_types, $parent_implements_types);
                }
            }
        }
        if (count($extend_types) > 0) {
            $string .= ' extends ' . implode(', ', $extend_types);
        }
        if (count($implements_types) > 0) {
            $string .= ' implements ' . implode(', ', $implements_types);
        }
        return $string;
    }

    public function toStub(CodeBase $code_base) : string
    {
        list($namespace, $string) = $this->toStubInfo($code_base);
        $namespace_text = $namespace === '' ? '' : "$namespace ";
        $string = sprintf("namespace %s{\n%s}\n", $namespace_text, $string);
        return $string;
    }

    /** @return string[] [string $namespace, string $text] */
    public function toStubInfo(CodeBase $code_base) : array
    {
        $signature = $this->toStubSignature($code_base);

        $stub = $signature;

        $stub .= " {";

        $constant_map = $this->getConstantMap($code_base);
        if (count($constant_map) > 0) {
            $stub .= "\n\n    // constants\n";
            $stub .= implode("\n", array_map(function (ClassConstant $constant) {
                return $constant->toStub();
            }, $constant_map));
        }

        $property_map = $this->getPropertyMap($code_base);
        if (count($property_map) > 0) {
            $stub .= "\n\n    // properties\n";

            $stub .= implode("\n", array_map(function (Property $property) {
                return $property->toStub();
            }, $property_map));
        }
        $reflection_class = new \ReflectionClass((string)$this->getFQSEN());
        $method_map = array_filter($this->getMethodMap($code_base), function (Method $method) use ($reflection_class) : bool {
            if ($method->getFQSEN()->isAlternate()) {
                return false;
            }
            $reflection_method = $reflection_class->getMethod($method->getName());
            if ($reflection_method->getDeclaringClass()->getName() !== $reflection_class->getName()) {
                return false;
            }
            return true;
        });
        if (count($method_map) > 0) {
            $stub .= "\n\n    // methods\n";

            $stub .= implode("\n", array_map(function (Method $method) use ($code_base) {
                return $method->toStub($code_base);
            }, $method_map));
        }

        $stub .= "\n}\n\n";
        $namespace = ltrim($this->getFQSEN()->getNamespace(), '\\');
        return [$namespace, $stub];
    }

    /**
     * This method must be called before analysis
     * begins.
     *
     * @return void
     */
    protected function hydrateOnce(CodeBase $code_base)
    {
        foreach ($this->getAncestorFQSENList() as $fqsen) {
            if ($code_base->hasClassWithFQSEN($fqsen)) {
                $code_base->getClassByFQSEN(
                    $fqsen
                )->hydrate($code_base);
            }
        }

        // Create the 'class' constant
        $class_constant = new ClassConstant(
            $this->getContext(),
            'class',
            StringType::instance(false)->asUnionType(),
            0,
            FullyQualifiedClassConstantName::make(
                $this->getFQSEN(),
                'class'
            )
        );
        $class_constant->setNodeForValue((string)$this->getFQSEN());
        $this->addConstant($code_base, $class_constant);

        // Add variable '$this' to the scope
        $this->getInternalScope()->addVariable(
            new Variable(
                $this->getContext(),
                'this',
                $this->getUnionType(),
                0
            )
        );

        // Fetch the constants declared within the class, to check if they have override annotations later.
        $original_declared_class_constants = $this->getConstantMap($code_base);

        // Load parent methods, properties, constants
        $this->importAncestorClasses($code_base);

        // Make sure there are no abstract methods on non-abstract classes
        AbstractMethodAnalyzer::analyzeAbstractMethodsAreImplemented(
            $code_base,
            $this
        );

        self::analyzeClassConstantOverrides($code_base, $original_declared_class_constants);
    }

    /**
     * @param ClassConstant[] $original_declared_class_constants
     * @return void
     */
    private function analyzeClassConstantOverrides(CodeBase $code_base, array $original_declared_class_constants)
    {
        foreach ($original_declared_class_constants as $constant) {
            if ($constant->isOverrideIntended() && !$constant->getIsOverride()) {
                if ($constant->hasSuppressIssue(Issue::CommentOverrideOnNonOverrideConstant)) {
                    continue;
                }
                $context = $constant->getContext();
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::CommentOverrideOnNonOverrideConstant,
                    $context->getLineNumberStart(),
                    (string)$constant->getFQSEN()
                );
            }
        }
    }

    /**
     * This method should be called after hydration
     *
     * @return void
     */
    final public function analyze(CodeBase $code_base)
    {
        if ($this->isPHPInternal()) {
            return;
        }

        // Make sure the parent classes exist
        ClassInheritanceAnalyzer::analyzeClassInheritance(
            $code_base,
            $this
        );

        DuplicateClassAnalyzer::analyzeDuplicateClass(
            $code_base,
            $this
        );

        ParentConstructorCalledAnalyzer::analyzeParentConstructorCalled(
            $code_base,
            $this
        );

        PropertyTypesAnalyzer::analyzePropertyTypes(
            $code_base,
            $this
        );

        // Analyze this class to make sure that we don't have conflicting
        // types between similar inherited methods.
        CompositionAnalyzer::analyzeComposition(
            $code_base,
            $this
        );

        // Let any configured plugins analyze the class
        ConfigPluginSet::instance()->analyzeClass(
            $code_base,
            $this
        );
    }
}
