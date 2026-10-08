<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Closure;
use Phan\Analysis\ConditionVisitor;
use Phan\CodeBase;
use Phan\Language\Type;
use Phan\Language\Type\CallableArrayType;
use Phan\Language\Type\CallableObjectType;
use Phan\Language\Type\CallableStringType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;

/**
 * An immutable representation of a PHPStan/Psalm-style conditional return type, e.g.
 *
 *     (at)return ($param is null ? Foo : Bar)
 *     (at)return ($param is not string ? Foo : ($other is int ? Baz : Qux))
 *
 * Either branch may itself be a nested ConditionalReturnType.
 *
 * @phan-pure
 */
final class ConditionalReturnType
{
    /** @var string the parameter name being tested, without the leading '$' */
    private $param_name;

    /** @var UnionType the type the parameter is compared against */
    private $condition;

    /** @var bool true for `$param is not Type` */
    private $negated;

    /** @var UnionType|ConditionalReturnType the result when the condition holds */
    private $if_true;

    /** @var UnionType|ConditionalReturnType the result when the condition does not hold */
    private $if_false;

    /** @var int the line of the annotation this was parsed from (0 if unknown) */
    private $lineno;

    public function __construct(
        string $param_name,
        UnionType $condition,
        bool $negated,
        UnionType|ConditionalReturnType $if_true,
        UnionType|ConditionalReturnType $if_false,
        int $lineno = 0
    ) {
        $this->param_name = $param_name;
        $this->condition = $condition;
        $this->negated = $negated;
        $this->if_true = $if_true;
        $this->if_false = $if_false;
        $this->lineno = $lineno;
    }

    /** The line of the annotation this was parsed from (0 if unknown) */
    public function getLineno(): int
    {
        return $this->lineno;
    }

    /** The name of the parameter being tested, without the leading '$' */
    public function getParamName(): string
    {
        return $this->param_name;
    }

    /** The type the parameter is compared against */
    public function getCondition(): UnionType
    {
        return $this->condition;
    }

    /** True for `$param is not Type` */
    public function isNegated(): bool
    {
        return $this->negated;
    }

    /** The result when the condition holds */
    public function getIfTrue(): UnionType|ConditionalReturnType
    {
        return $this->if_true;
    }

    /** The result when the condition does not hold */
    public function getIfFalse(): UnionType|ConditionalReturnType
    {
        return $this->if_false;
    }

    /**
     * The union of every branch's type. This is what callers that can't see the arguments
     * (and every pre-existing consumer of the return type) should use.
     */
    public function asFlattenedUnionType(): UnionType
    {
        return self::flatten($this->if_true)->withUnionType(self::flatten($this->if_false));
    }

    private static function flatten(UnionType|ConditionalReturnType $branch): UnionType
    {
        return $branch instanceof self ? $branch->asFlattenedUnionType() : $branch;
    }

    /**
     * Returns a copy with $mapper applied to the condition type and every leaf type.
     * Used for template substitution and self resolution.
     *
     * @param Closure(UnionType):UnionType $mapper
     */
    public function mapTypes(Closure $mapper): self
    {
        $new_condition = $mapper($this->condition);
        $new_if_true = $this->if_true instanceof self ? $this->if_true->mapTypes($mapper) : $mapper($this->if_true);
        $new_if_false = $this->if_false instanceof self ? $this->if_false->mapTypes($mapper) : $mapper($this->if_false);
        if ($new_condition === $this->condition && $new_if_true === $this->if_true && $new_if_false === $this->if_false) {
            return $this;
        }
        return new self($this->param_name, $new_condition, $this->negated, $new_if_true, $new_if_false, $this->lineno);
    }

    /**
     * Returns a copy with the members of $plain_type that this conditional doesn't already mention added to every leaf.
     * Used for `@return A|B|false` combined with `@psalm-return ($x is null ? A : B)`: `false` is possible for every call.
     */
    public function withPlainAnnotationTypes(UnionType $plain_type): self
    {
        $extra_types = $plain_type;
        foreach ($this->asFlattenedUnionType()->getTypeSet() as $type) {
            $extra_types = $extra_types->withoutType($type);
        }
        if ($extra_types->isEmpty()) {
            return $this;
        }
        return $this->mapLeafTypes(static function (UnionType $type) use ($extra_types): UnionType {
            return $type->withUnionType($extra_types);
        });
    }

    /**
     * Returns a copy with every member of $union_type added to every leaf.
     * Used when a docblock has a second conditional annotation: its types are treated as always possible.
     */
    public function withTypesAddedToLeaves(UnionType $union_type): self
    {
        if ($union_type->isEmpty()) {
            return $this;
        }
        return $this->mapLeafTypes(static function (UnionType $type) use ($union_type): UnionType {
            return $type->withUnionType($union_type);
        });
    }

