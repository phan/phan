<?php declare(strict_types=1);
namespace Phan\Language\Scope;

use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;

// TODO: Wrap this with a ClosureLikeScope
class FunctionLikeScope extends ClosedScope
{

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
