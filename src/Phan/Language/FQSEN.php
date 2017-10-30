<?php declare(strict_types=1);
namespace Phan\Language;

/**
 * A Fully-Qualified Name
 */
interface FQSEN
{

    /**
     * @param string $fully_qualified_string
     * An FQSEN string like '\Namespace\Class::method' or
     * 'Class' or 'Class::method'.
     *
     * @return static
     */
    public static function fromFullyQualifiedString(
        string $fully_qualified_string
    );

    /**
     * @param Context $context
     * The context in which the FQSEN string was found
     *
     * @param string $fqsen_string
     * An FQSEN string like '\Namespace\Class::method' or
     * 'Class' or 'Class::method'.
     *
     * @return static
     */
    public static function fromStringInContext(
        string $fqsen_string,
        Context $context
    );

    /**
     * @return string
     * The class associated with this FQSEN or
     * null if not defined
     */
    public function getName() : string;

    /**
     * @return string
     * The canonical representation of the name of the object. Functions
     * and Methods, for instance, lowercase their names.
     */
    public static function canonicalName(string $name) : string;

    /**
     * @return static
     * Get the canonical (non-alternate) FQSEN associated
     * with this FQSEN
     */
    public function getCanonicalFQSEN();

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    public function __toString() : string;
}
