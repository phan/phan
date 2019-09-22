<?php declare(strict_types=1);

namespace Phan\Language\FQSEN;

/**
 * A Fully-Qualified Function or Method Name
 */
interface FullyQualifiedFunctionLikeName
{
    /**
     * Returns true if this FQSEN represents a closure.
     */
    public function isClosure() : bool;

    /**
     * Returns the method name or (not fully-qualified) name of this function.
     */
    public function getName() : string;

    /**
     * Returns a string representation of this function or method signature.
     */
    public function __toString() : string;
}
