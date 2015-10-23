<?php
declare(strict_types=1);
namespace phan\element;

require_once(__DIR__.'/TypedStructuralElement.php');

class Property extends TypedStructuralElement {

    /**
     * @var $def
     * No idea
     */
    private $def;

    /**
     * @param string $file
     * The path to the file in which the class is defined
     *
     * @param string $namespace,
     * The namespace of the class
     *
     * @param int $line_number_start,
     * The starting line number of the class within the $file
     *
     * @param int $line_number_end,
     * The ending line number of the class within the $file
     *
     * @param string $comment,
     * Any comment block associated with the class
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
     * @param \phan\language\Context $context
     * The context in which the property appears
     *
     * @param \ReflectionProperty $reflection_property
     * The property to create a property structural element
     * from
     *
     * @return Property
     * Get a Property structural element from a ReflectionProperty
     * in a given context
     */
    public static function fromReflectionProperty(
        \phan\language\Context $context,
        \ReflectionProperty $reflection_property
    ) : Property {

        $type =
            $INTERNAL_CLASS_VARS[strtolower($class->getName())]['properties'][$name]
            ?? self::typeMap(gettype($value));


        $property = new Property(
            $context,
            Comment::none(),
            $name,
            new Type($type),
            $property->getModifiers()
        );

        return $property;
    }

}
