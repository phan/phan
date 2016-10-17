<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

/**
 * A Fully-Qualified Method Name
 */
class FullyQualifiedMethodName extends FullyQualifiedClassElement
    implements FullyQualifiedFunctionLikeName
{

    /**
     * @return string
     * The canonical representation of the name of the object. Functions
     * and Methods, for instance, lowercase their names.
     */
    public static function canonicalName(string $name) : string
    {
        return $name;
    }

    /**
     * @return bool
     * True if this FQSEN represents a closure
     */
    public function isClosure() : bool {
        return false;
    }

}
