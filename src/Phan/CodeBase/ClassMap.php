<?php

declare(strict_types=1);

namespace Phan\CodeBase;

use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;

/**
 * Maps for elements associated with an individual class
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
class ClassMap
{
    /**
     * @var array<string,ClassConstant>
     * A map from name to ClassConstant
     */
    private $class_constant_map = [];

    /**
     * @var array<string,Property>
     * A map from name to Property
     */
    private $property_map = [];

    /**
     * @var array<string,Method>
     * A map from name to Method
     */
    private $method_map = [];

    /**
     * Record that the class this represents has the provided class constant.
     */
    public function addClassConstant(ClassConstant $constant): void
    {
        $this->class_constant_map[
            $constant->getFQSEN()->getNameWithAlternateId()
        ] = $constant;
    }

    /**
     * @return bool does this class have a constant with name $name
     */
    public function hasClassConstantWithName(string $name): bool
    {
        return isset($this->class_constant_map[$name]);
    }

    /**
     * Gets the class constant $name of this class
     */
    public function getClassConstantByName(string $name): ClassConstant
    {
        return $this->class_constant_map[$name];
    }

    /**
     * @return array<string,ClassConstant>
     */
    public function getClassConstantMap(): array
    {
        return $this->class_constant_map;
    }

    /**
     * Record that the class this represents has the provided property information
     */
    public function addProperty(Property $property): void
    {
        $this->property_map[
            $property->getFQSEN()->getNameWithAlternateId()
        ] = $property;
    }

    /**
     * Checks if the class this represents has a property with name $name
     */
    public function hasPropertyWithName(string $name): bool
    {
        return isset($this->property_map[$name]);
    }

    /**
     * Fetch information about the property (of the class this represents) with name $name
     */
    public function getPropertyByName(string $name): Property
    {
        return $this->property_map[$name];
    }

    /**
     * @return array<string,Property>
     */
    public function getPropertyMap(): array
    {
        return $this->property_map;
    }

    /**
     * Records that the class that this represents has the provided method.
     */
    public function addMethod(Method $method): void
    {
        $this->method_map[\strtolower(
            $method->getFQSEN()->getNameWithAlternateId()
        )] = $method;
    }

    /**
     * Checks if the class that this represents has a method with name $name.
     */
    public function hasMethodWithName(string $name): bool
    {
        return isset($this->method_map[\strtolower($name)]);
    }

    /**
     * Fetches the method signature with name $name of the class that this represents.
     */
    public function getMethodByName(string $name): Method
    {
        return $this->method_map[\strtolower($name)];
    }

    /**
     * Fetches the method with the lowercase name $lowercase_name (including any alternate id suffix), if it exists.
     */
    public function getMethodByLowercaseName(string $lowercase_name): ?Method
    {
        return $this->method_map[$lowercase_name] ?? null;
    }

    /**
     * @return array<string,Method>
     */
    public function getMethodMap(): array
    {
        return $this->method_map;
    }

    // The entry API below is used by Phan's own code that must not cause the creation of method copies.
    // Every entry is a Method today. A later change adds lazy entries for inherited methods (the copy is
    // created on the first lookup by name); the entry API never creates those copies.

    /**
     * Returns the entry for the lowercase method name $lowercase_name (including any alternate id suffix), if any.
     * This never materializes a lazy entry.
     *
     * @internal
     */
    public function entry(string $lowercase_name): ?Method
    {
        return $this->method_map[$lowercase_name] ?? null;
    }

    /**
     * Returns the entries of this class's methods, by lowercase name (including any alternate id suffix),
     * in insertion order. This never materializes lazy entries.
     *
     * @return array<string,Method>
     * @internal
     */
    public function getMethodEntryMap(): array
    {
        return $this->method_map;
    }

    /**
     * Returns the lowercase names (including any alternate id suffix) of this class's methods, in insertion order.
     * This never materializes lazy entries.
     *
     * @return list<string>
     * @internal
     */
    public function getMethodNames(): array
    {
        return \array_keys($this->method_map);
    }

    /**
     * Returns whether any entry of this map is a lazy entry (always false until lazy entries are added).
     *
     * @internal
     */
    public function hasLazyEntries(): bool
    {
        return false;
    }
}
