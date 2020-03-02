<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Closure;
use Generator;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

use function json_encode;

/**
 * Phan's representation of the type `iterable<KeyType,ValueType>`
 * @phan-pure
 */
final class GenericIterableType extends IterableType
{
    /** @phan-override */
    public const NAME = 'iterable';

    /**
     * @var UnionType the union type of the keys of this iterable.
     */
    private $key_union_type;

    /**
     * @var UnionType the union type of the elements of this iterable.
     */
    private $element_union_type;

    protected function __construct(UnionType $key_union_type, UnionType $element_union_type, bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
        $this->key_union_type = $key_union_type;
        $this->element_union_type = $element_union_type;
    }

    /**
     * @return UnionType returns the iterable key's union type, because this is a subtype of iterable.
     * Other classes in the `Type` type hierarchy may return null.
     */
    public function getKeyUnionType(): UnionType
    {
        return $this->key_union_type;
    }

    /**
     * Returns `GenericArrayType::KEY_*` for the union type of this iterable's keys.
     * e.g. for `iterable<string, stdClass>`, returns KEY_STRING
     */
    public function getKeyType(): int
    {
        return $this->memoize(__METHOD__, function (): int {
            return GenericArrayType::keyTypeFromUnionTypeValues($this->key_union_type);
        });
    }

    /**
     * @return UnionType returns the union type of possible element types.
     */
    public function getElementUnionType(): UnionType
    {
        return $this->element_union_type;
    }

    public function genericArrayElementUnionType(): UnionType
    {
        return $this->element_union_type;
    }

    /**
     * @return UnionType returns the iterable key's union type
     * @phan-override
     *
     * @see self::getKeyUnionType()
     */
    public function iterableKeyUnionType(CodeBase $unused_code_base): UnionType
    {
        return $this->key_union_type;
    }

    /**
     * @return UnionType returns the iterable value's union type
     * @phan-override
     *
     * @see self::getElementUnionType()
     */
    public function iterableValueUnionType(CodeBase $unused_code_base): UnionType
    {
        return $this->element_union_type;
    }

    /**
     * Returns a nullable/non-nullable GenericIterableType
     * representing `iterable<$key_union_type, $element_union_type>`
     */
    public static function fromKeyAndValueTypes(UnionType $key_union_type, UnionType $element_union_type, bool $is_nullable): GenericIterableType
    {
        static $cache = [];
        $key = ($is_nullable ? '?' : '') . json_encode($key_union_type->generateUniqueId()) . ':' . json_encode($element_union_type->generateUniqueId());
        return $cache[$key] ?? ($cache[$key] = new self($key_union_type, $element_union_type, $is_nullable));
    }

    public function canCastToNonNullableType(Type $type): bool
    {
        if ($type instanceof GenericIterableType) {
            // TODO: Account for scalar key casting config?
            if (!$this->key_union_type->canCastToUnionType($type->key_union_type)) {
                return false;
            }
            if (!$this->element_union_type->canCastToUnionType($type->element_union_type)) {
                return false;
            }
            return true;
        }
        return parent::canCastToNonNullableType($type);
    }

    public function canCastToNonNullableTypeWithoutConfig(Type $type): bool
    {
        if ($type instanceof GenericIterableType) {
            if (!$this->key_union_type->canCastToUnionTypeWithoutConfig($type->key_union_type)) {
                return false;
            }
            if (!$this->element_union_type->canCastToUnionTypeWithoutConfig($type->element_union_type)) {
                return false;
            }
            return true;
        }
        return parent::canCastToNonNullableTypeWithoutConfig($type);
    }
    /**
     * Returns true for `T` and `T[]` and `\MyClass<T>`, but not `\MyClass<\OtherClass>` or `false`
     */
    public function hasTemplateTypeRecursive(): bool
    {
        return $this->key_union_type->hasTemplateTypeRecursive() || $this->element_union_type->hasTemplateTypeRecursive();
    }

