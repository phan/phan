<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use Phan\Language\Context;
use Phan\Language\FQSEN;

/**
 * A Fully-Qualified Name
 */
abstract class AbstractFQSEN implements FQSEN
{

    /**
     * @var string
     * The name of this structural element
     */
    private $name = '';

    /**
     * @param string $name
     * The name of this structural element
     */
    protected function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param string $fully_qualified_string
     * An FQSEN string like '\Namespace\Class::method' or
     * 'Class' or 'Class::method'.
     *
     * @return FQSEN
     */
    abstract public static function fromFullyQualifiedString(
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
     * @return FQSEN
     */
    abstract public static function fromStringInContext(
        string $fqsen_string,
        Context $context
    );

    /**
     * @return string
     * The class associated with this FQSEN
     */
    public function getName() : string
    {
        return $this->name;
    }

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
     * @return string
     * The canonical representation of the name of the object,
     * for use in array key lookups for singletons, namespace maps, etc.
     * This should not be used directly or indirectly in issue output
     * If an FQSEN is case sensitive, this should return $name
     */
    public static function canonicalLookupKey(string $name) : string
    {
        return \strtolower($name);
    }

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    abstract public function __toString() : string;
}
