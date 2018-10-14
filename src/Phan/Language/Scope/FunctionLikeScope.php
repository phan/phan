<?php declare(strict_types=1);
namespace Phan\Language\Scope;

use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;

/**
 * The scope of a function, method, or closure.
 * This has subclasses.
 */
class FunctionLikeScope extends ClosedScope
{
    /**
     * @return bool
     * True if we're in a class scope
     */
    public function isInClassScope() : bool
    {
        return $this->parent_scope->isInClassScope();
    }

    /**
     * @return FullyQualifiedClassName
     * Crawl the scope hierarchy to get a class FQSEN.
     */
    public function getClassFQSEN() : FullyQualifiedClassName
    {
        return $this->parent_scope->getClassFQSEN();
    }

    /**
     * @return ?FullyQualifiedClassName
     * Crawl the scope hierarchy to get a class FQSEN.
     * Return null if there is no class FQSEN.
     */
    public function getClassFQSENOrNull()
    {
        return $this->parent_scope->getClassFQSENOrNull();
    }

    /**
     * @return bool
     * True if we're in a function scope
     */
    public function isInFunctionLikeScope() : bool
    {
        return true;
    }

    /**
     * @return bool
     * True if we're in a function scope
     */
    public function isInPropertyScope() : bool
    {
        return false;
    }

    /**
     * @return FullyQualifiedMethodName|FullyQualifiedFunctionName
     * Get the FQSEN for the closure, method or function we're in
     */
    public function getFunctionLikeFQSEN()
    {
        $fqsen = $this->getFQSEN();

        if ($fqsen instanceof FullyQualifiedMethodName) {
            return $fqsen;
        }

        if ($fqsen instanceof FullyQualifiedFunctionName) {
            return $fqsen;
        }

        throw new \AssertionError("FQSEN must be a function-like FQSEN");
    }
}
