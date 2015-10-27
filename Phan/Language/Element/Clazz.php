<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\Element\Comment;
use \Phan\Language\FQSEN;
use \Phan\Language\Type;

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
    private $constant_map;

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
     * @param Type $type,
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
        Type $type,
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
     * @param string $class_name
     * The name of a builtin class to build a new Class structural
     * element from.
     *
     * @return Clazz
     * A Class structural element representing the given named
     * builtin.
     */
    public static function fromClassName(string $class_name) {
        return self::fromReflectionClass(
            new \ReflectionClass($class_name)
        );
    }

    /**
     * @param ReflectionClass $class
     * A reflection class representing a builtin class.
     *
     * @return Clazz
     * A Class structural element representing the given named
     * builtin.
     */
    public static function fromReflectionClass(
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

        $context = new Context();

        // Build a base class element
        $clazz = new Clazz(
            $context,
            Comment::none(),
            $class->getName(),
            new Type([$class->getName()]),
            $flags
        );

        // If this class has a parent class, add it to the
        // class info
        if(($parent_class = $class->getParentClass())) {
            $clazz->parent_class_name =
                $parent_class->getName();
        }

        foreach($class->getDefaultProperties() as $name => $value) {
            /*
            $property = Property::fromReflectionProperty(
                new \ReflectionProperty($class->getName(), $name);
            );
             */


            /*
            $type =
                $INTERNAL_CLASS_VARS[strtolower($class->getName())]['properties'][$name]
                ?? self::typeMap(gettype($value));
             */

            $property =
                new \ReflectionProperty($class->getName(), $name);

            $property_element =
                new Property(
                    $context->withClassFQSEN($clazz->getFQSEN()),
                    Comment::none(),
                    $name,
                    Type::typeForObject($value),
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
                    Type::typeForObject($value),
                    0
                );
        }

        foreach($class->getMethods() as $method) {
            $clazz->method_map = array_merge(
                $clazz->method_map,
                Method::mapFromReflectionClassAndMethod(
                    $context->withClassFQSEN($clazz->getFQSEN()),
                    $class,
                    $method,
                    $parents
                )
            );
        }

        return $clazz;
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
     * @return null
     */
    public function addMethod(Method $method) {
        $this->method_map[$method->getFQSEN()->__toString()] = $method;
    }

    /**
     *
     */
    public function hasMethodWithFQSEN(FQSEN $fqsen) : bool {
        return !empty($this->method_map[$fqsen->__toString()]);
    }

    /**
     *
     */
    public function getMethodWithName(string $method_name) : Method {
        return $this->method_map[$fqsen->__toString()];
    }

    /**
     * @return null
     */
    public function addTraitFQSEN(FQSEN $trait) {
        $this->trait_fqsen_list[] = $trait;
    }

    /**
     * @return FQSEN
     */
    public function getFQSEN() : FQSEN {
        return FQSEN::fromContext(
            $this->getContext()
        )->withClassName($this->getName());
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
}
