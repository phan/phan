<?php

declare(strict_types=1);

namespace Phan\Language\FQSEN;

use AssertionError;
use InvalidArgumentException;
use Phan\Exception\FQSENException;
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
     * The fully qualified class name of the class in which
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
     * Construct a fully-qualified class element from the class,
     * the element name in the class.
     * (and an alternate id to account for duplicate element definitions)
     *
     * @param FullyQualifiedClassName $fully_qualified_class_name
     * The fully qualified class name of the class in which
     * this element exists
     *
     * @param string $name
     * A name if one is in scope or the empty string otherwise.
     *
     * @param int $alternate_id
     * An alternate ID for the element for use when
     * there are multiple definitions of the element
     *
     * @return static
     * @suppress PhanTypeInstantiateAbstractStatic this error is correct, but this should never be called directly
     */
    public static function make(
        FullyQualifiedClassName $fully_qualified_class_name,
        string $name,
        int $alternate_id = 0
    ) {
        $name = static::canonicalName($name);

        $key = $fully_qualified_class_name . '::' . $name . ',' . $alternate_id .
               '|' . static::class;

        static $cache = [];
        return $cache[$key] ?? ($cache[$key] = new static(
            $fully_qualified_class_name,
            $name,
            $alternate_id
        ));
    }

    /**
     * @return static
     * Get the canonical (non-alternate) FQSEN associated
     * with this FQSEN
     */
    public function getCanonicalFQSEN(): FQSEN
    {
        $fully_qualified_class_name = $this->fully_qualified_class_name->getCanonicalFQSEN();
        if (!$this->alternate_id && $fully_qualified_class_name === $this->fully_qualified_class_name) {
            return $this;
        }
        return static::make(
            $fully_qualified_class_name,  // @phan-suppress-current-line PhanPartialTypeMismatchArgument
            $this->name,
            0
        );
    }

    /**
     * @param $fully_qualified_string
     * An FQSEN string like '\Namespace\Class::methodName'
     *
     * @return static
     *
     * @throws InvalidArgumentException if the $fully_qualified_string doesn't have a '::' delimiter
     *
     * @throws FQSENException if the class or element FQSEN is invalid
     */
    public static function fromFullyQualifiedString(
        string $fully_qualified_string
    ) {
        $parts = \explode('::', $fully_qualified_string);
        if (\count($parts) !== 2) {
            throw new InvalidArgumentException("Fully qualified class element lacks '::' delimiter");
        }

        [
            $fully_qualified_class_name_string,
            $name_string
        ] = $parts;

        $fully_qualified_class_name =
            FullyQualifiedClassName::fromFullyQualifiedString(
                $fully_qualified_class_name_string
            );

        // Split off the alternate ID
        $parts = \explode(',', $name_string);
        $name = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        return static::make(
            $fully_qualified_class_name,
            $name,
            $alternate_id
        );
    }

    /**
     * @param string $fqsen_string
     * An FQSEN string like '\Namespace\Class::methodName'
     *
     * @param Context $context
     * The context in which the FQSEN string was found
     *
     * @return static
     *
     * @throws InvalidArgumentException if $fqsen_string is invalid in $context
     *
     * @throws FQSENException if $fqsen_string is invalid
     */
    public static function fromStringInContext(
        string $fqsen_string,
        Context $context
    ) {
        $parts = \explode('::', $fqsen_string);

        // Test to see if we have a class defined
        if (\count($parts) === 1) {
            if (!$context->isInClassScope()) {
                throw new InvalidArgumentException("Cannot reference class element without class name when not in class scope.");
            }

            $fully_qualified_class_name = $context->getClassFQSEN();
        } else {
            if (\count($parts) > 2) {
                throw new InvalidArgumentException("Too many '::' in $fqsen_string");
            }
            [
                $class_name_string,
                $fqsen_string
            ] = $parts;

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
    public function getFullyQualifiedClassName(): FullyQualifiedClassName
    {
        return $this->fully_qualified_class_name;
    }

    /**
     * @return static
     * A FQSEN with the given alternate_id set
     */
    public function withAlternateId(
        int $alternate_id
    ) {

        if ($alternate_id >= 1000) {
            throw new AssertionError("Your alternate IDs have run away");
        }

        return static::make(
            $this->fully_qualified_class_name,
            $this->name,
            $alternate_id
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
    ): string {
        $fqsen_string = $fqsen->__toString() . '::' . $name;

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
    public function __toString(): string
    {
        return $this->memoize(__METHOD__, function (): string {
            return self::toString(
                $this->fully_qualified_class_name,
                $this->name,
                $this->alternate_id
            );
        });
    }
}
