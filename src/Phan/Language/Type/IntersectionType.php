<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use AssertionError;
use Closure;
use Generator;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;

use function implode;
use function in_array;
use function count;
use function get_debug_type;
use function reset;

/**
 * Represents the intersection of two or more object types
 * TODO: Forbid non-object types when creating this IntersectionType?
 */
final class IntersectionType extends Type
{
    /** @var non-empty-list<Type> the parts of the intersection type */
    protected $type_parts;

    /** @param non-empty-list<Type> $type_parts */
    private function __construct(array $type_parts)
    {
        if (\count($type_parts) < 2) {
            throw new AssertionError("Too few type_parts in intersection type (" . implode('&', $type_parts) . ')') ;
        }
        // an intersection type is nullable if all type_parts it contains are nullable.
        // FIXME: Normalize (?A)&B
        $is_nullable = true;
        foreach ($type_parts as $type) {
            if (!$type instanceof Type) {
                throw new AssertionError("IntersectionType expected all arguments to be type_parts, got " . get_debug_type($type));
            } elseif ($type instanceof IntersectionType) {
                throw new AssertionError("Intersection type_parts must contain atomic type_parts");
            }
            $is_nullable = $is_nullable && $type->isNullable();
        }
        $this->type_parts = $type_parts;
        parent::__construct('\\', '', [], $is_nullable);
    }

    /**
     * @return non-empty-list<Type> the list of parts of this intersection type.
     */
    public function getTypeParts(): array
    {
        return $this->type_parts;
    }

    /**
     * @return IntersectionType
     * @override
     */
    public function withIsNullable(bool $is_nullable): Type {
        if ($this->is_nullable === $is_nullable) {
            return $this;
        }
        $new_types = [];
        foreach ($this->type_parts as $type) {
            $new_types[] = $type->withIsNullable($is_nullable);
        }
        return new self($new_types);
    }

    /**
     * Create an intersection type from a list of type_parts
     *
     * @param non-empty-list<Type|UnionType> $types
     * @param ?CodeBase $code_base
     * @param ?Context $context @unused-param
     */
    public static function createFromTypes(array $types, ?CodeBase $code_base, ?Context $context): Type
    {
        $new_types = self::flattenTypes($types);
        if (!$new_types) {
            throw new AssertionError('Saw empty list of type_parts in intersection type');
        }
        if (count($new_types) === 1) {
            return reset($new_types);
        }
        // NOTE: This is not useful for intersection types created in the parse phase,
        // because those were already parsed
        if ($code_base) {
            foreach ($new_types as $i => $type) {
                if (!isset($new_types[$i])) {
                    continue;
                }
                foreach ($new_types as $j => $other) {
                    if ($j === $i) {
                        continue;
                    }
                    // For example, ArrayObject is a subtype of Countable, so Countable is redundant to add to an intersection type
                    if ($type->isSubtypeOf($other, $code_base)) {
                        unset($new_types[$j]);
                    }
                }
            }
            if (count($new_types) === 1) {
                // @phan-suppress-next-line PhanPossiblyFalseTypeReturn
                return reset($new_types);
            }
            $new_types = \array_values($new_types);
        }
        // avoid storing an equivalent copy of an array that may already be referenced elsewhere
        // to reduce memory usage
        if ($types !== $new_types) {
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument
            return new self($new_types);
        }
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        return new self($types);
    }

    /**
     * Convert a list of types and union types (e.g. from phpdoc)
     * into a list of Types that are not IntersectionType instances
     *
     * @param non-empty-list<Type|UnionType> $types
     * @return non-empty-list<Type>
     */
    public static function flattenTypes(array $types): array
    {
        $new_types = [];
        foreach ($types as $type) {
            if ($type instanceof UnionType) {
                $type_set = $type->getTypeSet();
                if (count($type_set) !== 1) {
                    throw new AssertionError("Expected union type_parts to contain a single type");
                }
                $type = reset($type_set);
            }
            foreach ($type instanceof IntersectionType ? $type->type_parts : [$type] as $part) {
                // TODO: if ($type instanceof IntersectionType)
                if (!in_array($part, $new_types, true)) {
                    $new_types[] = $part;
                }
            }
        }
        if (!$new_types) {
            throw new AssertionError("Did not expect empty list of types for intersection type");
        }
        // @phan-suppress-next-line PhanPartialTypeMismatchReturn
        return $new_types;
    }

