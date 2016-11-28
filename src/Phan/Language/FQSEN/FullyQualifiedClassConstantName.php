<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

/**
 * A Fully-Qualified Class Constant Name
 */
class FullyQualifiedClassConstantName extends FullyQualifiedClassElement implements FullyQualifiedConstantName
{

    /**
     * @return int
     * The namespace map type such as \ast\flags\USE_NORMAL or \ast\flags\USE_FUNCTION
     */
    protected static function getNamespaceMapType() : int
    {
        return \ast\flags\USE_CONST;
    }
}
