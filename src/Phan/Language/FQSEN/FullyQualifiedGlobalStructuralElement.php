<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use AssertionError;
use InvalidArgumentException;
use Phan\Exception\EmptyFQSENException;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * A Fully-Qualified Global Structural Element
 */
abstract class FullyQualifiedGlobalStructuralElement extends AbstractFQSEN
{
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
     * An alternate ID for the element for use when
     * there are multiple definitions of the element
     *
     * @throws EmptyFQSENException
     * if the name component of this FullyQualifiedGlobalStructuralElement is empty
     *
     * @throws EmptyFQSENException
     * if the namespace of this FullyQualifiedGlobalStructuralElement is empty
     *
     * @throws InvalidArgumentException
     * if the namespace begins with an invalid character
     */
    protected function __construct(
        string $namespace,
        string $name,
        int $alternate_id = 0
    ) {
        if ($name === '') {
            throw new EmptyFQSENException("The name of an FQSEN cannot be empty", rtrim($namespace, '\\') . '\\');
        }

        if ($namespace === '') {
            throw new EmptyFQSENException("The namespace cannot be empty", $name);
        }

        if ($namespace[0] !== '\\') {
            throw new InvalidArgumentException("The first character of a namespace must be \\");
        }

        parent::__construct($name);
        $this->namespace = $namespace;
        $this->alternate_id = $alternate_id;
    }

    /**
     * Construct a fully-qualified global structural element from a namespace and name
     * (such as 'is_string', '\is_int', 'stdClass', 'PHP_VERSION_ID', 'ast\parse_code', etc.)
     *
     * @param string $name
     * The name of this structural element, may contain a namespace.
     *
     * @return static
     *
     * @deprecated - use fromFullyQualifiedString
     * @suppress PhanUnreferencedPublicMethod
     */
    public static function makeFromExtractedNamespaceAndName(string $name)
    {
        $name = \ltrim($name, '\\');
        $i = \stripos($name, '\\');
        if ($i === false) {
            // Common case: no namespace
            return self::make('\\', $name);
        }
        // @phan-suppress-next-line PhanPossiblyFalseTypeArgument
        return self::make('\\' . \substr($name, 0, $i), \substr($name, $i + 1));
    }

    /**
     * Construct a fully-qualified global structural element from a namespace and name.
     *
     * @param string $namespace
     * The namespace in this element's scope
     *
     * @param string $name
     * The name of this structural element (additional namespace prefixes here are properly handled)
     *
     * @param int $alternate_id
     * An alternate ID for the element for use when
     * there are multiple definitions of the element
     *
     * @return static
     */
    public static function make(
        string $namespace,
        string $name,
        int $alternate_id = 0
    ) {
        // Transfer any relative namespace stuff from the
        // name to the namespace.
        $name_parts = \explode('\\', $name);
        $name = (string)\array_pop($name_parts);
        foreach ($name_parts as $part) {
            // TODO: Emit a warning or throw instead?
            if ($part === '') {
                continue;
            }
            if ($namespace === '\\') {
                $namespace = '\\' . $part;
            } else {
                $namespace .= '\\' . $part;
            }
        }
        $namespace = self::cleanNamespace($namespace);

        // use the canonicalName for $name instead of strtolower - Some subclasses(constants) are case-sensitive.
        $key = static::class . '|' .
            static::toString(\strtolower($namespace), static::canonicalLookupKey($name), $alternate_id);

        $fqsen = self::memoizeStatic($key, /** @return FullyQualifiedGlobalStructuralElement */ function () use ($namespace, $name, $alternate_id) {
            return new static(
                $namespace,
                $name,
                $alternate_id
            );
        });

        return $fqsen;
    }

    /**
     * @param $fully_qualified_string
     * An fully qualified string like '\Namespace\Class'
     *
     * @return static
     *
     * @throws InvalidArgumentException on failure.
     */
    public static function fromFullyQualifiedString(
        string $fully_qualified_string
    ) {

        $key = static::class . '|' . $fully_qualified_string;

        return self::memoizeStatic(
            $key,
            /**
             * @return FullyQualifiedGlobalStructuralElement
             * @throws InvalidArgumentException
             */
            function () use ($fully_qualified_string) {
                // Split off the alternate_id
                $parts = \explode(',', $fully_qualified_string);
                $fqsen_string = $parts[0];
                $alternate_id = (int)($parts[1] ?? 0);

                $parts = \explode('\\', $fqsen_string);
                $name = (string)\array_pop($parts);

                if ($name === '') {
                    throw new InvalidArgumentException("The name cannot be empty");
                }

                $namespace = '\\' . \implode('\\', \array_filter($parts));

                if ($namespace === '') {
                    throw new InvalidArgumentException("The namespace cannot be empty");
                }

                if ($namespace[0] !== '\\') {
                    throw new InvalidArgumentException("The first character of the namespace must be \\");
                }

                return static::make(
                    $namespace,
                    $name,
                    $alternate_id
                );
            }
        );
    }

