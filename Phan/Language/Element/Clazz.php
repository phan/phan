<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\CodeBase;
use \Phan\Exception\AccessException;
use \Phan\Language\Context;
use \Phan\Language\Element\Comment;
use \Phan\Language\Element\Constant;
use \Phan\Language\Element\Method;
use \Phan\Language\Element\Property;
use \Phan\Language\FQSEN;
use \Phan\Language\Type;
use \Phan\Language\UnionType;
use \Phan\Persistent\Column;
use \Phan\Persistent\Schema;

class Clazz extends TypedStructuralElement {
    use \Phan\Memoize;

    /**
     * @var \Phan\Language\FQSEN
     */
    private $parent_class_fqsen = null;

    /**
     * @var \Phan\Language\FQSEN[]
     * A possibly empty list of interfaces implemented
     * by this class
     */
    private $interface_fqsen_list = [];

    /**
     * @var \Phan\Language\FQSEN[]
     * A possibly empty list of traits used by this class
     */
    private $trait_fqsen_list = [];

    /**
     * @var Constant[]
     * A list of constants defined on this class
     */
    private $constant_map = [];

    /**
     * @var Property[]
     * A list of properties defined on this class
     */
    private $property_map = [];

    /**
     * @var Method[]
     * A list of methods defined on this class
     */
    private $method_map = [];

    /**
     * @var bool
     * True if this class's constructor calls it's
     * parent constructor.
     */
    private $is_parent_constructor_called = true;

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param CommentElement $comment,
     * Any comment block associated with the class
     *
     * @param string $name,
     * The name of the typed structural element
     *
     * @param UnionType $type,
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     *
     * @param int $flags,
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    public function __construct(
        Context $context,
        Comment $comment,
        string $name,
        UnionType $type,
        int $flags
    ) {
        // Add variable '$this' to the scope
        $context =
            $context->withScopeVariable(new Variable(
                $context,
                Comment::none(),
                'this',
                $type,
                0
            ));

        parent::__construct(
            $context,
            $comment,
            $name,
            $type,
            $flags
        );

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
     * @param ReflectionClass $class
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
        if($class->isFinal()) {
            $flags = \ast\flags\CLASS_FINAL;
        } else if($class->isInterface()) {
            $flags = \ast\flags\CLASS_INTERFACE;
        } else if($class->isTrait()) {
            $flags = \ast\flags\CLASS_TRAIT;
        }
        if($class->isAbstract()) {
            $flags |= \ast\flags\CLASS_ABSTRACT;
        }

        $context = new Context();

        // Build a base class element
        $clazz = new Clazz(
            $context,
            Comment::none(),
            $class->getName(),
            UnionType::fromStringInContext($class->getName(), $context),
            $flags
        );

        // If this class has a parent class, add it to the
        // class info
        if(($parent_class = $class->getParentClass())) {

            $parent_class_fqsen = FQSEN::fromFullyQualifiedString(
                '\\' . $parent_class->getName()
            );

            $clazz->setParentClassFQSEN($parent_class_fqsen);
        }

        foreach($class->getDefaultProperties() as $name => $value) {
            $property =
                new \ReflectionProperty($class->getName(), $name);

            $property_element =
                new Property(
                    $context->withClassFQSEN($clazz->getFQSEN()),
                    Comment::none(),
                    $name,
                    Type::fromObject($value)->asUnionType(),
                    0
                );

            $clazz->property_map[
                $property_element->getFQSEN()->__toString()
            ] = $property_element;
        }

        foreach ($class->getInterfaceNames() as $name) {
            $clazz->addInterfaceClassFQSEN(
                FQSEN::fromFullyQualifiedString(
                    '\\' . $name
                )
            );
        }

        foreach ($class->getTraitNames() as $name) {
            $clazz->addTraitFQSEN(
                FQSEN::fromFullyQualifiedString(
                    '\\' . $name
                )
            );
        }

        foreach($class->getConstants() as $name => $value) {
            $clazz->constant_map[$name] =
                new Constant(
                    $context,
                    Comment::none(),
                    $name,
                    Type::fromObject($value)->asUnionType(),
                    0
                );
        }

        foreach($class->getMethods() as $reflection_method) {
            $method_list =
                Method::methodListFromReflectionClassAndMethod(
                    $context->withClassFQSEN($clazz->getFQSEN()),
                    $code_base,
                    $class,
                    $reflection_method
                );

            foreach ($method_list as $method) {
                $code_base->addMethod($method);
                $clazz->addMethod($method);
            }
        }

        return $clazz;
    }

