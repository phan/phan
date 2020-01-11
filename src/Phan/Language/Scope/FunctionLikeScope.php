<?php

declare(strict_types=1);

namespace Phan\Language\Scope;

use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Scope;

/**
 * The scope of a function, method, or closure.
 * This has subclasses.
 */
class FunctionLikeScope extends ClosedScope
{
    public function __construct(
        Scope $parent_scope,
        FQSEN $fqsen
    ) {
        $this->parent_scope = $parent_scope;
        $this->fqsen = $fqsen;
        $this->flags = $parent_scope->flags | Scope::IN_FUNCTION_LIKE_SCOPE;
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

    /**
     * @return bool
     * True if we're in a function scope
     */
    public function isInFunctionLikeScope(): bool
    {
        return true;
    }

    /**
     * @return bool
     * True if we're in a function scope
     */
    public function isInPropertyScope(): bool
    {
        return false;
    }

    /**
     * @return bool
     * True if we're in a class-like scope
     */
    public function isInMethodLikeScope(): bool
    {
        return ($this->flags & self::IN_CLASS_LIKE_SCOPE) !== 0;
    }

    /**
     * @return FullyQualifiedMethodName|FullyQualifiedFunctionName
     * Get the FQSEN for the closure, method or function we're in
     */
    public function getFunctionLikeFQSEN()
    {
        $fqsen = $this->fqsen;

        if ($fqsen instanceof FullyQualifiedMethodName) {
            return $fqsen;
        }

        if ($fqsen instanceof FullyQualifiedFunctionName) {
            return $fqsen;
        }

        throw new \AssertionError("FQSEN must be a function-like FQSEN");
    }
}