    /**
     * @override
     */
    public function __toString(): string
    {
        return $this->memoize(__METHOD__, function (): string {
            return implode('&', $this->type_parts);
        });
    }

    /**
     * Emit an issue and return true if this intersection type contains an impossible combination
     */
    public function checkImpossibleCombination(CodeBase $code_base, Context $context): bool
    {
        foreach ($this->type_parts as $i => $type) {
            foreach ($this->type_parts as $j => $other) {
                if ($j === $i) {
                    continue;
                }
                if ($context && !$type->asPHPDocUnionType()->canCastToDeclaredType($code_base, (clone $context)->withStrictTypes(1), $other->asPHPDocUnionType())) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::ImpossibleIntersectionType,
                        $context->getLineNumberStart(),
                        $this,
                        $type,
                        $other
                    );
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return Generator<mixed,Type> ($outer_type => $inner_type)
     *
     * This includes classes, StaticType (and "self"), and TemplateType.
     * This includes duplicate definitions
     * TODO: Warn about Closure Declarations with invalid parameters...
     *
     * TODO: Use different helper for GoToDefinitionRequest
     * @override
     */
    public function getReferencedClasses(): Generator
    {
        foreach ($this->type_parts as $outer_type) {
            foreach ($outer_type->getReferencedClasses() as $type) {
                yield $outer_type => $type;
            }
        }
    }

    /**
     * This is not an object with a single known fqsen, it is one with multiple fqsens
     * @override
     */
    public function isObjectWithKnownFQSEN(): bool
    {
        return false;
    }

    /**
     * @override
     */
    protected function computeExpandedTypes(CodeBase $code_base, int $recursion_depth): UnionType
    {
        $recursive_union_type_builder = new UnionTypeBuilder();
        $recursive_union_type_builder->addType($this);
        foreach ($this->type_parts as $type) {
            $recursive_union_type_builder->addUnionType($type->asExpandedTypes($code_base, $recursion_depth + 1));
        }

        return $recursive_union_type_builder->getPHPDocUnionType();
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly (accounting for templates)
     * @override
     */
    public function canCastToType(Type $type, CodeBase $code_base): bool
    {
        // Handle intersection -> intersection cast
        return $this->anyTypePartsMatchOtherTypePartsCallback(static function (Type $part, Type $other_part) use ($code_base): bool {
            return $part->canCastToType($other_part, $code_base);
        }, $type);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly without config settings.
     * @override
     */
    public function canCastToTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        // TODO: Handle intersection -> intersection cast
        return $this->anyTypePartsMatchOtherTypePartsCallback(static function (Type $part, Type $other_part) use ($code_base): bool {
            return $part->canCastToTypeWithoutConfig($other_part, $code_base);
        }, $type);
    }

    /**
     * @override
     * @deprecated
     */
    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        // TODO: Handle intersection -> intersection cast
        return $this->anyTypePartsMatchOtherTypePartsCallback(static function (Type $part, Type $other_part) use ($code_base): bool {
            return $part->canCastToNonNullableType($other_part, $code_base);
        }, $type);
    }

    /**
     * @override
     */
    public function canCastToNonNullableTypeHandlingTemplates(Type $type, CodeBase $code_base): bool
    {
        // TODO: Handle intersection -> intersection cast
        return $this->anyTypePartsMatchOtherTypePartsCallback(static function (Type $part, Type $other_part) use($code_base): bool {
            return $part->canCastToNonNullableTypeHandlingTemplates($other_part, $code_base);
        }, $type);
    }

    /**
     * @override
     */
    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        return $this->anyTypePartsMatchOtherTypePartsCallback(static function (Type $part, Type $other_part) use ($code_base): bool {
            return $part->canCastToNonNullableTypeWithoutConfig($other_part, $code_base);
        }, $type);
    }

    /**
     * @override
     */
    public function isSubtypeOf(Type $type, CodeBase $code_base): bool
    {
        return $this->anyTypePartsMatchOtherTypePartsCallback(static function (Type $part, Type $other_part) use ($code_base): bool {
            return $part->isSubtypeOf($other_part, $code_base);
        }, $type);
    }

    /**
     * @override
     */
    public function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return $this->anyTypePartsMatchOtherTypePartsCallback(static function (Type $part, Type $other_part) use ($code_base): bool {
            return $part->isSubtypeOfNonNullableType($other_part, $code_base);
        }, $type);
    }

