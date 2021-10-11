<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use AssertionError;
use Closure;
use Generator;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;

use function count;
use function get_debug_type;
use function implode;
use function in_array;
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
    public function withIsNullable(bool $is_nullable): Type
    {
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
     * @param bool $suppress_union_type_warnings
     */
    public static function createFromTypes(array $types, ?CodeBase $code_base, ?Context $context, bool $suppress_union_type_warnings = false): Type
    {
        $new_types = self::flattenTypes($types, $code_base, $context, $suppress_union_type_warnings);
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
    private static function flattenTypes(array $types, ?CodeBase $code_base, ?Context $context, bool $suppress_union_type_warnings): array
    {
        $new_types = [];
        foreach ($types as $type) {
            if ($type instanceof UnionType) {
                $type_set = $type->getTypeSet();
                if (count($type_set) !== 1) {
                    if ($suppress_union_type_warnings) {
                        if ($code_base && $context) {
                            Issue::maybeEmit(
                                $code_base,
                                $context,
                                Issue::CommentUnsupportedUnionType,
                                $context->getLineNumberStart(),
                                $type
                            );
                        }
                        $type = $type->withFlattenedArrayShapeOrLiteralTypeInstances();
                        $type_set = $type->getTypeSet();
                        if (count($type_set) !== 1) {
                            if ($type->isArray()) {
                                $type_set = [ArrayType::instance($type->containsNullable())];
                            } elseif ($type->isObject()) {
                                $type_set = [ObjectType::instance($type->containsNullable())];
                            } else {
                                $type_set = [MixedType::instance(false)];
                            }
                        }
                    } else {
                        throw new AssertionError("Expected union type_parts to contain a single type");
                    }
                }
                $type = reset($type_set);
                if (!$type instanceof Type) {
                    throw new AssertionError("Impossible non-type in " . __METHOD__);
                }
            }
            foreach ($type instanceof IntersectionType ? $type->type_parts : [$type] as $part) {
                // TODO: if ($type instanceof IntersectionType)
                if (!in_array($part, $new_types, true)) {
                    $new_types[] = $part;
                }
            }
        }
        $has_exclusively_truthy = false;
        $has_array = false;
        $has_object = false;
        foreach ($new_types as $i => $type) {
            if (!$has_exclusively_truthy) {
                $has_exclusively_truthy = $type->isAlwaysTruthy();
            }
            // Convert callable&object to callable-object, etc.
            if ($type instanceof CallableType) {
                foreach ($new_types as $j => $other) {
                    if ($i === $j) {
                        continue;
                    }
                    if ($other->isObject()) {
                        $new_types[$i] = CallableObjectType::instance($type->isNullable());
                        continue 2;
                    } elseif ($other instanceof StringType) {
                        $new_types[$i] = CallableStringType::instance($type->isNullable());
                        continue 2;
                    } elseif ($other instanceof ArrayType) {
                        $new_types[$i] = CallableArrayType::instance($type->isNullable());
                        continue 2;
                    }
                }
            }
            if ($type instanceof ObjectType) {
                $has_object = true;
            } elseif ($type instanceof ArrayType) {
                $has_array = true;
            }
            // TODO: Convert iterable<k,v> to Traversable<k,v> if another value is an object
        }
        if ($has_object) {
            foreach ($new_types as $j => $other) {
                if ($other->isObject()) {
                    // do nothing
                } elseif (!$other->isPossiblyObject()) {
                    // Emit PhanImpossibleIntersectionType elsewhere
                    continue;
                } else {
                    $other = $other->asObjectType();
                    if ($other) {
                        $new_types[$j] = $other;
                    }
                }
            }
        } elseif ($has_array) {
            foreach ($new_types as $j => $other) {
                if ($other instanceof ArrayType) {
                    continue;
                }
                if (!$other instanceof IterableType && !$other instanceof MixedType) {
                    // Emit PhanImpossibleIntersectionType elsewhere
                    continue;
                }
                $other = $other->asArrayType();
                if ($other) {
                    $new_types[$j] = $other;
                }
            }
        }
        if ($has_exclusively_truthy) {
            foreach ($new_types as $j => $other) {
                if ($other->isAlwaysTruthy()) {
                    continue;
                }
                if (!$other->isPossiblyTruthy()) {
                    // Emit PhanImpossibleIntersectionType elsewhere
                    continue;
                }
                $new_types[$j] = $other->asNonFalseyType();
            }
        }
        return \array_values($new_types);
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
                if (!$type->asPHPDocUnionType()->canCastToDeclaredType($code_base, (clone $context)->withStrictTypes(1), $other->asPHPDocUnionType())) {
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
        foreach ($this->type_parts as $part) {
            if ($part->checkImpossibleCombination($code_base, $context)) {
                return true;
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
     * @override
     */
    protected function computeExpandedTypesPreservingTemplate(CodeBase $code_base, int $recursion_depth): UnionType
    {
        $recursive_union_type_builder = new UnionTypeBuilder();
        $recursive_union_type_builder->addType($this);
        foreach ($this->type_parts as $type) {
            $recursive_union_type_builder->addUnionType($type->asExpandedTypesPreservingTemplate($code_base, $recursion_depth + 1));
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
        return $this->anyTypePartsMatchOtherTypePartsCallback(static function (Type $part, Type $other_part) use ($code_base): bool {
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
     * Creates an optional type or intersection type by transforming this intersection type
     * @param Closure(Type): (?Type) $mapping_callback
     */
    public function mapTypePartsToOptionalType(Closure $mapping_callback): ?Type
    {
        $new_types = [];
        foreach ($this->type_parts as $type) {
            $new_type = $mapping_callback($type);
            if ($new_type) {
                $new_types[] = $new_type;
            }
        }
        if ($new_types === $this->type_parts) {
            return $this;
        }
        return $new_types ? self::createFromTypes($new_types, null, null) : null;
    }

    public function hasTemplateTypeRecursive(): bool
    {
        return $this->anyTypePartsMatchMethod(__FUNCTION__);
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
        return $this->mapTypeParts(static function (Type $part) use ($template_parameter_type_map): Type {
            $mapped = $part->withTemplateParameterTypeMap($template_parameter_type_map);
            return $mapped->typeCount() === 1 ? $mapped->getTypeSet()[0] : $part;
        })->asPHPDocUnionType();
    }

    /**
     * @suppress PhanUnusedReturnBranchWithoutSideEffects we pretend Issue::maybeEmit doesn't have side effects but it does
     */
    public function asFunctionInterfaceOrNull(CodeBase $code_base, Context $context, bool $warn = true): ?FunctionInterface
    {
        foreach ($this->type_parts as $part) {
            $function = $part->asFunctionInterfaceOrNull($code_base, $context, false);
            if ($function) {
                return $function;
            }
        }
        if ($warn && $this->hasObjectWithKnownFQSEN()) {
            foreach ($this->type_parts as $part) {
                if ($part->isCallable($code_base)) {
                    // don't warn about Countable&callable-object
                    return null;
                }
            }
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::UndeclaredInvokeInCallable,
                $context->getLineNumberStart(),
                '__invoke',
                $this
            );
        }
        return null;
    }

    // TODO Implement getTemplateTypeExtractorClosure? Does it make sense to do that?

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

    /**
     * @return ?UnionType returns the iterable value's union type if this is a subtype of iterable, null otherwise.
     * @override
     */
    public function iterableValueUnionType(CodeBase $code_base): ?UnionType
    {
        // TODO: Split out into a common helper method
        $values = [];
        $real_values = [];
        foreach ($this->type_parts as $part) {
            $value = $part->iterableValueUnionType($code_base);
            if ($value === null) {
                continue;
            }
            if ($value->typeCount() > 1) {
                return $value;
            }
            foreach ($value->getTypeSet() as $inner) {
                $values[] = $inner;
            }
            $real_type_set = $value->getRealTypeSet();
            if (count($real_type_set) > 1) {
                return $value;
            }
            foreach ($real_type_set as $inner) {
                $real_values[] = $inner;
            }
        }
        if (!$values) {
            return null;
        }
        $real_type_set = $real_values ? [self::createFromTypes($real_values, $code_base, null)] : [];

        return UnionType::of(
            [self::createFromTypes($values, $code_base, null)],
            $real_type_set
        );
    }

    /**
     * @return ?UnionType returns the iterable key's union type if this is a subtype of iterable, null otherwise.
     * @override
     */
    public function iterableKeyUnionType(CodeBase $code_base): ?UnionType
    {
        $values = [];
        $real_values = [];
        foreach ($this->type_parts as $part) {
            $value = $part->iterableKeyUnionType($code_base);
            if ($value === null) {
                continue;
            }
            if ($value->typeCount() > 1) {
                return $value;
            }
            foreach ($value->getTypeSet() as $inner) {
                $values[] = $inner;
            }
            $real_type_set = $value->getRealTypeSet();
            if (count($real_type_set) > 1) {
                return $value;
            }
            foreach ($real_type_set as $inner) {
                $real_values[] = $inner;
            }
        }
        if (!$values) {
            return null;
        }
        $real_type_set = $real_values ? [self::createFromTypes($real_values, $code_base, null)] : [];

        return UnionType::of(
            [self::createFromTypes($values, $code_base, null)],
            $real_type_set
        );
    }

    private function anyTypePartsMatchMethod(string $method_name): bool
    {
        foreach ($this->type_parts as $part) {
            if ($part->{$method_name}()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param mixed ...$args
     * @no-named-arguments
     */
    private function anyTypePartsMatchMethodWithArgs(string $method_name, ...$args): bool
    {
        foreach ($this->type_parts as $part) {
            if ($part->{$method_name}(...$args)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @suppress PhanPluginUnknownArrayMethodParamType
     * @no-named-arguments
     */
    private function allTypePartsMatchMethodWithArgs(string $method_name, ...$args): bool
    {
        foreach ($this->type_parts as $part) {
            if (!$part->{$method_name}(...$args)) {
                return false;
            }
        }
        return true;
    }

    public function isSelfType(): bool
    {
        return $this->anyTypePartsMatchMethod(__FUNCTION__);
    }

    public function isStaticType(): bool
    {
        return $this->anyTypePartsMatchMethod(__FUNCTION__);
    }

    public function hasStaticOrSelfTypesRecursive(CodeBase $code_base): bool
    {
        return $this->anyTypePartsMatchMethodWithArgs(__FUNCTION__, $code_base);
    }

    public function isScalar(): bool
    {
        return $this->anyTypePartsMatchMethod(__FUNCTION__);
    }

    public function isValidBitwiseOperand(): bool
    {
        return $this->anyTypePartsMatchMethod(__FUNCTION__);
    }

    /**
     * @return bool
     * True if this type is a callable or a Closure.
     * @unused-param $code_base
     */
    public function isCallable(CodeBase $code_base): bool
    {
        return $this->anyTypePartsMatchMethod(__FUNCTION__);
    }

    /**
     * @return bool
     * True if this type is an object (or the phpdoc `object`)
     */
    public function isObject(): bool
    {
        // Probably always true?
        return $this->anyTypePartsMatchMethod(__FUNCTION__);
    }

    /**
     * Returns this type (or a subtype) converted to a type of an expression satisfying is_object(expr)
     * Returns null if Phan cannot cast this type to an object type.
     */
    public function asObjectType(): ?Type
    {
        return $this->mapTypePartsToOptionalType(static function (Type $type): ?Type {
            return $type->asObjectType();
        });
    }

    /**
     * @return bool
     * True if this type is possibly an object (or the phpdoc `object`)
     * This is the same as isObject(), except that it returns true for the exact class of IterableType.
     */
    public function isPossiblyObject(): bool
    {
        return $this->anyTypePartsMatchMethod(__FUNCTION__);
    }

    /**
     * Check if this type can possibly cast to the declared type, ignoring nullability of this type
     *
     * Precondition: This is either non-nullable or the type NullType/VoidType
     * @unused-param $context
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        return $this->anyTypePartsMatchMethodWithArgs(__FUNCTION__, $code_base, $context, $other);
    }

    public function canPossiblyCastToClass(CodeBase $code_base, Type $other): bool
    {
        return $this->anyTypePartsMatchMethodWithArgs(__FUNCTION__, $code_base, $other);
    }

    public function isIterable(CodeBase $code_base): bool
    {
        return $this->anyTypePartsMatchMethodWithArgs(__FUNCTION__, $code_base);
    }

    public function isCountable(CodeBase $code_base): bool
    {
        return $this->anyTypePartsMatchMethodWithArgs(__FUNCTION__, $code_base);
    }

    public function isTraversable(CodeBase $code_base): bool
    {
        return $this->anyTypePartsMatchMethodWithArgs(__FUNCTION__, $code_base);
    }

    public function asIterable(CodeBase $code_base): ?Type
    {
        return $this->mapTypePartsToOptionalType(static function (Type $type) use ($code_base): ?Type {
            return $type->asIterable($code_base);
        });
    }

    /**
     * @return FQSEN
     * A fully-qualified structural element name derived
     * from this type
     *
     * @see FullyQualifiedClassName::fromType() for a method that always returns FullyQualifiedClassName
     */
    public function asFQSEN(): FQSEN
    {
        foreach ($this->type_parts as $part) {
            if ($part->isObjectWithKnownFQSEN()) {
                return $part->asFQSEN();
            }
        }
        throw new AssertionError("Unexpected call to " . __METHOD__);
    }

    public function getTypesRecursively(): Generator
    {
        yield $this;
        yield from $this->type_parts;
    }

    public function asSignatureType(): Type
    {
        return $this->mapTypeParts(static function (Type $part): Type {
            return $part->asSignatureType();
        });
    }

    public function asCallableType(CodeBase $code_base): ?Type
    {
        return $this->mapTypePartsToOptionalType(static function (Type $part) use ($code_base): ?Type {
            return $part->asCallableType($code_base);
        });
    }

    public function weaklyOverlaps(Type $other, CodeBase $code_base): bool
    {
        return $this->anyTypePartsMatchMethodWithArgs(__FUNCTION__, $other, $code_base);
    }

    // TODO not implemented for intersection type to intersection type cast
    public function isTemplateSubtypeOf(Type $other): bool
    {
        return $this->anyTypePartsMatchMethodWithArgs(__FUNCTION__, $other);
    }

    public function isDefiniteNonCallableType(CodeBase $code_base): bool
    {
        return $this->anyTypePartsMatchMethodWithArgs(__FUNCTION__, $code_base);
    }

    public function isPossiblyIterable(CodeBase $code_base): bool
    {
        return $this->allTypePartsMatchMethodWithArgs(__FUNCTION__, $code_base);
    }

    public function withErasedUnionTypes(): Type
    {
        return $this->mapTypeParts(static function (Type $part): Type {
            return $part->withErasedUnionTypes();
        });
    }
}
