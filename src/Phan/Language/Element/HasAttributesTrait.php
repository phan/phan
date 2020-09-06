<?php

declare(strict_types=1);

namespace Phan\Language\Element;

/**
 * This contains functionality common to declarations that have attributes
 */
trait HasAttributesTrait
{
    /**
     * @var list<Attribute> the attributes associated with this function-like
     */
    protected $attribute_list = [];

    /**
     * Set the attributes associated with this function-like
     * @param list<Attribute> $attribute_list
     */
    public function setAttributeList(array $attribute_list): void
    {
        $this->attribute_list = $attribute_list;
    }

    /**
     * Get the attributes associated with this function-like
     * @return list<Attribute>
     */
    public function getAttributeList(): array
    {
        return $this->attribute_list;
    }
}
