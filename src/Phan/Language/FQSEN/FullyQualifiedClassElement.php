<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use \Phan\Language\Context;
use \Phan\Language\FQSEN;

/**
 * A Fully-Qualified Class Name
 */
abstract class FullyQualifiedClassElement extends FQSEN {
    use \Phan\Language\FQSEN\Alternatives;
    use \Phan\Memoize;

    /**
     * @var FullyQualifiedClassName
     * A fully qualified class name for the class in
     * which this element exists
     */
    private $fully_qualified_class_name;

    /**
     * @param FullyQualifiedClassName $fully_qualified_class_name
     * The fully qualified class name of the class in whic
     * this element exists
     *
     * @param string $name
     * A name if one is in scope or the empty string otherwise.
     *
     * @param int $alternate_id
     * An alternate ID for the element for use when
     * there are multiple definitions of the element
     */
    protected function __construct(
        FullyQualifiedClassName $fully_qualified_class_name,
        string $name,
        int $alternate_id = 0
    ) {
        parent::__construct($name);

        $this->fully_qualified_class_name =
            $fully_qualified_class_name;
        $this->alternate_id = $alternate_id;
    }

    /**
     * @param FullyQualifiedClassName $fully_qualified_class_name
     * The fully qualified class name of the class in whic
     * this element exists
     *
     * @param string $name
     * A name if one is in scope or the empty string otherwise.
     *
     * @param int $alternate_id
     * An alternate ID for the element for use when
     * there are multiple definitions of the element
     */
    public static function make(
        FullyQualifiedClassName $fully_qualified_class_name,
        string $name,
        int $alternate_id = 0
    ) {
        $key = implode('|', [
            get_called_class(),
            __FUNCTION__,
            (string)$fully_qualified_class_name,
            $name,
            $alternate_id
        ]);

        return self::memoizeStatic($key, function() use (
            $fully_qualified_class_name,
            $name,
            $alternate_id
        ) {
            return new static(
                $fully_qualified_class_name,
                static::canonicalName($name),
                $alternate_id
            );
        });
    }

    /**
     * @return string
     * The canonical representation of the name of the object. Functions
     * and Methods, for instance, lowercase their names.
     */
    public static function canonicalName(string $name) : string {
        return $name;
    }

    /**
     * @param $fully_qualified_string
     * An FQSEN string like '\Namespace\Class::methodName'
     */
    public static function fromFullyQualifiedString(
        string $fully_qualified_string
    ) {
        assert(false !== strpos($fully_qualified_string, '::'),
            "Fully qualified class element lacks '::' delimiter in $fully_qualified_string.");

        list(
            $fully_qualified_class_name_string,
            $name_string
        ) = explode('::', $fully_qualified_string);

        $fully_qualified_class_name =
            FullyQualifiedClassName::fromFullyQualifiedString(
                $fully_qualified_class_name_string
            );

        // Split off the alternate ID
        $parts = explode(',', $name_string);
        $name = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        assert(is_int($alternate_id),
            "Alternate must be an integer in $fully_qualified_string");

        return static::make(
            $fully_qualified_class_name,
            $name,
            $alternate_id
        );
    }

    /**
     * @param Context $context
     * The context in which the FQSEN string was found
     *
     * @param $fqsen_string
     * An FQSEN string like '\Namespace\Class::methodName'
     *
     * @return FullyQualifiedMethodName
     */
    public static function fromStringInContext(
        string $fqsen_string,
        Context $context
    ) {
        // Test to see if we have a class defined
        if (false === strpos($fqsen_string, '::')) {
            $fully_qualified_class_name = $context->getClassFQSEN();
        } else {
            assert(false !== strpos($fqsen_string, '::'),
                "Fully qualified class element lacks '::' delimiter in $fqsen_string.");

            list(
                $class_name_string,
                $fqsen_string
            ) = explode('::', $fqsen_string);

            $fully_qualified_class_name =
                FullyQualifiedClassName::fromStringInContext(
                    $class_name_string, $context
                );
        }

        // Split off the alternate ID
        $parts = explode(',', $fqsen_string);
        $name = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        assert(is_int($alternate_id),
            "Alternate must be an integer in $fqsen_string");

        return static::make(
            $fully_qualified_class_name,
            $name,
            $alternate_id
        );
    }

    /**
     * @return FullyQualifiedClassName
     * The fully qualified class name associated with this
     * class element.
     */
    public function getFullyQualifiedClassName(
    ) : FullyQualifiedClassName {
        return $this->fully_qualified_class_name;
    }

    /**
     * @return static
     * A clone of this object with the given fully qualified
     * class name
     */
    public function withFullyQualifiedClassName(
        FullyQualifiedClassName $fully_qualified_class_name
    ) {
        $fqsen = clone($this);
        $fqsen->fully_qualified_class_name =
            $fully_qualified_class_name;
        return $fqsen;
    }

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    public function __toString() : string {
        $fqsen_string = (string)$this->getFullyQualifiedClassName();
        $fqsen_string .= '::' . $this->getName();

        if ($this->alternate_id) {
            $fqsen_string .= ",{$this->alternate_id}";
        }

        return $fqsen_string;
    }

}