    /**
     * Returns true if each of the types in this IntersectionType made $matcher_callback return true
     * @param Closure(Type): bool $matcher_callback
     */
    public function allTypePartsMatchCallback(Closure $matcher_callback): bool
    {
        foreach ($this->type_parts as $type) {
            if (!$matcher_callback($type)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns true if any of the types in this IntersectionType made $matcher_callback return true
     * @param Closure(Type): bool $matcher_callback
     * @override
     */
    public function anyTypePartsMatchCallback(Closure $matcher_callback): bool
    {
        foreach ($this->type_parts as $type) {
            if ($matcher_callback($type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if any of the types in this IntersectionType made $matcher_callback return true for all parts of $other
     * @param Closure(Type, Type): bool $matcher_callback
     * @param Type $other (can be IntersectionType)
     */
    public function anyTypePartsMatchOtherTypePartsCallback(Closure $matcher_callback, Type $other): bool
    {
        if ($other instanceof IntersectionType) {
            foreach ($other->type_parts as $other_part) {
                if (!$this->anyTypePartsMatchOtherTypePartsCallback($matcher_callback, $other_part)) {
                    return false;
                }
            }
            return true;
        }
        foreach ($this->type_parts as $type) {
            if ($matcher_callback($type, $other)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Creates a type or intersection type by transforming this intersection type
     * @param Closure(Type): Type $mapping_callback
     */
    public function mapTypeParts(Closure $mapping_callback): Type
    {
        $new_types = [];
        foreach ($this->type_parts as $type) {
            $new_types[] = $mapping_callback($type);
        }
        if ($new_types === $this->type_parts) {
            return $this;
        }
        return self::createFromTypes($new_types, null, null);
    }

    /**
     * Convert `\MyClass<T>` and `\MyClass<\OtherClass>` to just `\MyClass`.
     *
     * TODO: Override in subclasses such as generic arrays, generic iterables, and array shapes.
     * @override
     */
    public function eraseTemplatesRecursive(): Type
    {
        return $this->mapTypeParts(static function (Type $part): Type {
            return $part->eraseTemplatesRecursive();
        });
    }

    public function canUseInRealSignature(): bool
    {
        // TODO: Enable when supported for php 8.1 and minimum_target_php_version_id is satisfied and all parts work
        return false;
    }

    public function hasObjectWithKnownFQSEN(): bool
    {
        foreach ($this->type_parts as $type) {
            if ($type->hasObjectWithKnownFQSEN()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return Type
     * Either this or 'static' resolved in the given context.
     */
    public function withStaticResolvedInContext(
        Context $context
    ): Type {
        $type_parts = $this->type_parts;
        foreach ($type_parts as $i => $type) {
            $type_parts[$i] = $type->withStaticResolvedInContext($context);
        }
        if ($type_parts === $this->type_parts) {
            return $this;
        }
        return new self($type_parts);
    }
}
