<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Closure;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Represents a template type that has not yet been resolved.
 * @see https://github.com/phan/phan/wiki/Generic-Types
 * @phan-pure
 */
final class TemplateType extends Type
{
    public const VARIANCE_INVARIANT = 0;
    public const VARIANCE_COVARIANT = 1;
    public const VARIANCE_CONTRAVARIANT = 2;

    /** @var string an identifier for the template type. */
    private $template_type_identifier;

    /** @var ?UnionType constraint/upper bound for this template type. */
    private $bound_union_type;

    /** @var int one of the VARIANCE_* constants */
    private $variance;

    /**
     * @param string $template_type_identifier
     * An identifier for the template type
     */
    protected function __construct(
        string $template_type_identifier,
        bool $is_nullable,
        ?UnionType $bound_union_type,
        int $variance
    ) {
        $this->template_type_identifier = $template_type_identifier;
        $this->is_nullable = $is_nullable;
        $this->bound_union_type = $bound_union_type;
        $this->variance = $variance;
    }

    /**
     * Create an instance for this ID
     */
    public static function instanceForId(string $id, bool $is_nullable, ?UnionType $bound_union_type = null, int $variance = self::VARIANCE_INVARIANT): TemplateType
    {
        if ($bound_union_type === null || $bound_union_type->isEmpty()) {
            if ($is_nullable) {
                static $nullable_cache = [];
                return $nullable_cache[$variance][$id] ?? ($nullable_cache[$variance][$id] = new self($id, true, null, $variance));
            }
            static $cache = [];
            return $cache[$variance][$id] ?? ($cache[$variance][$id] = new self($id, false, null, $variance));
        }

        $bound_key = $bound_union_type->generateUniqueId();
        if ($is_nullable) {
            static $nullable_bounded_cache = [];
            return $nullable_bounded_cache[$variance][$id][$bound_key] ?? ($nullable_bounded_cache[$variance][$id][$bound_key] = new self($id, true, $bound_union_type, $variance));
        }
        static $bounded_cache = [];
        return $bounded_cache[$variance][$id][$bound_key] ?? ($bounded_cache[$variance][$id][$bound_key] = new self($id, false, $bound_union_type, $variance));
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
    public function withIsNullable(bool $is_nullable): Type
    {
        if ($is_nullable === $this->is_nullable) {
            return $this;
        }

        return self::instanceForId(
            $this->template_type_identifier,
            $is_nullable,
            $this->bound_union_type,
            $this->variance
        );
    }

    /**
     * @return string
     * The name associated with this type
     */
    public function getName(): string
    {
        return $this->template_type_identifier;
    }

    /**
     * @return string
     * A string representation of this type in FQSEN form.
     * @override
     */
    public function asFQSENString(): string
    {
        return $this->template_type_identifier;
    }

    /**
     * @return string
     * The namespace associated with this type
     */
    public function getNamespace(): string
    {
        return '';
    }

    /**
     * Returns the declared constraint for this template type, if any.
     */
    public function getBoundUnionType(): ?UnionType
    {
        return $this->bound_union_type;
    }

    /**
     * Whether this template type declares a constraint.
     */
    public function hasBound(): bool
    {
        return $this->bound_union_type !== null && !$this->bound_union_type->isEmpty();
    }

    /**
     * Returns the variance mode (one of the VARIANCE_* constants).
     */
    public function getVariance(): int
    {
        return $this->variance;
    }

    /**
     * Returns true if this template type is declared covariant.
     */
    public function isCovariant(): bool
    {
        return $this->variance === self::VARIANCE_COVARIANT;
    }

    /**
     * Returns true if this template type is declared contravariant.
     */
    public function isContravariant(): bool
    {
        return $this->variance === self::VARIANCE_CONTRAVARIANT;
    }

    public function isObject(): bool
    {
        // Return true because we don't know, it may or may not be an object.
        // Not sure if this will be called.
        return false;
    }

    public function isObjectWithKnownFQSEN(): bool
    {
        // We have a template type ID, not an fqsen
        return false;
    }

    public function isPossiblyObject(): bool
    {
        return true;
    }

    /**
     * Returns true for `T` and `T[]` and `\MyClass<T>`, but not `\MyClass<\OtherClass>`
     *
     * Overridden in subclasses.
     */
    public function hasTemplateTypeRecursive(): bool
    {
        return true;
    }

    /**
     * @unused-param $code_base
     */
    public function getTemplateParameterTypeMap(CodeBase $code_base, bool $omit_missing = false): array
    {
        if (!$omit_missing) {
            return [
                $this->template_type_identifier => UnionType::empty()
            ];
        }
        return [
            $this->template_type_identifier => $this->asPHPDocUnionType()
        ];
    }

    /**
     * @param array<string,UnionType> $template_parameter_type_map
     * A map from template type identifiers to concrete types
     *
     * @return UnionType
     * This UnionType with any template types contained herein
     * mapped to concrete types defined in the given map.
     */
    public function withTemplateParameterTypeMap(
        array $template_parameter_type_map
    ): UnionType {
        $type = $template_parameter_type_map[$this->template_type_identifier] ?? $this->asPHPDocUnionType();
        if ($this->is_nullable) {
            return $type->withIsNullable(true);
        }
        return $type;
    }

    /**
     * Combine two closures that generate union types
     * @param ?Closure(mixed, Context):UnionType $left
     * @param ?Closure(mixed, Context):UnionType $right
     * @return ?Closure(mixed, Context):UnionType
     */
    public static function combineParameterClosures(?Closure $left, ?Closure $right): ?Closure
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
        return static function (mixed $params, Context $context) use ($left, $right): UnionType {
            return $left($params, $context)->withUnionType($right($params, $context));
        };
    }

