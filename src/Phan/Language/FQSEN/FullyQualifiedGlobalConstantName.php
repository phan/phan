<?php declare(strict_types=1);

namespace Phan\Language\FQSEN;

/**
 * A Fully-Qualified Constant Name
 */
class FullyQualifiedGlobalConstantName extends FullyQualifiedGlobalStructuralElement implements FullyQualifiedConstantName
{
    /**
     * @return int
     * The namespace map type such as \ast\flags\USE_NORMAL or \ast\flags\USE_FUNCTION
     */
    protected static function getNamespaceMapType() : int
    {
        return \ast\flags\USE_CONST;
    }

    /**
     * @return string
     * The canonical representation of the name of the object,
     * for use in array key lookups for singletons, namespace maps, etc.
     * This should not be used directly or indirectly in issue output
     * If an FQSEN is case-sensitive, this should return $name
     */
    public static function canonicalLookupKey(string $name) : string
    {
        return $name;
    }
}
