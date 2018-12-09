<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Represents a template type that has not yet been resolved.
 * @see https://github.com/phan/phan/wiki/Generic-Types
 */
final class TemplateType extends Type
{
    /** @var string an identifier for the template type. */
    private $template_type_identifier;

    /**
     * @param string $template_type_identifier
     * An identifier for the template type
     */
    public function __construct(
        string $template_type_identifier
    ) {
        $this->template_type_identifier = $template_type_identifier;
    }

    /**
     * @return string
     * The name associated with this type
     */
    public function getName() : string
    {
        return $this->template_type_identifier;
    }

    /**
     * @return string
     * A string representation of this type in FQSEN form.
     * @override
     */
    public function asFQSENString() : string
    {
        return $this->template_type_identifier;
    }

    /**
     * @return string
     * The namespace associated with this type
     */
    public function getNamespace() : string
    {
        return '';
    }

    public function isObject() : bool
    {
        // Return true because we don't know, it may or may not be an object.
        // Not sure if this will be called.
        return true;
    }

    public function isObjectWithKnownFQSEN() : bool
    {
        // We have a template type ID, not an fqsen
        return false;
    }

    public function isPossiblyObject() : bool
    {
        return true;
    }

    /**
     * Replace the resolved reference to class T (possibly namespaced) with a regular template type.
     *
     * @param array<string,TemplateType> $template_fix_map maps the incorrectly resolved name to the template type @phan-unused-param
     * @return Type
     */
    public function withConvertTypesToTemplateTypes(array $template_fix_map) : Type
    {
        return $this;
    }

    /**
     * Returns true for `T` and `T[]` and `\MyClass<T>`, but not `\MyClass<\OtherClass>`
     *
     * Overridden in subclasses.
     */
    public function hasTemplateTypeRecursive() : bool
    {
        return true;
    }

    /**
     * @param array<string,UnionType> $template_parameter_type_map
     * A map from template type identifiers to concrete types
     *
     * @return UnionType
     * This UnionType with any template types contained herein
     * mapped to concrete types defined in the given map.
     *
     * @see UnionType::withConvertTypesToTemplateTypes() for the opposite
     */
    public function withTemplateParameterTypeMap(
        array $template_parameter_type_map
    ) : UnionType {
        return $template_parameter_type_map[$this->template_type_identifier] ?? $this->asUnionType();
    }
}
