<?php declare(strict_types=1);
namespace Phan\Language\Scope;

use Phan\Language\FQSEN\FullyQualifiedClassName;

class ClassScope extends ClosedScope
{

    /**
     * @return bool
     * True if we're in a class scope
     * @override
     */
    public function isInClassScope() : bool
    {
        return true;
    }

    /**
     * @return bool
     * True if we're in a class scope
     * @override
     */
    public function isInPropertyScope() : bool
    {
        return false;
    }

    /**
     * @return FullyQualifiedClassName
     * Get the FullyQualifiedClassName of the class who's scope
     * we're in
     */
    public function getClassFQSEN() : FullyQualifiedClassName
    {
        $fqsen = $this->getFQSEN();

        if ($fqsen instanceof FullyQualifiedClassName) {
            return $fqsen;
        }

        throw new \AssertionError("FQSEN must be a FullyQualifiedClassName");
    }
}
