<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

class GenericType extends Type
{
    /**
     * @var Type
     * The base type of this generic type referencing a
     * generic class
     */
    private $type;

    /**
     * @var UnionType[]
     * A map from a template type identifier to a
     * concrete union type
     */
    private $template_type_map = [];

    /**
     * @param Type $type
     * The base type of this generic type referencing a
     * generic class
     *
     * @param UnionType[] $template_type_map
     * A map from a template type identifier to a
     * concrete union type
     */
    public function __construct(
        Type $type,
        array $template_type_map
    ) {
        $this->type = $type;
        $this->template_type_map = $template_type_map;
    }

    /**
     * @return Type[]
     * A map from template type identifier to its
     * concrete type
     */
    public function getTemplateTypeMap() : array
    {
        return $this->template_type_map;
    }

    /**
     * @return string
     * The name associated with this type
     */
    public function getName() : string
    {
        return $this->type->getName();
    }

    /**
     * @return bool
     * True if this namespace is defined
     */
    public function hasNamespace() : bool
    {
        return $this->type->hasNamespace();
    }

    /**
     * @return string
     * The namespace associated with this type
     */
    public function getNamespace() : string
    {
        return $this->type->getNamespace();
    }

    /**
     * @return string
     * A human readable representation of this type
     */
    public function __toString()
    {
        $string = parent::__toString();

        $string .= '<';
        $string .= implode(',', array_values($this->template_type_map));
        $string .= '>';

        return $string;
    }

}
