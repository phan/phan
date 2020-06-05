<?php

declare(strict_types=1);

namespace Phan\Language\FQSEN;

use AssertionError;
use Exception;
use Phan\Exception\EmptyFQSENException;
use Phan\Exception\FQSENException;
use Phan\Exception\InvalidFQSENException;
use Phan\Language\Context;

use function array_slice;

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
     * @readonly
     */
    protected $namespace = '\\';

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
     * @throws InvalidFQSENException
     * if the namespace begins with an invalid character
     */
    protected function __construct(
        string $namespace,
        string $name,
        int $alternate_id = 0
    ) {
        if ($name === '') {
            throw new EmptyFQSENException("The name of an FQSEN cannot be empty", \rtrim($namespace, '\\') . '\\');
        }

        if ($namespace === '') {
            throw new EmptyFQSENException("The namespace cannot be empty", $name);
        }

        if ($namespace[0] !== '\\') {
            throw new InvalidFQSENException("The first character of a namespace must be \\", $namespace . "\\" . $name);
        }

        parent::__construct($name);
        $this->namespace = $namespace;
        $this->alternate_id = $alternate_id;
    }

    /** @internal */
    public const VALID_STRUCTURAL_ELEMENT_REGEX = '/^\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*$/D';
    /** @internal */
    public const VALID_STRUCTURAL_ELEMENT_REGEX_PART = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/D';

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
     *
     * @throws FQSENException on invalid/empty FQSEN
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
        if ($name === '') {
            throw new EmptyFQSENException(
                "Empty name of fqsen",
                \rtrim($namespace, "\\") . "\\" . \implode("\\", \array_merge($name_parts, [$name]))
            );
        }
        foreach ($name_parts as $i => $part) {
            if ($part === '') {
                if ($i > 0) {
                    throw new InvalidFQSENException(
                        "Invalid part '' of fqsen",
                        \rtrim($namespace, "\\") . "\\" . \implode('\\', \array_merge(array_slice($name_parts, $i), [$name]))
                    );
                }
                continue;
            }
            if (!\preg_match(self::VALID_STRUCTURAL_ELEMENT_REGEX_PART, $part)) {
                throw new InvalidFQSENException(
                    "Invalid part '$part' of fqsen",
                    \rtrim($namespace, "\\") . "\\$part\\" . \implode('\\', \array_merge(array_slice($name_parts, $i), [$name]))
                );
            }
            if ($namespace === '\\') {
                $namespace = '\\' . $part;
            } else {
                $namespace .= '\\' . $part;
            }
        }
        $namespace = self::cleanNamespace($namespace);
        if (!\preg_match(self::VALID_STRUCTURAL_ELEMENT_REGEX, \rtrim($namespace, '\\') . '\\' . $name)) {
            throw new InvalidFQSENException("Invalid namespaced name", \rtrim($namespace, '\\') . '\\' . $name);
        }

        // use the canonicalName for $name instead of strtolower - Some subclasses(constants) are case-sensitive.
        $key = static::class . '|' .
            static::toString(\strtolower($namespace), static::canonicalLookupKey($name), $alternate_id);

        $fqsen = self::memoizeStatic($key, static function () use ($namespace, $name, $alternate_id): FullyQualifiedGlobalStructuralElement {
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
     * @throws FQSENException on failure.
     */
    public static function fromFullyQualifiedString(
        string $fully_qualified_string
    ) {

        $key = static::class . '|' . $fully_qualified_string;

        return self::memoizeStatic(
            $key,
            /**
             * @throws FQSENException
             */
            static function () use ($fully_qualified_string): FullyQualifiedGlobalStructuralElement {
                // Split off the alternate_id
                $parts = \explode(',', $fully_qualified_string);
                $fqsen_string = $parts[0];
                $alternate_id = (int)($parts[1] ?? 0);

                $parts = \explode('\\', $fqsen_string);
                if ($parts[0] === '') {
                    \array_shift($parts);
                    if (\count($parts) === 0) {
                        throw new EmptyFQSENException("The name cannot be empty", $fqsen_string);
                    }
                }
                $name = (string)\array_pop($parts);

                if ($name === '') {
                    throw new EmptyFQSENException("The name cannot be empty", $fqsen_string);
                }

                $namespace = '\\' . \implode('\\', $parts);
                if ($namespace !== '\\') {
                    if (!\preg_match(self::VALID_STRUCTURAL_ELEMENT_REGEX, $namespace)) {
                        throw new InvalidFQSENException("The namespace $namespace is invalid", $fqsen_string);
                    }
                } elseif (\count($parts) > 0) {
                    // E.g. from `\\stdClass` with two backslashes
                    throw new InvalidFQSENException("The namespace cannot have empty parts", $fqsen_string);
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
     * Construct a fully-qualified global structural element from a namespace and name,
     * if it was already constructed.
     *
     * @param string $namespace
     * The namespace in this element's scope
     *
     * @param string $name
     * The name of this structural element (additional namespace prefixes here are properly handled)
     *
     * @return ?static the FQSEN, if it was loaded
     *
     * @throws FQSENException on failure.
     */
    public static function makeIfLoaded(string $namespace, string $name)
    {
        $name_parts = \explode('\\', $name);
        $name = (string)\array_pop($name_parts);
        if ($name === '') {
            throw new EmptyFQSENException(
                "Empty name of fqsen",
                \rtrim($namespace, "\\") . "\\" . \implode("\\", \array_merge($name_parts, [$name]))
            );
        }
        foreach ($name_parts as $i => $part) {
            if ($part === '') {
                if ($i > 0) {
                    throw new InvalidFQSENException(
                        "Invalid part '' of fqsen",
                        \rtrim($namespace, "\\") . "\\" . \implode('\\', \array_merge(array_slice($name_parts, $i), [$name]))
                    );
                }
                continue;
            }
            if (!\preg_match(self::VALID_STRUCTURAL_ELEMENT_REGEX_PART, $part)) {
                throw new InvalidFQSENException(
                    "Invalid part '$part' of fqsen",
                    \rtrim($namespace, "\\") . "\\$part\\" . \implode('\\', \array_merge(array_slice($name_parts, $i), [$name]))
                );
            }
            if ($namespace === '\\') {
                $namespace = '\\' . $part;
            } else {
                $namespace .= '\\' . $part;
            }
        }
        $namespace = self::cleanNamespace($namespace);
        if (!\preg_match(self::VALID_STRUCTURAL_ELEMENT_REGEX, \rtrim($namespace, '\\') . '\\' . $name)) {
            throw new InvalidFQSENException("Invalid namespaced name", \rtrim($namespace, '\\') . '\\' . $name);
        }
        $key = static::class . '|' .
            static::toString(\strtolower($namespace), static::canonicalLookupKey($name), 0);

        try {
            return self::memoizeStatic(
                $key,
                /**
                 * @throws FQSENException
                 */
                static function (): self {
                    // Reuse the exception to save time generating an unused stack trace.
                    static $exception;
                    $exception = ($exception ?? new Exception());
                    throw $exception;
                }
            );
        } catch (\Exception $_) {
            return null;
        }
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
     * @throws FQSENException if the $fqsen_string is invalid or empty
     */
    public static function fromStringInContext(
        string $fqsen_string,
        Context $context
    ) {
        // Check to see if we're fully qualified
        if (($fqsen_string[0] ?? '') === '\\') {
            return static::fromFullyQualifiedString($fqsen_string);
        }
        // @phan-suppress-next-line PhanAbstractStaticMethodCallInStatic Do not call fromStringInContext directly on this abstract class
        $namespace_map_type = static::getNamespaceMapType();
        if ($namespace_map_type === \ast\AST_CONST && \in_array(\strtolower($fqsen_string), ['true', 'false', 'null'], true)) {
            return static::fromFullyQualifiedString($fqsen_string);
        }

        // Split off the alternate ID
        $parts = \explode(',', $fqsen_string);
        $fqsen_string = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        $parts = \explode('\\', $fqsen_string);
        // Split the parts into the namespace(0 or more components) and the last name.
        $name = \array_pop($parts);

        // @phan-suppress-next-line PhanSuspiciousTruthyString
        if (!$name) {
            throw new InvalidFQSENException("The name cannot be empty", $fqsen_string);
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
        if ($namespace === '') {
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
    abstract protected static function getNamespaceMapType(): int;

    /**
     * @return string
     * The namespace associated with this FQSEN
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return string
     * The namespace+name associated with this FQSEN.
     * (e.g. 'ast\parse_code')
     */
    public function getNamespacedName(): string
    {
        if ($this->namespace === '\\') {
            return $this->name;
        }
        return \ltrim($this->namespace, '\\') . '\\' . $this->name;
    }

    /**
     * @return static a copy of this global structural element with a different namespace
     * @suppress PhanUnreferencedPublicMethod
     */
    public function withNamespace(
        string $namespace
    ) {
        // @phan-suppress-next-line PhanThrowTypeAbsentForCall the class name was already validated
        return static::make(
            self::cleanNamespace($namespace),
            $this->name,
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

        // @phan-suppress-next-line PhanThrowTypeAbsentForCall the class name was already validated
        return static::make(
            $this->namespace,
            $this->name,
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
    protected static function cleanNamespace(string $namespace): string
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
    ): string {
        $fqsen_string = $namespace;

        if ($fqsen_string !== '' && $fqsen_string !== '\\') {
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
    public function __toString(): string
    {
        return $this->as_string ?? $this->as_string = static::toString(
            $this->namespace,
            $this->name,
            $this->getAlternateId()
        );
    }
}