    /**
     * Checks whether an instantiated template argument union satisfies a declared bound.
     *
     * @param array<string,bool> $seen_template_names used to prevent infinite recursion when templates reference each other
     */
    public static function unionTypeSatisfiesBound(
        CodeBase $code_base,
        UnionType $actual,
        UnionType $bound,
        array $seen_template_names = []
    ): bool {
        if ($bound->isEmpty()) {
            return true;
        }
        if ($actual->isEmpty()) {
            // The actual type couldn't be inferred (e.g. untyped argument),
            // so don't treat it as a violation. Other checks (e.g. PossiblyUndeclaredVariable)
            // already cover cases where a value may be undefined.
            return true;
        }
        // Allow mixed types to satisfy bounds (consistent with regular type checking).
        // Mixed could be compatible with any type at runtime, so we don't warn.
        // This minimizes false positives when type information is unavailable.
        if ($actual->hasMixedOrNonEmptyMixedType()) {
            return true;
        }

        foreach ($actual->getTypeSet() as $type) {
            if ($type instanceof self) {
                $name = $type->getName();
                if (isset($seen_template_names[$name])) {
                    // Avoid infinite recursion; assume satisfied if already checked.
                    continue;
                }
                $template_bound = $type->getBoundUnionType();
                if ($template_bound === null || $template_bound->isEmpty()) {
                    return false;
                }
                $seen_template_names[$name] = true;
                if (!self::unionTypeSatisfiesBound($code_base, $template_bound, $bound, $seen_template_names)) {
                    return false;
                }
                unset($seen_template_names[$name]);
                continue;
            }

            if ($type instanceof ClassStringType) {
                $class_union_type = $type->getClassUnionType();
                if (!$class_union_type->isEmpty()) {
                    if (self::unionTypeSatisfiesBound($code_base, $class_union_type, $bound, $seen_template_names)) {
                        continue;
                    }
                    // Fall through to the generic check below if the class-string itself may still match.
                } elseif (self::boundAcceptsAnyObject($bound)) {
                    // A bare class-string is compatible with a constraint of plain "object" (or nullable object).
                    continue;
                }
            }

            if (!$type->asPHPDocUnionType()->canCastToUnionType($bound, $code_base)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true if the bound is effectively "object" (optionally nullable),
     * meaning any object-like class-string should be accepted.
     */
    private static function boundAcceptsAnyObject(UnionType $bound): bool
    {
        $has_object = false;
        foreach ($bound->getTypeSet() as $type) {
            if ($type instanceof ObjectType) {
                $has_object = true;
                continue;
            }
            if ($type instanceof NullType) {
                continue;
            }
            return false;
        }
        return $has_object;
    }

    /**
     * @unused-param $code_base
     * @param TemplateType $template_type the template type that this union type is being searched for.
     *
     * @return ?Closure(UnionType, Context):UnionType a closure to map types to the template type wherever it was in the original union type
     */
    public function getTemplateTypeExtractorClosure(CodeBase $code_base, TemplateType $template_type): ?Closure
    {
        // For `@param T $param`, if passed a union type such as `int`, then `T` must be `int|false`
        if ($this === $template_type) {
            return static function (UnionType $type, Context $_): UnionType {
                return $type;
            };
        }
        // For `@param ?T $param`, if passed `?int|false`, then `T` must be `int|false`
        if ($this->withIsNullable(false) === $template_type) {
            return static function (UnionType $type, Context $_): UnionType {
                return $type->withIsNullable(false);
            };
        }
        // Overridden in subclasses
        return null;
    }

    /**
     * @override
     */
    public function canUseInRealSignature(): bool
    {
        return false;
    }

    /**
     * @unused-param $code_base
     * @unused-param $context
     * @unused-param $other
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        if (!$this->bound_union_type || $this->bound_union_type->isEmpty()) {
            return true;
        }

        return $this->bound_union_type->canCastToUnionType($other->asPHPDocUnionType(), $code_base);
    }

    /**
     * @param list<Type> $target_type_set
     */
    public function canCastToAnyTypeInSetWithoutConfig(array $target_type_set, CodeBase $code_base): bool
    {
        if (!$this->bound_union_type || $this->bound_union_type->isEmpty()) {
            return true;
        }

        foreach ($target_type_set as $type) {
            if ($this->bound_union_type->canCastToUnionType($type->asPHPDocUnionType(), $code_base)) {
                return true;
            }
        }
        return false;
    }

    public function isPossiblyFalsey(): bool
    {
        return true;
    }

    public function isAlwaysTruthy(): bool
    {
        return false;
    }

    public function isPossiblyNumeric(): bool
    {
        return true;
    }

    /**
     * Returns true if this could include the type `true`
     * (e.g. for `mixed`, `bool`, etc.)
     */
    public function isPossiblyTrue(): bool
    {
        return true;
    }

    /**
     * Returns true for types such as `mixed`, `bool`, `false`
     */
    public function isPossiblyFalse(): bool
    {
        return true;
    }

    public function isNullable(): bool
    {
        return true;
    }

    public function isNullableLabeled(): bool
    {
        return $this->is_nullable;
    }

    /**
     * @unused-param $other
     * @unused-param $code_base
     */
    public function weaklyOverlaps(Type $other, CodeBase $code_base): bool
    {
        return true;
    }

    /**
     * Template types are placeholders that will be resolved at call time.
     * They should be considered compatible with the declared signature type because:
     *
     * 1. The signature itself provides a runtime constraint on what the template can be
     * 2. Template constraints (e.g. "template SomeType of SomeClass") are semantic documentation
     *    rather than strict type bounds that Phan can verify at declaration time
     * 3. Actual type safety is enforced when the template is instantiated
     *
     * This prevents false positives for valid patterns like when a template type constrained
     * to object is used with an object signature.
     *
     * @param UnionType $union_type the real signature type to check against
     * @param Context $context
     * @param CodeBase $code_base
     * @return bool true since template types are compatible with signature constraints
     *
     * @unused-param $union_type
     * @unused-param $context
     * @unused-param $code_base
     * @override
     */
    public function isExclusivelyNarrowedFormOrEquivalentTo(
        UnionType $union_type,
        Context $context,
        CodeBase $code_base
    ): bool {
        // Template types are placeholders resolved at call time.
        // The signature provides the constraint, so always allow it.
        return true;
    }
}
