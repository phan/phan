<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\Language\Context;
use \Phan\Language\Type;
use \Phan\Language\UnionType;

/**
 * A Fully-Qualified Name
 */
abstract class FQSEN {

    /**
     * @var string
     * The name of this structural element
     */
    private $name = '';

    /**
     * @param string $name
     * The name of this structural element
     */
    protected function __construct(string $name) {
        $this->name = $name;
    }

    /**
     * @param $fqsen_string
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
     * @param $fqsen_string
     * An FQSEN string like '\Namespace\Class::method' or
     * 'Class' or 'Class::method'.
     *
     * @return FQSEN
     */
    abstract public static function fromStringInContext(
        string $string,
        Context $context
    );

    /**
     * @return string
     * The class associated with this FQSEN or
     * null if not defined
     */
    public function getName() : string {
        return $this->name;
    }

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    abstract public function __toString() : string;
}
