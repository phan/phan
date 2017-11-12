<?php declare(strict_types=1);
namespace Phan\Language\Scope;

use Phan\Language\FQSEN\FullyQualifiedPropertyName;

class PropertyScope extends ClosedScope
{

    /**
     * @return bool
     * True if we're in a property scope
     * @override
     */
    public function isInPropertyScope() : bool
    {
        return true;
    }

    /**
     * @return bool
     * True if we're in a class scope (True for properties)
     * @override
     */
    public function isInClassScope() : bool
    {
        return true;
    }

    /**
     * @return FullyQualifiedPropertyName
     * Get the FullyQualifiedPropertyName of the class who's scope
     * we're in.
     * @override
     */
    public function getPropertyFQSEN() : FullyQualifiedPropertyName
    {
        $fqsen = $this->getFQSEN();

        if ($fqsen instanceof FullyQualifiedPropertyName) {
            return $fqsen;
        }

        throw new \AssertionError("FQSEN must be a FullyQualifiedPropertyName");
    }
}
