<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\Language\Context;
use \Phan\Language\Type;
use \Phan\Language\UnionType;

/**
 * A Fully-Qualified Name
 */
abstract class FQSEN {

    protected function __construct() {}

    /**
     * @param $fqsen_string
     * An FQSEN string like '\Namespace\Class::method' or
     * 'Class' or 'Class::method'.
     *
     * @return FQSEN2
     */
    abstract public static function fromFullyQualifiedString(
        string $fully_qualified_string
    );

    /**
     * @param Context $context
     * The context in which the FQSEN string was found
     *
     * @param $fqsen_string
     * An FQSEN string like '\Namespace\Class::method' or
     * 'Class' or 'Class::method'.
     *
     * @return FQSEN2
     */
    abstract public static function fromStringInContext(
        string $string,
        Context $context
    );

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    abstract public function __toString() : string;
}
