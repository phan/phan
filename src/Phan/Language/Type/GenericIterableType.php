<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Closure;
use Generator;
use Phan\CodeBase;
use Phan\Config;
use Phan\Debug\Frame;
use Phan\Exception\RecursionDepthException;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;

use function count;
use function json_encode;

/**
 * Phan's representation of the type `iterable<KeyType,ValueType>`
 * @phan-pure
 */
final class GenericIterableType extends IterableType
{
    use NativeTypeTrait;

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
     * @unused-param $code_base
     * @return UnionType returns the iterable key's union type
     * @phan-override
     *
     * @see self::getKeyUnionType()
     */
    public function iterableKeyUnionType(CodeBase $code_base): UnionType
    {
        return $this->key_union_type;
    }

    /**
     * @unused-param $code_base
     * @return UnionType returns the iterable value's union type
     * @phan-override
     *
     * @see self::getElementUnionType()
     */
    public function iterableValueUnionType(CodeBase $code_base): UnionType
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

    public function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof GenericIterableType) {
            // TODO: Account for scalar key casting config?
            if (!$this->key_union_type->canCastToUnionType($type->key_union_type, $code_base)) {
                return false;
            }
            if (!$this->element_union_type->canCastToUnionType($type->element_union_type, $code_base)) {
                return false;
            }
            return true;
        }
        return parent::canCastToNonNullableType($type, $code_base);
    }

    public function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof GenericIterableType) {
            if (!$this->key_union_type->canCastToUnionTypeWithoutConfig($type->key_union_type, $code_base)) {
                return false;
            }
            if (!$this->element_union_type->canCastToUnionTypeWithoutConfig($type->element_union_type, $code_base)) {
                return false;
            }
            return true;
        }
        return parent::canCastToNonNullableTypeWithoutConfig($type, $code_base);
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

    public function asArrayType(): Type
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

    /**
     * @param CodeBase $code_base
     * The code base to use in order to find super classes, etc.
     *
     * @param $recursion_depth
     * This thing has a tendency to run-away on me. This tracks
     * how bad I messed up by seeing how far the expanded types
     * go
     *
     * @return UnionType
     * Expands class types to all inherited classes returning
     * a superset of this type.
     *
     * TODO: Support expanding key types. Support better checks for casting from Traversable/array.
     * Copy those fixes to asExpandedTypesPreservingTemplate().
     * @override
     */
    public function asExpandedTypes(
        CodeBase $code_base,
        int $recursion_depth = 0
    ): UnionType {
        // We're going to assume that if the type hierarchy
        // is taller than some value we probably messed up
        // and should bail out.
        if ($recursion_depth >= 20) {
            throw new RecursionDepthException("Recursion has gotten out of hand: " . Frame::getExpandedTypesDetails());
        }

        return $this->memoize(__METHOD__, function () use ($code_base, $recursion_depth): UnionType {
            // TODO: convert (A&B)[] to (A&B)[]|A[]|B[]
            $element_types = $this->element_union_type->getTypeSet();
            if (count($element_types) >= 2) {
                $union_type_builder = new UnionTypeBuilder();
                foreach ($element_types as $element_type) {
                    $new_type = self::fromKeyAndValueTypes($this->key_union_type, $element_type->asPHPDocUnionType(), $this->is_nullable);
                    $union_type_builder->addUnionType($new_type->asExpandedTypes($code_base, $recursion_depth + 1));
                }
                return $union_type_builder->getPHPDocUnionType();
            }
            $element_type = \reset($element_types);
            $union_type = $this->asPHPDocUnionType();
            if (!$element_type instanceof Type) {
                return $union_type;
            }
            $union_type = $this->asPHPDocUnionType();
            $recursive_union_type_builder = new UnionTypeBuilder();

            if (!$element_type->isObjectWithKnownFQSEN()) {
                return $union_type;
            }
            $class_fqsen = FullyQualifiedClassName::fromType($element_type);

            if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                return $union_type;
            }

            $clazz = $code_base->getClassByFQSEN($class_fqsen);

            $class_union_type = $clazz->getUnionType();
            $additional_union_type = $clazz->getAdditionalTypes();
            if ($additional_union_type !== null) {
                $class_union_type = $class_union_type->withUnionType($additional_union_type);
            }

            // TODO: Use helpers for list, non-empty-array, etc.
            foreach ($class_union_type->getTypeSet() as $type) {
                $union_type = $union_type->withType(self::fromKeyAndValueTypes($this->key_union_type, $type->asPHPDocUnionType(), $this->is_nullable));
            }

            // Recurse up the tree to include all types
            $representation = $this->__toString();
            try {
                foreach ($union_type->getTypeSet() as $clazz_type) {
                    if ($clazz_type->__toString() !== $representation) {
                        $recursive_union_type_builder->addUnionType(
                            $clazz_type->asExpandedTypes(
                                $code_base,
                                $recursion_depth + 1
                            )
                        );
                    } else {
                        $recursive_union_type_builder->addType($clazz_type);
                    }
                }
            } catch (RecursionDepthException $_) {
                return GenericIterableType::fromKeyAndValueTypes($this->key_union_type, UnionType::fromFullyQualifiedPHPDocString('mixed'), $this->is_nullable)->asPHPDocUnionType();
            }

            // Add in aliases
            // (If enable_class_alias_support is false, this will do nothing)
            if (Config::getValue('enable_class_alias_support')) {
                $this->addClassAliases($code_base, $recursive_union_type_builder, $class_fqsen);
            }
            return $recursive_union_type_builder->getPHPDocUnionType();
        });
    }

    /**
     * @param CodeBase $code_base
     * The code base to use in order to find super classes, etc.
     *
     * @param $recursion_depth
     * This thing has a tendency to run-away on me. This tracks
     * how bad I messed up by seeing how far the expanded types
     * go
     *
     * @return UnionType
     * Expands class types to all inherited classes returning
     * a superset of this type.
     * @override
     */
    public function asExpandedTypesPreservingTemplate(
        CodeBase $code_base,
        int $recursion_depth = 0
    ): UnionType {
        // We're going to assume that if the type hierarchy
        // is taller than some value we probably messed up
        // and should bail out.
        if ($recursion_depth >= 20) {
            throw new RecursionDepthException("Recursion has gotten out of hand: " . Frame::getExpandedTypesDetails());
        }

        return $this->memoize(__METHOD__, function () use ($code_base, $recursion_depth): UnionType {
            // TODO: convert (A&B)[] to (A&B)[]|A[]|B[]
            $element_types = $this->element_union_type->getTypeSet();
            if (count($element_types) >= 2) {
                $union_type_builder = new UnionTypeBuilder();
                foreach ($element_types as $element_type) {
                    $new_type = self::fromKeyAndValueTypes($this->key_union_type, $element_type->asPHPDocUnionType(), $this->is_nullable);
                    $union_type_builder->addUnionType($new_type->asExpandedTypesPreservingTemplate($code_base, $recursion_depth + 1));
                }
                return $union_type_builder->getPHPDocUnionType();
            }
            $element_type = \reset($element_types);
            $union_type = $this->asPHPDocUnionType();
            if (!$element_type instanceof Type) {
                return $union_type;
            }
            $union_type = $this->asPHPDocUnionType();
            $recursive_union_type_builder = new UnionTypeBuilder();

            if (!$element_type->isObjectWithKnownFQSEN()) {
                return $union_type;
            }
            $class_fqsen = FullyQualifiedClassName::fromType($element_type);

            if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                return $union_type;
            }

            $clazz = $code_base->getClassByFQSEN($class_fqsen);

            $class_union_type = $clazz->getUnionType();
            $additional_union_type = $clazz->getAdditionalTypes();
            if ($additional_union_type !== null) {
                $class_union_type = $class_union_type->withUnionType($additional_union_type);
            }

            // TODO: Use helpers for list, non-empty-array, etc.
            foreach ($class_union_type->getTypeSet() as $type) {
                $union_type = $union_type->withType(self::fromKeyAndValueTypes($this->key_union_type, $type->asPHPDocUnionType(), $this->is_nullable));
            }

            // Recurse up the tree to include all types
            $representation = $this->__toString();
            try {
                foreach ($union_type->getTypeSet() as $clazz_type) {
                    if ($clazz_type->__toString() !== $representation) {
                        $recursive_union_type_builder->addUnionType(
                            $clazz_type->asExpandedTypesPreservingTemplate(
                                $code_base,
                                $recursion_depth + 1
                            )
                        );
                    } else {
                        $recursive_union_type_builder->addType($clazz_type);
                    }
                }
            } catch (RecursionDepthException $_) {
                return GenericIterableType::fromKeyAndValueTypes($this->key_union_type, UnionType::fromFullyQualifiedPHPDocString('mixed'), $this->is_nullable)->asPHPDocUnionType();
            }

            // Add in aliases
            // (If enable_class_alias_support is false, this will do nothing)
            if (Config::getValue('enable_class_alias_support')) {
                $this->addClassAliases($code_base, $recursive_union_type_builder, $class_fqsen);
            }
            return $recursive_union_type_builder->getPHPDocUnionType();
        });
    }

    // (If enable_class_alias_support is false, this will not be called)
    private function addClassAliases(
        CodeBase $code_base,
        UnionTypeBuilder $union_type_builder,
        FullyQualifiedClassName $class_fqsen
    ): void {
        $fqsen_aliases = $code_base->getClassAliasesByFQSEN($class_fqsen);
        foreach ($fqsen_aliases as $alias_fqsen_record) {
            $alias_fqsen = $alias_fqsen_record->alias_fqsen;
            $union_type_builder->addType(
                GenericIterableType::fromKeyAndValueTypes($this->key_union_type, $alias_fqsen->asType()->asPHPDocUnionType(), $this->is_nullable)
            );
        }
    }

    public function getReferencedClasses(): Generator
    {
        yield from $this->key_union_type->getReferencedClasses();
        yield from $this->element_union_type->getReferencedClasses();
    }

    public function asObjectType(): Type
    {
        return Type::make(
            '\\',
            'Traversable',
            $this->key_union_type->isEmpty() ? [$this->element_union_type] : [$this->key_union_type, $this->element_union_type],
            false,
            Type::FROM_TYPE
        );
    }

    public function isSubtypeOf(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof GenericIterableType) {
            return $this->key_union_type->isStrictSubtypeOf($code_base, $type->key_union_type) &&
                $this->element_union_type->isStrictSubtypeOf($code_base, $type->element_union_type);
        }
        return \get_class($type) === IterableType::class || $type instanceof MixedType;
    }
}
