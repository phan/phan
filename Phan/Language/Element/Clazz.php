<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\CodeBase;
use \Phan\Language\Context;
use \Phan\Language\Element\Comment;
use \Phan\Language\Element\Constant;
use \Phan\Language\Element\Method;
use \Phan\Language\Element\Property;
use \Phan\Language\FQSEN;
use \Phan\Language\Type;
use \Phan\Language\UnionType;

class Clazz extends TypedStructuralElement {

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
    ) {
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

        $context = new Context($code_base);

        // Build a base class element
        $clazz = new Clazz(
            $context,
            Comment::none(),
            $class->getName(),
            UnionType::fromString($class->getName()),
            $flags
        );

        // If this class has a parent class, add it to the
        // class info
        if(($parent_class = $class->getParentClass())) {
            $clazz->parent_class_name =
                $parent_class->getName();
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

        $clazz->interface_list =
            $class->getInterfaceNames();

        $clazz->trait_list =
            $class->getTraitNames();

        $parents = [];
        $temp = $class;
        while($parent = $temp->getParentClass()) {
            $parents[] = $parent->getName();
            $parents = array_merge($parents, $parent->getInterfaceNames());
            $temp = $parent;
        }

        $types = [$class->getName()];
        $types = array_merge($types, $clazz->interface_list);
        $types = array_merge($types, $parents);
        $clazz->type = implode('|', array_unique($types));

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

        foreach($class->getMethods() as $method) {
            $method_map =
                Method::mapFromReflectionClassAndMethod(
                    $context->withClassFQSEN($clazz->getFQSEN()),
                    $class,
                    $method,
                    $parents
                );

            $clazz->method_map = array_merge(
                $clazz->method_map,
                $method_map
            );
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
        $this->getUnionType()->addType(
            UnionType::fromString((string)$fqsen)
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
        $this->getUnionType()->addType(
            UnionType::fromString((string)$fqsen)
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
     * @return Property
     */
    public function getPropertyWithName(string $name) : Property {
        return $this->property_map[$name];
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
        $this->method_map[(string)$method->getFQSEN()] = $method;
    }

    /**
     *
     */
    public function hasMethodWithFQSEN(FQSEN $fqsen) : bool {
        return !empty($this->method_map[(string)$fqsen]);
    }

    /**
     * @return Method
     * The method with the given name
     */
    public function getMethodByFQSEN(FQSEN $fqsen) : Method {
        return $this->method_map[(string)$fqsen];
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
    public function addTraitFQSEN(FQSEN $trait) {
        $this->trait_fqsen_list[] = $trait;
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
     * @return FQSEN
     */
    public function getFQSEN() : FQSEN {
        return parent::getFQSEN()
            ->withClassName($this->getContext(), $this->getName());
    }
}
