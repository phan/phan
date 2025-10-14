<?php

declare(strict_types=1);

namespace Phan\Language\Scope;

use Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Scope;

/**
 * Represents the Scope of the Context of a class's constant declaration.
 */
class ClassConstantScope extends ClosedScope
{
    public function __construct(
        Scope $parent_scope,
        FullyQualifiedClassConstantName $fqsen
    ) {
        $this->parent_scope = $parent_scope;
        $this->fqsen = $fqsen;
        $this->flags = $parent_scope->flags | Scope::IN_CLASS_CONSTANT_SCOPE;
    }

    /**
     * @return bool
     * True if we're in a class constant scope
     * @override
     */
    public function isInClassConstantScope(): bool
    {
        return true;
    }

    /**
     * @return bool
     * True if we're in a class scope (True for class constants)
     * @override
     */
    public function isInClassScope(): bool
    {
        return true;
    }

    /**
     * @return FullyQualifiedClassConstantName
     * Get the FullyQualifiedClassConstantName of the constant whose scope
     * we're in.
     * @override
     */
    public function getClassConstantFQSEN(): FullyQualifiedClassConstantName
    {
        if ($this->fqsen instanceof FullyQualifiedClassConstantName) {
            return $this->fqsen;
        }

        throw new \AssertionError("FQSEN must be a FullyQualifiedClassConstantName");
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
