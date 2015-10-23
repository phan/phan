<?php
declare(strict_types=1);
namespace phan\language\element;

require_once(__DIR__.'/Constant.php');
require_once(__DIR__.'/Method.php');
require_once(__DIR__.'/Property.php');
require_once(__DIR__.'/TypedStructuralElement.php');

/**
 * Static data defining type names for builtin classes
 */
$INTERNAL_CLASS_VARS =
    require(__DIR__.'/../constants/BuiltinClassTypes.php');

class Class extends TypedStructuralElement {

    /**
     * @var \phan\language\FQSEN
     */
    private $parent_class_fqsen = null;

    /**
     * @var \phan\language\FQSEN[]
     * A possibly empty list of interfaces implemented
     * by this class
     */
    private $interface_fqsen_list = [];

    /**
     * @var \phan\language\FQSEN[]
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
     * @param \phan\Context $context
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
        \phan\Context $context,
        CommentElement $comment
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
     * @return Class
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
     * @return Class
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

        // Build a base class element
        $class_element = new Class(
            \phan\language\Context::none(),
            Comment::none(),
            $class->getName(),
            new Type($class->getName()),
            $flags
        );

        // If this class has a parent class, add it to the
        // class info
        if(($parent_class = $class->getParentClass())) {
            $class_element->parent_class_name =
                $parent_class->getName();
        }

        foreach($class->getDefaultProperties() as $name => $value) {
            /*
            $property = Property::fromReflectionProperty(
                new \ReflectionProperty($class->getName(), $name);
            );
             */


            $type =
                $INTERNAL_CLASS_VARS[strtolower($class->getName())]['properties'][$name]
                ?? self::typeMap(gettype($value));

            $property =
                new \ReflectionProperty($class->getName(), $name);

            $class_element->property_map[strtolower($name)] =
                new PropertyElement(
                    'internal',
                    '',
                    0,
                    0,
                    '',
                    false,
                    $property->getModifiers(),
                    $name,
                    self::typeMap(gettype($value))
                );
        }

        $class_element->interface_list =
            $class->getInterfaceNames();

        $class_element->trait_list =
            $class->getTraitNames();

        $parents = [];
        $temp = $class;
        while($parent = $temp->getParentClass()) {
            $parents[] = $parent->getName();
            $parents = array_merge($parents, $parent->getInterfaceNames());
            $temp = $parent;
        }

        $types = [$class->getName()];
        $types = array_merge($types, $class_element->interface_list);
        $types = array_merge($types, $parents);
        $class_element->type = implode('|', array_unique($types));

        foreach($class->getConstants() as $name => $value) {
            $class_element->constant_map[$name] =
                new ConstantElement(
                    'internal',
                    '',
                    0,
                    0,
                    '',
                    false,
                    0,
                    $name,
                    self::typeMap(gettype($value))
                );
        }

        foreach($class->getMethods() as $method) {
            $class_element->method_map = array_merge(
                $class_element->method_map,
                MethodElement::mapFromReflectionClassAndMethod(
                    $class,
                    $method,
                    $parents
                )
            );
        }

        return $class_element;
    }
}
