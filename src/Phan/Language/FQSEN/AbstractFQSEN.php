<?php

declare(strict_types=1);

namespace Phan\Language\FQSEN;

use Error;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Serializable;

/**
 * A Fully-Qualified Name
 *
 * Serialization and cloning are forbidden.
 */
abstract class AbstractFQSEN implements FQSEN, Serializable
{

    /**
     * @var string
     * The name of this structural element
     * @readonly
     */
    protected $name = '';

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
     * @return static
     */
    abstract public static function fromFullyQualifiedString(
        string $fully_qualified_string
    );

    /**
     * @param string $fqsen_string
     * An FQSEN string like '\Namespace\Class::method' or
     * 'Class' or 'Class::method'.
     *
     * @param Context $context
     * The context in which the FQSEN string was found
     *
     * @return static
     */
    abstract public static function fromStringInContext(
        string $fqsen_string,
        Context $context
    );

    /**
     * @return string
     * The class associated with this FQSEN
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     * The canonical representation of the name of the object. Functions
     * and Methods, for instance, lowercase their names.
     */
    public static function canonicalName(string $name): string
    {
        return $name;
    }

    /**
     * @return string
     * The canonical representation of the name of the object,
     * for use in array key lookups for singletons, namespace maps, etc.
     * This should not be used directly or indirectly in issue output
     * If an FQSEN is case-sensitive, this should return $name
     */
    public static function canonicalLookupKey(string $name): string
    {
        return \strtolower($name);
    }

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    abstract public function __toString(): string;

    /**
     * @throws Error to prevent accidentally calling this
     */
    public function __clone()
    {
        // We compare and look up FQSENs by their identity
        throw new Error("cloning an FQSEN (" . (string)$this . ") is forbidden\n");
    }

    /**
     * @throws Error to prevent accidentally calling this
     */
    public function serialize(): string
    {
        // We compare and look up FQSENs by their identity
        throw new Error("serializing an FQSEN (" . (string)$this . ") is forbidden\n");
    }

    /**
     * @param string $serialized
     * @throws Error to prevent accidentally calling this
     * @suppress PhanParamSignatureRealMismatchHasNoParamTypeInternal, PhanUnusedSuppression parameter type widening was allowed in php 7.2, signature changed in php 8
     */
    public function unserialize($serialized): void
    {
        // We compare and look up FQSENs by their identity
        throw new Error("unserializing an FQSEN ($serialized) is forbidden\n");
    }
}