    /**
     * @param FQSEN $fqsen
     * The parent class to associate with this class
     *
     * @return null
     */
    public function setParentClassFQSEN(FQSEN $fqsen) {
        $this->parent_class_fqsen = $fqsen;

        // Add the parent to the union type of this
        // class
        $this->getUnionType()->addUnionType(
            UnionType::fromFullyQualifiedString((string)$fqsen)
        );
    }

    /**
     * @return bool
     * True if this class has a parent class
     */
    public function hasParentClassFQSEN() : bool {
        return !empty($this->parent_class_fqsen);
    }

    /**
     * @return FQSEN
     * The parent class of this class if one exists
     */
    public function getParentClassFQSEN() : FQSEN {
        return $this->parent_class_fqsen;
    }

    /**
     * @param FQSEN $fqsen
     * Add the given FQSEN to the list of implemented
     * interfaces for this class
     *
     * @return null
     */
    public function addInterfaceClassFQSEN(FQSEN $fqsen) {
        $this->interface_fqsen_list[] = $fqsen;

        // Add the interface to the union type of this
        // class
        $this->getUnionType()->addUnionType(
            UnionType::fromFullyQualifiedString((string)$fqsen)
        );
    }

    /**
     * @return FQSEN[]
     * Get the list of interfaces implemented by this class
     */
    public function getInterfaceClassFQSENList() : array {
        return $this->interface_fqsen_list;
    }

    /**
     * @return void
     */
    public function addProperty(Property $property) {
        $this->property_map[$property->getName()] = $property;
    }

    /**
     * @return bool
     */
    public function hasPropertyWithName(string $name) : bool {
        return !empty($this->property_map[$name]);
    }

    /**
     * @param string $name
     * The name of the property
     *
     * @param Context $context
     * The context of the caller requesting the property
     *
     * @return Property
     * A property with the given name
     *
     * @throws AccessException
     * An exception may be thrown if the caller does not
     * have access to the given property from the given
     * context
     */
    public function getPropertyWithNameFromContext(
        string $name,
        Context $context
    ) : Property {
        $property = $this->property_map[$name];

        // If we're getting the property from outside of this
        // class and the property isn't public and we don't
        // have a getter or setter, emit an access error
        if ((!$context->hasClassFQSEN() || $context->getClassFQSEN() != $this->getFQSEN())
            && !$property->isPublic()
            && !$this->hasMethodWithName('__get')
            && !$this->hasMethodWithName('__set')
        ) {
            if ($property->isPrivate()) {
                throw new AccessException(
                    "Cannot access private property {$this->getFQSEN()}::\${$property->getName()}"
                );
            }
            if ($property->isProtected()) {
                throw new AccessException(
                    "Cannot access protected property {$this->getFQSEN()}::\${$property->getName()}"
                );
            }
        }

        return $property;
    }

    /**
     * @return Property[]
     * The list of properties on this class
     */
    public function getPropertyMap() : array {
        return $this->property_map;
    }

    /**
     * Add a class constant
     *
     * @return null;
     */
    public function addConstant(Constant $constant) {
        $this->constant_map[$constant->getName()] =
            $constant;
    }

    /**
     * @return bool
     * True if a constant with the given name is defined
     * on this class.
     */
    public function hasConstantWithName(string $name) : bool {
        return !empty($this->constant_map[$name]);
    }

    /**
     * @return Constant
     * The class constant with the given name.
     */
    public function getConstantWithName(string $name) : Constant {
        return $this->constant_map[$name];
    }

    /**
     * @return Constant[]
     * The constants associated with this class
     */
    public function getConstantMap() : array {
        return $this->constant_map;
    }

    /**
     * @return null
     */
    public function addMethod(Method $method) {
        $name = strtolower($method->getName());

        if (empty($this->method_map[$name])) {
            $this->method_map[$name] = $method;
        }
    }