    /**
     * Returns a copy with $mapper applied to every leaf type (but not to the conditions).
     * @param Closure(UnionType):UnionType $mapper
     */
    private function mapLeafTypes(Closure $mapper): self
    {
        $new_if_true = $this->if_true instanceof self ? $this->if_true->mapLeafTypes($mapper) : $mapper($this->if_true);
        $new_if_false = $this->if_false instanceof self ? $this->if_false->mapLeafTypes($mapper) : $mapper($this->if_false);
        return new self($this->param_name, $this->condition, $this->negated, $new_if_true, $new_if_false, $this->lineno);
    }

    /**
     * Returns a copy where parameter names are renamed according to $name_map (old name => new name).
     * Used when an overriding method renames the parameters of the method it inherits the conditional from.
     *
     * @param array<string,string> $name_map
     */
    public function withRenamedParams(array $name_map): self
    {
        $new_name = $name_map[$this->param_name] ?? $this->param_name;
        $new_if_true = $this->if_true instanceof self ? $this->if_true->withRenamedParams($name_map) : $this->if_true;
        $new_if_false = $this->if_false instanceof self ? $this->if_false->withRenamedParams($name_map) : $this->if_false;
        if ($new_name === $this->param_name && $new_if_true === $this->if_true && $new_if_false === $this->if_false) {
            return $this;
        }
        return new self($new_name, $this->condition, $this->negated, $new_if_true, $new_if_false, $this->lineno);
    }

    /**
     * @return list<string> every parameter name referenced by this conditional (without '$'), deduplicated
     */
    public function getParamNames(): array
    {
        $names = [$this->param_name => true];
        foreach ([$this->if_true, $this->if_false] as $branch) {
            if ($branch instanceof self) {
                foreach ($branch->getParamNames() as $name) {
                    $names[$name] = true;
                }
            }
        }
        return \array_keys($names);
    }