    /**
     * @param array<string,UnionType> $template_parameter_type_map
     * A map from template type identifiers to concrete types
     *
     * @return UnionType
     * This UnionType with any template types contained herein
     * mapped to concrete types defined in the given map.
     *
     * Overridden in subclasses
     */
    public function withTemplateParameterTypeMap(
        array $template_parameter_type_map
    ): UnionType {
        $new_key_type = $this->key_union_type->withTemplateParameterTypeMap($template_parameter_type_map);
        $new_element_type = $this->element_union_type->withTemplateParameterTypeMap($template_parameter_type_map);
        if ($new_element_type === $this->element_union_type &&
            $new_key_type === $this->key_union_type) {
            return $this->asPHPDocUnionType();
        }
        return self::fromKeyAndValueTypes($new_key_type, $new_element_type, $this->is_nullable)->asPHPDocUnionType();
    }

    public function __toString(): string
    {
        $string = $this->element_union_type->__toString();
        if (!$this->key_union_type->isEmpty()) {
            $string = $this->key_union_type->__toString() . ',' . $string;
        }
        $string = "iterable<$string>";

        if ($this->is_nullable) {
            $string = '?' . $string;
        }

        return $string;
    }

    /**
     * If this generic array type in a parameter declaration has template types, get the closure to extract the real types for that template type from argument union types
     *
     * @param CodeBase $code_base
     * @return ?Closure(UnionType, Context):UnionType
     */
    public function getTemplateTypeExtractorClosure(CodeBase $code_base, TemplateType $template_type): ?Closure
    {
        $closure = $this->element_union_type->getTemplateTypeExtractorClosure($code_base, $template_type);
        if ($closure) {
            // If a function expects T[], then T is the generic array element type of the passed in union type
            $element_closure = static function (UnionType $type, Context $context) use ($code_base, $closure): UnionType {
                return $closure($type->iterableValueUnionType($code_base), $context);
            };
        } else {
            $element_closure = null;
        }
        $closure = $this->key_union_type->getTemplateTypeExtractorClosure($code_base, $template_type);
        if ($closure) {
            $key_closure = static function (UnionType $type, Context $context) use ($code_base, $closure): UnionType {
                return $closure($type->iterableKeyUnionType($code_base), $context);
            };
        } else {
            $key_closure = null;
        }
        return TemplateType::combineParameterClosures($key_closure, $element_closure);
    }

    /**
     * Returns the corresponding type that would be used in a signature
     * @override
     */
    public function asSignatureType(): Type
    {
        return IterableType::instance($this->is_nullable);
    }

    public function asArrayType(): ?Type
    {
        $key_type = GenericArrayType::keyTypeFromUnionTypeValues($this->key_union_type);
        if ($this->element_union_type->typeCount() === 1) {
            $element_type = $this->element_union_type->getTypeSet()[0];
        } else {
            if ($key_type === GenericArrayType::KEY_MIXED) {
                return ArrayType::instance(false);
            }
            $element_type = MixedType::instance(false);
        }
        return GenericArrayType::fromElementType($element_type, false, $key_type);
    }

    /**
     * Returns a type where all referenced union types (e.g. in generic arrays) have real type sets removed.
     */
    public function withErasedUnionTypes(): Type
    {
        return $this->memoize(__METHOD__, function (): Type {
            $erased_element_union_type = $this->element_union_type->eraseRealTypeSetRecursively();
            $erased_key_union_type = $this->key_union_type->eraseRealTypeSetRecursively();
            if ($erased_key_union_type === $this->key_union_type && $erased_element_union_type === $this->element_union_type) {
                return $this;
            }
            return self::fromKeyAndValueTypes($this->key_union_type, $erased_element_union_type, $this->is_nullable);
        });
    }

    /**
     * @override
     */
    public function withIsNullable(bool $is_nullable): Type
    {
        if ($is_nullable === $this->is_nullable) {
            return $this;
        }

        return self::fromKeyAndValueTypes(
            $this->key_union_type,
            $this->element_union_type,
            $is_nullable
        );
    }

    public function getTypesRecursively(): Generator
    {
        yield $this;
        yield from $this->key_union_type->getTypesRecursively();
        yield from $this->element_union_type->getTypesRecursively();
    }
}