    /**
     * @return bool
     * True if this class has a method with the given name
     */
    public function hasMethodWithName(string $name) : bool {
        $name = strtolower($name);

        // All classes have a constructor even if it hasn't
        // been declared yet
        if ('__construct' === $name) {
            return true;
        }

        return !empty($this->method_map[$name]);
    }

    /**
     * @return Method
     * The method with the given name
     */
    public function getMethodByName(string $name) : Method {
        $name = strtolower($name);

        if (!empty($this->method_map[$name])) {
            return $this->method_map[$name];
        }

        if ('__construct' === $name) {
            // Create a default constructor if its requested
            // but doesn't exist yet
            $default_constructor =
                Method::defaultConstructorForClassInContext(
                    $this,
                    $this->getContext()->withClassFQSEN(
                        $this->getFQSEN()
                    )
                );

            $this->addMethod($default_constructor);

            return $default_constructor;
        }

        // Error out
        return null;
    }

    /**
     * @return Method[]
     * A list of methods on this class
     */
    public function getMethodMap() : array {
        return $this->method_map;
    }

    /**
     * @return null
     */
    public function addTraitFQSEN(FQSEN $fqsen) {
        $this->trait_fqsen_list[] = $fqsen;

        // Add the trait to the union type of this class
        $this->getUnionType()->addUnionType(
            UnionType::fromFullyQualifiedString((string)$fqsen)
        );
    }

    /**
     * @return FQSEN[]
     * A list of FQSEN's for included traits
     */
    public function getTraitFQSENList() : array {
        return $this->trait_fqsen_list;
    }

    /**
     * @return bool
     * True if this class calls its parent constructor
     */
    public function getIsParentConstructorCalled() : bool {
        return $this->is_parent_constructor_called;
    }

    /**
     * @return null
     */
    public function setIsParentConstructorCalled(bool $is_parent_constructor_called) {
        $this->is_parent_constructor_called =
            $is_parent_constructor_called;
    }

    /**
     * @return bool
     * True if this is a final class
     */
    public function isFinal() : bool {
        return (bool)(
            $this->getFlags() & \ast\flags\CLASS_FINAL
        );
    }

    /**
     * @return bool
     * True if this is an abstract class
     */
    public function isAbstract() : bool {
        return (bool)(
            $this->getFlags() & \ast\flags\CLASS_ABSTRACT
        );
    }

    /**
     * @return bool
     * True if this is an interface
     */
    public function isInterface() : bool {
        return (bool)(
            $this->getFlags() & \ast\flags\CLASS_INTERFACE
        );
    }

    /**
     * @return bool
     * True if this class is a trait
     */
    public function isTrait() : bool {
        return (bool)(
            $this->getFlags() & \ast\flags\CLASS_TRAIT
        );
    }

    /**
     * @param UnionType $union_type
     * Set the type represented by this class
     *
     * @return null
     */
    public function setUnionType(UnionType $union_type) {
        // Set the class's type
        parent::setUnionType($union_type);

        // Propagate the type to the constructor
        if (!empty($this->method_map['__construct'])) {
            $method = $this->getMethodByName('__construct');
            $method->setUnionType($union_type);
        }

        // Propagate the type to the 'this' variable
        $variable = $this->getContext()->getScope()
            ->getVariableWithName('this');
        $variable->setUnionType($union_type);
    }

    public function setFQSEN(FQSEN $fqsen) {
        parent::setFQSEN($fqsen);

        // Propagate the type to the constructor
        if (!empty($this->method_map['__construct'])) {
            $method = $this->getMethodByName('__construct');
            $method->setFQSEN($fqsen);
        }

    }

    /**
     * @return FQSEN
     */
    public function getFQSEN() : FQSEN {
        return parent::getFQSEN()
            ->withClassName($this->getContext(), $this->getName());
    }

