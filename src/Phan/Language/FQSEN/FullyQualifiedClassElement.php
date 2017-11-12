<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use Phan\Language\Context;
use Phan\Language\FQSEN;

/**
 * A Fully-Qualified Class Name
 */
abstract class FullyQualifiedClassElement extends AbstractFQSEN
{
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
        $name = static::canonicalName($name);

        $key = self::toString($fully_qualified_class_name, $name, $alternate_id)
            . '|' . \get_called_class();

        return self::memoizeStatic($key, function () use (
            $fully_qualified_class_name,
            $name,
            $alternate_id
        ) {
            return new static(
                $fully_qualified_class_name,
                $name,
                $alternate_id
            );
        });
    }

    /**
     * @return static
     * Get the canonical (non-alternate) FQSEN associated
     * with this FQSEN
     */
    public function getCanonicalFQSEN() : FQSEN
    {
        $old_fully_qualified_class_name = $this->getFullyQualifiedClassName();
        $fully_qualified_class_name = $old_fully_qualified_class_name->getCanonicalFQSEN();
        if ($this->alternate_id == 0 && $fully_qualified_class_name === $old_fully_qualified_class_name) {
            return $this;
        }
        return static::make(
            $fully_qualified_class_name,
            $this->getName(),
            0
        );
    }

    /**
     * @param $fully_qualified_string
     * An FQSEN string like '\Namespace\Class::methodName'
     */
    public static function fromFullyQualifiedString(
        string $fully_qualified_string
    ) {
        \assert(
            false !== \strpos(
                $fully_qualified_string,
                '::'
            ),
            "Fully qualified class element lacks '::' delimiter"
        );

        list(
            $fully_qualified_class_name_string,
            $name_string
        ) = explode('::', $fully_qualified_string);

        $fully_qualified_class_name =
            FullyQualifiedClassName::fromFullyQualifiedString(
                $fully_qualified_class_name_string
            );

        // Make sure that we're actually getting a class
        // name reference back
        \assert(
            $fully_qualified_class_name instanceof FullyQualifiedClassName,
            "FQSEN must be an instanceof FullyQualifiedClassName"
        );

        // Split off the alternate ID
        $parts = explode(',', $name_string);
        $name = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        \assert(\is_int($alternate_id), "Alternate must be an integer");

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
     * @return static
     */
    public static function fromStringInContext(
        string $fqsen_string,
        Context $context
    ) {
        // Test to see if we have a class defined
        if (false === \strpos($fqsen_string, '::')) {
            \assert(
                $context->isInClassScope(),
                "Cannot reference class element without class name when not in class scope."
            );

            $fully_qualified_class_name = $context->getClassFQSEN();
        } else {
            \assert(
                false !== \strpos(
                    $fqsen_string,
                    '::'
                ),
                "Fully qualified class element lacks '::' delimiter"
            );

            list(
                $class_name_string,
                $fqsen_string
            ) = \explode('::', $fqsen_string);

            $fully_qualified_class_name =
                FullyQualifiedClassName::fromStringInContext(
                    $class_name_string,
                    $context
                );
        }

        // Split off the alternate ID
        $parts = \explode(',', $fqsen_string);
        $name = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        \assert(
            \is_int($alternate_id),
            "Alternate must be an integer"
        );

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
    public function getFullyQualifiedClassName() : FullyQualifiedClassName
    {
        return $this->fully_qualified_class_name;
    }

    /**
     * @return static
     * A new object with the given fully qualified
     * class name
     */
    public function withFullyQualifiedClassName(
        FullyQualifiedClassName $fully_qualified_class_name
    ) {
        return static::make(
            $fully_qualified_class_name,
            $this->getName(),
            $this->getAlternateId()
        );
    }

    /**
     * @return static
     * A FQSEN with the given alternate_id set
     */
    public function withAlternateId(
        int $alternate_id
    ) {

        \assert(
            $alternate_id < 1000,
            "Your alternate IDs have run away"
        );

        return static::make(
            $this->getFullyQualifiedClassName(),
            $this->getName(),
            $alternate_id
        );
    }

    /**
     * @return int
     * The alternate id for the class of the class element
     * TODO: Is it necessary to have both of these?
     */
    public function getAlternateIdForClassName() : int
    {
        return $this->getFullyQualifiedClassName()->getAlternateId();
    }

    /**
     * @return static
     * A FQSEN with the given alternate_id set on the class name
     * (E.g. MyClass,1::my_function for alternate_id 1)
     * TODO: Is it necessary to have both of these?
     */
    public function withAlternateIdForClassName(
        int $alternate_id
    ) {

        \assert(
            $alternate_id < 1000,
            "Your alternate IDs have run away"
        );

        return static::make(
            $this->getFullyQualifiedClassName()->withAlternateId($alternate_id),
            $this->getName(),
            $this->getAlternateId()
        );
    }

    /**
     * @return string
     * A string representation of the given values
     */
    public static function toString(
        FullyQualifiedClassName $fqsen,
        string $name,
        int $alternate_id
    ) : string {
        $fqsen_string = ((string)$fqsen) . '::' . $name;

        if ($alternate_id) {
            $fqsen_string .= ",$alternate_id";
        }

        return $fqsen_string;
    }

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    public function __toString() : string
    {
        $fqsen_string = $this->memoize(__METHOD__, function () {
            return self::toString(
                $this->getFullyQualifiedClassName(),
                $this->getName(),
                $this->alternate_id
            );
        });

        // print $fqsen_string . '|' . spl_object_hash($this) . "\n";

        return $fqsen_string;
    }
}
