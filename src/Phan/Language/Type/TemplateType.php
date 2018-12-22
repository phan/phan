<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Closure;
use Phan\CodeBase;
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
    protected function __construct(
        string $template_type_identifier,
        bool $is_nullable
    ) {
        $this->template_type_identifier = $template_type_identifier;
        $this->is_nullable = $is_nullable;
    }

    /**
     * Create an instance for this ID
     */
    public static function instanceForId(string $id, bool $is_nullable) : TemplateType
    {
        if ($is_nullable) {
            static $nullable_cache = [];
            return $nullable_cache[$id] ?? ($nullable_cache[$id] = new self($id, true));
        }
        static $cache = [];
        return $cache[$id] ?? ($cache[$id] = new self($id, false));
    }

    /**
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass
     * false
     *
     * @return Type
     * A new type that is a copy of this type but with the
     * given nullability value.
     */
    public function withIsNullable(bool $is_nullable) : Type
    {
        if ($is_nullable === $this->is_nullable) {
            return $this;
        }

        return self::instanceForId(
            $this->template_type_identifier,
            $is_nullable
        );
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

    /**
     * Combine two closures that generate union types
     * @param ?Closure(mixed):UnionType $left
     * @param ?Closure(mixed):UnionType $right
     * @return ?Closure(mixed):UnionType
     */
    public static function combineParameterClosures($left, $right)
    {
        if (!$left) {
            return $right;
        }
        if (!$right) {
            return $left;
        }

        /**
         * @param mixed $params
         */
        return function ($params) use ($left, $right) : UnionType {
            return $left($params)->withUnionType($right($params));
        };
    }

    /**
     * @param TemplateType $template_type the template type that this union type is being searched for.
     *
     * @return ?Closure(UnionType):UnionType a closure to map types to the template type wherever it was in the original union type
     */
    public function getTemplateTypeExtractorClosure(CodeBase $unused_code_base, TemplateType $template_type)
    {
        if ($this === $template_type) {
            return function (UnionType $type) : UnionType {
                return $type;
            };
        }
        // Overridden in subclasses
        return null;
    }
}
