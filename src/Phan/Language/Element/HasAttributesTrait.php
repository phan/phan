<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Config;

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

    /**
     * Check if this element has a #[Deprecated] attribute (PHP 8.4+)
     */
    public function hasDeprecatedAttribute(): bool
    {
        foreach ($this->attribute_list as $attribute) {
            $fqsen = $attribute->getFQSEN();
            // Check for both \Deprecated and Deprecated (in root namespace)
            if ($fqsen->__toString() === '\\Deprecated') {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if this element has a #[NoDiscard] attribute (PHP 8.5+)
     * This also works with polyfills on earlier PHP versions.
     */
    public function hasNoDiscardAttribute(): bool
    {
        foreach ($this->attribute_list as $attribute) {
            $fqsen = $attribute->getFQSEN();
            // Check for both \NoDiscard and NoDiscard (in root namespace)
            if ($fqsen->__toString() === '\\NoDiscard') {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if this element has a #[Override] attribute (PHP 8.3+ for methods, PHP 8.5+ for properties)
     */
    public function hasOverrideAttribute(): bool
    {
        $target_version = Config::get_closest_target_php_version_id();
        $minimum_version = ($this instanceof Property) ? 80500 : 80300;
        if ($target_version < $minimum_version) {
            return false;
        }
        foreach ($this->attribute_list as $attribute) {
            $fqsen = $attribute->getFQSEN();
            // Check for both \Override and Override (in root namespace)
            if ($fqsen->__toString() === '\\Override') {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if this element has a #[ReturnTypeWillChange] attribute (PHP 8.1+)
     * This attribute is frequently polyfilled, so version checks are not necessary.
     */
    public function hasReturnTypeWillChangeAttribute(): bool
    {
        foreach ($this->attribute_list as $attribute) {
            $fqsen = $attribute->getFQSEN();
            if ($fqsen->__toString() === '\\ReturnTypeWillChange') {
                return true;
            }
        }
        return false;
    }
}
