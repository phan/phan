<?php

declare(strict_types=1);

namespace Phan\Language\Scope;

use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope;

/**
 * Represents the Scope of the Context of a class's property declaration.
 */
class PropertyScope extends ClosedScope
{
    public function __construct(
        Scope $parent_scope,
        FullyQualifiedPropertyName $fqsen
    ) {
        $this->parent_scope = $parent_scope;
        $this->fqsen = $fqsen;
        $this->flags = $parent_scope->flags;
    }

    /**
     * @return bool
     * True if we're in a property scope
     * @override
     */
    public function isInPropertyScope(): bool
    {
        return true;
    }

    /**
     * @return bool
     * True if we're in a class scope (True for properties)
     * @override
     */
    public function isInClassScope(): bool
    {
        return true;
    }

    /**
     * @return FullyQualifiedPropertyName
     * Get the FullyQualifiedPropertyName of the class whose scope
     * we're in.
     * @override
     */
    public function getPropertyFQSEN(): FullyQualifiedPropertyName
    {
        if ($this->fqsen instanceof FullyQualifiedPropertyName) {
            return $this->fqsen;
        }

        throw new \AssertionError("FQSEN must be a FullyQualifiedPropertyName");
    }

    /**
     * @return FullyQualifiedClassName
     * Crawl the scope hierarchy to get a class FQSEN.
     */
    public function getClassFQSEN(): FullyQualifiedClassName
    {
        return $this->parent_scope->getClassFQSEN();
    }

    /**
     * @return ?FullyQualifiedClassName
     * Crawl the scope hierarchy to get a class FQSEN.
     * Return null if there is no class FQSEN.
     */
    public function getClassFQSENOrNull(): ?FullyQualifiedClassName
    {
        return $this->parent_scope->getClassFQSENOrNull();
    }
}
