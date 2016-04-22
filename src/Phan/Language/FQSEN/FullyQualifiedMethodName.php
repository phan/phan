<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use Phan\Language\Context;

/**
 * A Fully-Qualified Method Name
 */
class FullyQualifiedMethodName extends FullyQualifiedClassElement
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
}
