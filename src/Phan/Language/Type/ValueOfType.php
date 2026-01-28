<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Generator;
use Phan\CodeBase;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;

/**
 * Represents the utility type `value-of<T>` which resolves to the union of possible values of `T`.
 */
final class ValueOfType extends \Phan\Language\Type implements MultiType
{
    /**
     * @var ?array<int,\Phan\Language\Type>
     */
    private $resolved_type_set = null;

    /**
     * @param list<UnionType> $template_parameter_type_list
     */
    public function __construct(
        string $namespace,
        string $name,
        array $template_parameter_type_list,
        bool $is_nullable
    ) {
        if (\count($template_parameter_type_list) !== 1) {
            $template_parameter_type_list = [
                UnionType::merge($template_parameter_type_list ?: [UnionType::empty()], false)
            ];
        }
        parent::__construct($namespace, $name, $template_parameter_type_list, $is_nullable);
    }

    /**
     * @return non-empty-list<\Phan\Language\Type>
     */
    public function asIndividualTypeInstances(): array
    {
        // Don't expand if VALUE types contain unresolved template types
        // (Key types having templates is fine - we only care about values)
        $inner_union = $this->template_parameter_type_list[0] ?? UnionType::empty();
        if (self::hasTemplateInValuePosition($inner_union)) {
            return [$this];
        }
        return $this->resolved_type_set ?? ($this->resolved_type_set = $this->computeResolvedTypeSet());
    }

    /**
     * Check if any value/element types in the union have template types.
     * This ignores template types in key positions.
     */
    private static function hasTemplateInValuePosition(UnionType $union): bool
    {
        foreach ($union->getTypeSet() as $type) {
            if ($type instanceof TemplateType) {
                // The array type itself is a template - can't resolve values
                return true;
            }
            if ($type instanceof ArrayShapeType) {
                if ($type->genericArrayElementUnionType()->hasTemplateTypeRecursive()) {
                    return true;
                }
            } elseif ($type instanceof GenericArrayInterface) {
                if ($type->genericArrayElementUnionType()->hasTemplateTypeRecursive()) {
                    return true;
                }
            } elseif ($type instanceof GenericIterableType) {
                if ($type->getElementUnionType()->hasTemplateTypeRecursive()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param array<string,UnionType> $template_parameter_type_map
     */
    public function withTemplateParameterTypeMap(
        array $template_parameter_type_map
    ): UnionType {
        if (!$template_parameter_type_map) {
            return $this->asPHPDocUnionType();
        }
        $inner_union = $this->template_parameter_type_list[0] ?? UnionType::empty();
        $new_inner = $inner_union->withTemplateParameterTypeMap($template_parameter_type_map);
        if ($new_inner === $inner_union) {
            return $this->asPHPDocUnionType();
        }
        // Create new ValueOfType with substituted inner type and resolve it
        return (new ValueOfType(
            '\\',
            'value-of',
            [$new_inner],
            $this->is_nullable
        ))->asPHPDocUnionType();
    }

    public function asPHPDocUnionType(): UnionType
    {
        // If VALUE types contain unresolved template types, don't expand
        // Create UnionType directly to avoid infinite recursion via UnionType::of
        $inner_union = $this->template_parameter_type_list[0] ?? UnionType::empty();
        if (self::hasTemplateInValuePosition($inner_union)) {
            return new UnionType([$this], true);
        }
        return UnionType::of($this->asIndividualTypeInstances());
    }

    public function asRealUnionType(): UnionType
    {
        return $this->asPHPDocUnionType();
    }

    /**
     * @return Generator<\Phan\Language\Type>
     * @override
     */
    public function getReferencedClasses(): Generator
    {
        // value-of<T> represents primitive values, not classes
        // Yield referenced classes from inner type for validation purposes
        $inner_union = $this->template_parameter_type_list[0] ?? UnionType::empty();
        yield from $inner_union->getReferencedClasses();
    }

    /**
     * @override
     */
    public function isObject(): bool
    {
        return false;
    }

    /**
     * @override
     */
    public function isObjectWithKnownFQSEN(): bool
    {
        return false;
    }

    /**
     * @override
     */
    public function isPossiblyObject(): bool
    {
        $inner_union = $this->template_parameter_type_list[0] ?? UnionType::empty();
        // If VALUE types have unresolved templates, we don't know - assume possibly object
        if (self::hasTemplateInValuePosition($inner_union)) {
            return true;
        }
        // Otherwise, check if resolved types could produce objects
        foreach ($inner_union->getTypeSet() as $type) {
            if ($type instanceof ArrayShapeType) {
                if ($type->genericArrayElementUnionType()->hasPossiblyObjectTypes()) {
                    return true;
                }
            } elseif ($type instanceof GenericArrayInterface) {
                if ($type->genericArrayElementUnionType()->hasPossiblyObjectTypes()) {
                    return true;
                }
            } elseif ($type instanceof GenericIterableType) {
                if ($type->getElementUnionType()->hasPossiblyObjectTypes()) {
                    return true;
                }
            } elseif ($type instanceof IterableType || $type instanceof ArrayType || $type instanceof MixedType) {
                // Plain array/iterable/mixed without element type info - values could be objects
                return true;
            }
        }
        return false;
    }

    /**
     * @override
     */
    public function asFQSENString(): string
    {
        return 'value-of';
    }

    /**
     * value-of is not a class type, so it doesn't have parent classes to expand to.
     * @param CodeBase $code_base @phan-unused-param
     * @param int $recursion_depth @phan-unused-param
     * @param bool $preserving_template @phan-unused-param
     * @override
     */
    public function asExpandedTypes(
        CodeBase $code_base,
        int $recursion_depth = 0,
        bool $preserving_template = false
    ): UnionType {
        return $this->asPHPDocUnionType();
    }

    /**
     * @return non-empty-list<\Phan\Language\Type>
     */
    private function computeResolvedTypeSet(): array
    {
        $builder = new UnionTypeBuilder();
        $template_union = $this->template_parameter_type_list[0] ?? UnionType::empty();

        if ($template_union->isEmpty()) {
            self::addFallbackValueTypes($builder);
        } else {
            foreach ($template_union->getTypeSet() as $type) {
                if ($type instanceof NullType) {
                    $builder->addType(NullType::instance(false));
                    continue;
                }
                if ($type->isNullable()) {
                    $builder->addType(NullType::instance(false));
                    $type = $type->withIsNullable(false);
                }

                if ($type instanceof ArrayShapeType) {
                    $builder->addUnionType($type->genericArrayElementUnionType());
                    continue;
                }
                if ($type instanceof GenericArrayInterface) {
                    $builder->addUnionType($type->genericArrayElementUnionType());
                    continue;
                }
                if ($type instanceof GenericIterableType) {
                    $builder->addUnionType($type->getElementUnionType());
                    continue;
                }
                if ($type instanceof IterableType || $type instanceof ArrayType) {
                    $builder->addType(MixedType::instance(false));
                    continue;
                }
                if ($type instanceof TemplateType) {
                    self::addFallbackValueTypes($builder);
                    continue;
                }

                // Unknown types default to mixed
                self::addFallbackValueTypes($builder);
            }
        }

        if ($builder->isEmpty()) {
            self::addFallbackValueTypes($builder);
        }

        if ($this->is_nullable) {
            $builder->addType(NullType::instance(false));
        }

        $type_set = $builder->getTypeSet();
        if (!$type_set) {
            $type_set = [MixedType::instance(false)];
        }

        return $type_set;
    }

    private static function addFallbackValueTypes(UnionTypeBuilder $builder): void
    {
        $builder->addType(MixedType::instance(false));
    }
}