    /**
     * True if the condition or any branch mentions a template type.
     */
    public function hasTemplateTypeRecursive(): bool
    {
        if ($this->condition->hasTemplateTypeRecursive()) {
            return true;
        }
        foreach ([$this->if_true, $this->if_false] as $branch) {
            if ($branch->hasTemplateTypeRecursive()) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if the condition or any branch uses the template type $template_type.
     */
    public function usesTemplateType(TemplateType $template_type): bool
    {
        if ($this->condition->usesTemplateType($template_type)) {
            return true;
        }
        foreach ([$this->if_true, $this->if_false] as $branch) {
            if ($branch->usesTemplateType($template_type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolve this conditional for a specific call.
     *
     * @param Closure(string):(?UnionType) $arg_type_lookup
     *        Given a parameter name (without '$'), returns the union type of the corresponding argument,
     *        or null if it can't be determined (e.g. argument unpacking, unknown parameter).
     */
    public function resolve(CodeBase $code_base, Closure $arg_type_lookup): UnionType
    {
        $arg_type = $arg_type_lookup($this->param_name);
        $if_true = $this->if_true;
        $if_false = $this->if_false;
        if ($this->negated) {
            [$if_true, $if_false] = [$if_false, $if_true];
        }
        $verdict = $arg_type !== null ? self::evaluateCondition($code_base, $arg_type, $this->condition) : null;
        if ($verdict === true) {
            return self::resolveBranch($code_base, $if_true, $arg_type_lookup);
        }
        if ($verdict === false) {
            return self::resolveBranch($code_base, $if_false, $arg_type_lookup);
        }
        // Ambiguous: a nested condition on the same parameter only sees the part of the argument type
        // that reaches its branch, e.g. `($x is int ? A : ($x is string ? B : C))` with `int|string` gives `A|B`.
        $matching_lookup = $arg_type_lookup;
        $remaining_lookup = $arg_type_lookup;
        if ($arg_type !== null) {
            $matching_lookup = $this->lookupWithNarrowedSubject($arg_type_lookup, self::narrowToCondition($code_base, $arg_type, $this->condition));
            $remaining_lookup = $this->lookupWithNarrowedSubject($arg_type_lookup, self::excludeCondition($code_base, $arg_type, $this->condition));
        }
        return self::resolveBranch($code_base, $if_true, $matching_lookup)->withUnionType(
            self::resolveBranch($code_base, $if_false, $remaining_lookup)
        );
    }

    /**
     * @param Closure(string):(?UnionType) $arg_type_lookup
     * @return Closure(string):(?UnionType) the same lookup, but returning $narrowed_type for this conditional's parameter
     */
    private function lookupWithNarrowedSubject(Closure $arg_type_lookup, ?UnionType $narrowed_type): Closure
    {
        if ($narrowed_type === null) {
            return $arg_type_lookup;
        }
        $param_name = $this->param_name;
        return static function (string $name) use ($arg_type_lookup, $param_name, $narrowed_type): ?UnionType {
            return $name === $param_name ? $narrowed_type : $arg_type_lookup($name);
        };
    }

    /**
     * @return ?UnionType the part of $arg_type that may satisfy `is $condition`, or null if that can't be narrowed
     */
    private static function narrowToCondition(CodeBase $code_base, UnionType $arg_type, UnionType $condition): ?UnionType
    {
        if ($arg_type->hasMixedOrNonEmptyMixedType()) {
            return $condition;
        }
        $narrowed = UnionType::empty();
        foreach (self::withNullSplitOff($arg_type)->getTypeSet() as $type) {
            $type_as_union = $type->asPHPDocUnionType();
            if (!$type_as_union->hasAnyTypeOverlap($code_base, $condition)) {
                continue;
            }
            if ($condition->isStrictSubtypeOf($code_base, $type_as_union)) {
                // e.g. `int` tested with `is positive-int`: only the positive-int part reaches the branch.
                $narrowed = $narrowed->withUnionType($condition);
            } else {
                $narrowed = $narrowed->withType($type);
            }
        }
        return $narrowed->isEmpty() ? null : $narrowed;
    }

    /**
     * @return ?UnionType the part of $arg_type that may fail `is $condition`, or null if that can't be narrowed
     */
    private static function excludeCondition(CodeBase $code_base, UnionType $arg_type, UnionType $condition): ?UnionType
    {
        $remaining = ConditionVisitor::excludeMatchingTypes($code_base, self::withNullSplitOff($arg_type), $condition);
        return $remaining->isEmpty() ? null : $remaining;
    }

    /**
     * `?string` is one type to Phan; represent it as `string|null` so that `$x is null` / `$x is string`
     * can keep or drop each part separately.
     */
    private static function withNullSplitOff(UnionType $union_type): UnionType
    {
        if (!$union_type->containsNullable()) {
            return $union_type;
        }
        return $union_type->nonNullableClone()->withType(NullType::instance(false));
    }

    /**
     * @param Closure(string):(?UnionType) $arg_type_lookup
     */
    private static function resolveBranch(CodeBase $code_base, UnionType|ConditionalReturnType $branch, Closure $arg_type_lookup): UnionType
    {
        return $branch instanceof self ? $branch->resolve($code_base, $arg_type_lookup) : $branch;
    }

    /**
     * @return ?bool true if $arg_type definitely satisfies `is $condition`,
     *               false if it definitely does not,
     *               null if it can't be decided (the result is the union of both branches)
     */
    private static function evaluateCondition(CodeBase $code_base, UnionType $arg_type, UnionType $condition): ?bool
    {
        if ($arg_type->isEmpty() || $condition->isEmpty() || $arg_type->hasMixedOrNonEmptyMixedType() || $arg_type->hasTemplateTypeRecursive()) {
            return null;
        }
        if (self::isCallableCondition($condition)) {
            return self::evaluateCallableCondition($code_base, $arg_type, $condition);
        }
        if ($arg_type->isStrictSubtypeOf($code_base, $condition)) {
            return true;
        }
        if ($arg_type->hasAnyTypeOverlap($code_base, $condition)) {
            return null;
        }
        // The types are disjoint as declared. Two object types can still be satisfied at runtime by a
        // subclass implementing both (e.g. an unrelated interface), so only treat that as ambiguous.
        if ($arg_type->hasObjectTypes() && $condition->hasObjectTypes()) {
            return null;
        }
        return false;
    }

    /**
     * True if $condition consists only of `callable`, `callable-string`, `callable-array` or `callable-object`.
     */
    private static function isCallableCondition(UnionType $condition): bool
    {
        return !$condition->hasTypeMatchingCallback(static function (Type $type): bool {
            return !($type instanceof CallableType || $type instanceof CallableStringType || $type instanceof CallableArrayType || $type instanceof CallableObjectType);
        });
    }

    /**
     * `$x is callable` for a string or array argument can only be decided at runtime, but Phan's cast rules let any
     * non-empty string or array cast to `callable`. So only types known to be callable (Closure, callable-string,
     * classes with __invoke(), ...) select the first branch, only types that can't be callable (int, false, a string
     * literal that isn't a function name, ...) or can't be the specific kind of callable (a Closure for
     * `callable-string`) select the second, and everything else keeps both.
     */
    private static function evaluateCallableCondition(CodeBase $code_base, UnionType $arg_type, UnionType $condition): ?bool
    {
        $all_callable = true;
        $all_non_callable = true;
        foreach ($arg_type->getTypeSet() as $type) {
            if ($type->isNullable() || !$type->isCallable($code_base)) {
                $all_callable = false;
            }
            if (!$type->isDefiniteNonCallableType($code_base)) {
                $all_non_callable = false;
            }
        }
        if ($all_callable && $arg_type->isStrictSubtypeOf($code_base, $condition)) {
            return true;
        }
        // e.g. a Closure can never be a callable-string
        if ($all_non_callable || !$arg_type->hasAnyTypeOverlap($code_base, $condition)) {
            return false;
        }
        return null;
    }

    /** The PHPStan syntax for this conditional, e.g. `($x is null ? int : string)` */
    public function __toString(): string
    {
        return '($' . $this->param_name . ' is ' . ($this->negated ? 'not ' : '') . $this->condition
            . ' ? ' . $this->if_true . ' : ' . $this->if_false . ')';
    }
}
