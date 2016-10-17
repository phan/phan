<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

/**
 * A Fully-Qualified Method Name
 */
interface FullyQualifiedFunctionLikeName
{
    /**
     * @return bool
     * True if this FQSEN represents a closure
     */
    public function isClosure() : bool;
}
