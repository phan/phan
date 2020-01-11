<?php

declare(strict_types=1);

namespace Phan\Language\FQSEN;

/**
 * A Fully-Qualified Class Constant Name
 */
class FullyQualifiedClassConstantName extends FullyQualifiedClassElement implements FullyQualifiedConstantName
{
    /**
     * @return string
     * The canonical representation of the name of the object,
     * for use in array key lookups for singletons, namespace maps, etc.
     * This should not be used directly or indirectly in issue output
     * If an FQSEN is case-sensitive, this should return $name
     */
    public static function canonicalLookupKey(string $name): string
    {
        return $name;
    }
}
