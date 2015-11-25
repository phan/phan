<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\Type;
use \Phan\Language\UnionType;

/**
 * A Fully-Qualified Global Structural Element
 */
abstract class FullyQualifiedGlobalStructuralElement extends FQSEN {
    use \Phan\Language\FQSEN\Alternatives;
    use \Phan\Memoize;

    /**
     * @var string
     * The namespace in this elements scope
     */
    private $namespace = '\\';

    /**
     * @param string $namespace
     * The namespace in this element's scope
     *
     * @param string $name
     * The name of this structural element
     *
     * @param int $alternate_id
     * An alternate ID for the elemnet for use when
     * there are multiple definitions of the element
     */
    protected function __construct(
        string $namespace,
        string $name,
        int $alternate_id = 0
    ) {
        parent::__construct($name);

        assert(!empty($name),
            "The name cannot be empty");

        assert(!empty($namespace),
            "The namespace cannot be empty");

        assert($namespace[0] === '\\',
            "The first character of a namespace must be \\, but got $namespace");

        $this->namespace = $namespace;
        $this->alternate_id = $alternate_id;
    }

    /**
     * @param string $namespace
     * The namespace in this element's scope
     *
     * @param string $name
     * The name of this structural element
     *
     * @param int $alternate_id
     * An alternate ID for the elemnet for use when
     * there are multiple definitions of the element
     */
    public static function make(
        string $namespace,
        string $name,
        int $alternate_id = 0
    ) {
        $key = implode('|', [
            get_called_class(),
            __FUNCTION__,
            $namespace,
            $name,
            $alternate_id,
        ]);

        return self::memoizeStatic($key, function() use (
            $namespace, $name, $alternate_id
        ) {
            return new static(
                self::cleanNamespace($namespace),
                $name,
                $alternate_id
            );
        });
    }

    /**
     * @param $fully_qualified_string
     * An fully qualified string like '\Namespace\Class'
     */
    public static function fromFullyQualifiedString(
        string $fully_qualified_string
    ) {
        // Split off the alternate_id
        $parts = explode(',', $fully_qualified_string);
        $fqsen_string = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        assert(is_int($alternate_id),
            "Alternate must be an integer in $fully_qualified_string");

        $parts = explode('\\', $fqsen_string);
        $name = array_pop($parts);

        assert(!empty($name),
            "The name cannot be empty in $fully_qualified_string");

        $namespace = '\\' . implode('\\', array_filter($parts));

        assert(!empty($namespace),
            "The namespace cannot be empty in $fully_qualified_string");

        assert($namespace[0] === '\\',
            "The first character of a namespace must be \\");

        return static::make(
            $namespace,
            $name,
            $alternate_id
        );
    }

    /**
     * @param Context $context
     * The context in which the FQSEN string was found
     *
     * @param $fqsen_string
     * An FQSEN string like '\Namespace\Class'
     */
    public static function fromStringInContext(
        string $fqsen_string,
        Context $context
    ) {

        // Check to see if we're fully qualified
        if (0 === strpos($fqsen_string, '\\')) {
            return self::fromFullyQualifiedString($fqsen_string);
        }

        // Split off the alternate ID
        $parts = explode(',', $fqsen_string);
        $fqsen_string = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        assert(is_int($alternate_id),
            "Alternate must be an integer in $fqsen_string");

        $parts = explode('\\', $fqsen_string);
        $name = array_pop($parts);

        assert(!empty($name),
            "The name cannot be empty in $fqsen_string");

        // Check for a name map
        if ($context->hasNamespaceMapFor(static::getNamespaceMapType(), $name)) {
            return $context->getNamespaceMapFor(
                static::getNamespaceMapType(),
                $name
            );
        }

        $namespace = implode('\\', array_filter($parts));

        // n.b.: Functions must override this method because
        //       they don't prefix the namespace for naked
        //       calls
        if (empty($namespace)) {
            $namespace = $context->getNamespace();
        }

        return static::make(
            $namespace,
            $name,
            $alternate_id
        );
    }

    /**
     * @return int
     * The namespace map type such as T_CLASS or T_FUNCTION
     */
    abstract protected static function getNamespaceMapType() : int;

    /**
     * @return string
     * The namespace associated with this FQSEN
     * or null if not defined
     */
    public function getNamespace() : string {
        return $this->namespace;
    }

    public function withNamespace(
        string $namespace
    ) : FullyQualifiedGlobalStructuralElement {
        $fqsen = clone($this);
        $fqsen->namespace = self::cleanNamespace($namespace);
        return $fqsen;
    }

    /**
     * @param string|null $namespace
     *
     * @return string
     * A cleaned version of the given namespace such that
     * its always prefixed with a '\' and never ends in a
     * '\', and is the string "\" if there is no namespace.
     */
    protected static function cleanNamespace(string $namespace) : string {
        if (!$namespace
            || empty($namespace)
            || $namespace === '\\'
        ) {
            return '\\';
        }

        // Ensure that the first character of the namespace
        // is always a '\'
        if (0 !== strpos($namespace, '\\')) {
            $namespace = '\\' . $namespace;
        }

        // Ensure that we don't have a trailing '\' on the
        // namespace
        if ('\\' === substr($namespace, -1)) {
            $namespace = substr($namespace, 0, -1);
        }

        return $namespace;
    }

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    public function __toString() : string {
        $fqsen_string = $this->getNamespace();

        if ($fqsen_string && $fqsen_string !== '\\') {
            $fqsen_string .= '\\';
        }

        $fqsen_string .= strtolower($this->getName());

        // Append an alternate ID if we need to disambiguate
        // multiple definitions
        if ($this->getAlternateId()) {
            $fqsen_string .= ',' . $this->getAlternateId();
        }

        return $fqsen_string;
    }

}