    /**
     * Add properties, constants and methods from all
     * ancestors (parents, traits, ...) to this class
     *
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return null
     */
    public function importAncestorClasses(CodeBase $code_base) {
        $this->memoize(__METHOD__, function() use ($code_base) {
            // Copy information from the traits into this class
            foreach ($this->getTraitFQSENList() as $trait_fqsen) {
                // Let the parent class finder worry about this
                if (!$code_base->hasClassWithFQSEN($trait_fqsen)) {
                    continue;
                }

                assert($code_base->hasClassWithFQSEN($trait_fqsen),
                    "Trait $trait_fqsen should already have been proven to exist");

                $this->importAncestorClass(
                    $code_base->getClassByFQSEN($trait_fqsen)
                );
            }

            // Copy information from the parent(s)
            $this->importParentClass($code_base);
        });
    }

    /*
     * Add properties, constants and methods from the
     * parent of this class
     *
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return null
     */
    public function importParentClass(CodeBase $code_base) {
        $this->memoize(__METHOD__, function() use ($code_base) {
            if (!$this->hasParentClassFQSEN()) {
                return;
            }

            // Let the parent class finder worry about this
            if (!$code_base->hasClassWithFQSEN(
                $this->getParentClassFQSEN()
            )) {
                return;
            }

            assert($code_base->hasClassWithFQSEN($this->getParentClassFQSEN()),
                "Clazz {$this->getParentClassFQSEN()} should already have been proven to exist from {$this->getContext()}");

            // Get the parent class
            $parent = $code_base->getClassByFQSEN(
                $this->getParentClassFQSEN()
            );

            // Tell the parent to import its own parents first
            $parent->importParentClass($code_base);

            // Import elements from the parent
            $this->importAncestorClass($parent);
        });
    }

    /**
     * Add properties, constants and methods from the given
     * class to this.
     *
     * @param Clazz $superclazz
     * A class to import from
     *
     * @return null
     */
    public function importAncestorClass(Clazz $superclazz) {
        $this->memoize((string)$superclazz->getFQSEN(),
            function() use ($superclazz) {
                // Copy properties
                foreach ($superclazz->getPropertyMap() as $property) {
                    $this->addProperty($property);
                }

                // Copy constants
                foreach ($superclazz->getConstantMap() as $constant) {
                    $this->addConstant($constant);
                }

                // Copy methods
                foreach ($superclazz->getMethodMap() as $method) {
                    $this->addMethod($method);
                }
            });
    }

    /**
     * @return Clazz[]
     * The set of all alternates to this class
     */
    public function alternateGenerator(CodeBase $code_base) : \Generator {
        $alternate_id = 0;
        $fqsen = $this->getFQSEN();
        while ($code_base->hasClassWithFQSEN($fqsen)) {
            yield $code_base->getClassByFQSEN($fqsen);
            $fqsen = $fqsen->withAlternateId(++$alternate_id);
        }
    }

    /**
     * @return string
     * A string describing this class
     */
    public function __toString() : string {
        $string = '';

        if ($this->isFinal()) {
            $string .= 'final ';
        }

        if ($this->isAbstract()) {
            $string .= 'abstract ';
        }

        if ($this->isInterface()) {
            $string .= 'Interface ';
        } else if ($this->isTrait()) {
            $string .= 'Trait ';
        } else {
            $string .= 'Class ';
        }

        $string .= (string)$this->getFQSEN()->getCanonicalFQSEN();

        return $string;
    }

    /**
     * @return Schema
     * The schema for this model
     */
    public static function createSchema() : Schema {
        $schema = new Schema('Clazz', [
            new Column('fqsen', 'STRING', true),
            new Column('name', 'STRING'),
            new Column('type', 'STRING'),
            new Column('flags', 'INT'),
            new Column('context', 'STRING'),
            new Column('comment', 'STRING'),
            new Column('is_deprecated', 'BOOL'),
            new Column('parent_class_fqsen', 'STRING'),
            new Column('is_parent_constructor_called', 'STRING'),
        ]);

        return $schema;
    }

    /**
     * @return array
     * Get a map from column name to row values for
     * this instance
     */
    public function toRow() : array {
        return array_merge(parent::toRow(), [
            'parent_class_fqsen' =>
                (string)$this->parent_class_fqsen,
            'is_parent_constructor_called' =>
                $this->is_parent_constructor_called,
        ]);
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return Model
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) : array {
        print_r($row);
    }

}