    /**
     * @param $fqsen_string
     * An FQSEN string like '\Namespace\Class'
     *
     * @param Context $context
     * The context in which the FQSEN string was found
     *
     * @return static
     *
     * @throws InvalidArgumentException if the $fqsen_string is invalid
     */
    public static function fromStringInContext(
        string $fqsen_string,
        Context $context
    ) {
        // Check to see if we're fully qualified
        if (($fqsen_string[0] ?? '') === '\\') {
            return static::fromFullyQualifiedString($fqsen_string);
        }
        $namespace_map_type = static::getNamespaceMapType();
        if ($namespace_map_type === \ast\AST_CONST && Type::fromReservedConstantName($fqsen_string)->isDefined()) {
            return static::fromFullyQualifiedString($fqsen_string);
        }

        // Split off the alternate ID
        $parts = \explode(',', $fqsen_string);
        $fqsen_string = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        $parts = \explode('\\', $fqsen_string);
        // Split the parts into the namespace(0 or more components) and the last name.
        $name = \array_pop($parts);

        if (!$name) {
            throw new InvalidArgumentException("The name cannot be empty");
        }

        // Check for a name map
        if ($context->hasNamespaceMapFor($namespace_map_type, $fqsen_string)) {
            return $context->getNamespaceMapFor(
                $namespace_map_type,
                $fqsen_string
            );
        }

        $namespace = \implode('\\', \array_filter($parts));

        // n.b.: Functions must override this method because
        //       they don't prefix the namespace for naked
        //       calls
        if (!$namespace) {
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
     * The namespace map type such as \ast\flags\USE_NORMAL or \ast\flags\USE_FUNCTION
     */
    abstract protected static function getNamespaceMapType() : int;

    /**
     * @return string
     * The namespace associated with this FQSEN
     */
    public function getNamespace() : string
    {
        return $this->namespace;
    }

    /**
     * @return string
     * The namespace+name associated with this FQSEN.
     * (e.g. 'ast\parse_code')
     */
    public function getNamespacedName() : string
    {
        if ($this->namespace === '\\') {
            return $this->getName();
        }
        return \ltrim($this->namespace, '\\') . '\\' . $this->getName();
    }

    /**
     * @return static a copy of this global structural element with a different namespace
     */
    public function withNamespace(
        string $namespace
    ) {
        return static::make(
            self::cleanNamespace($namespace),
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
        if ($this->getAlternateId() === $alternate_id) {
            return $this;
        }

        if ($alternate_id >= 1000) {
            throw new AssertionError("Your alternate IDs have run away");
        }

        return static::make(
            $this->getNamespace(),
            $this->getName(),
            $alternate_id
        );
    }

    /**
     * @param string $namespace (can be empty)
     *
     * @return string
     * A cleaned version of the given namespace such that
     * its always prefixed with a '\' and never ends in a
     * '\', and is the string "\" if there is no namespace.
     */
    protected static function cleanNamespace(string $namespace) : string
    {
        if ($namespace === ''
            || $namespace === '\\'
        ) {
            return '\\';
        }

        // Ensure that the first character of the namespace
        // is always a '\'
        if ($namespace[0] !== '\\') {
            $namespace = '\\' . $namespace;
        }

        // Ensure that we don't have a trailing '\' on the
        // namespace
        return \rtrim($namespace, '\\');
    }

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    public static function toString(
        string $namespace,
        string $name,
        int $alternate_id
    ) : string {
        $fqsen_string = $namespace;

        if ($fqsen_string && $fqsen_string !== '\\') {
            $fqsen_string .= '\\';
        }

        $fqsen_string .= static::canonicalName($name);

        // Append an alternate ID if we need to disambiguate
        // multiple definitions
        if ($alternate_id) {
            $fqsen_string .= ',' . $alternate_id;
        }

        return $fqsen_string;
    }

    /** @var string|null caches the value of $this->__toString() */
    private $as_string = null;

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    public function __toString() : string
    {
        $as_string = $this->as_string;
        if ($as_string === null) {
            $as_string = static::toString(
                $this->getNamespace(),
                $this->getName(),
                $this->getAlternateId()
            );
            $this->as_string = $as_string;
        }
        return $as_string;
    }
}
