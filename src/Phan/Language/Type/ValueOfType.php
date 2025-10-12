<?php

declare(strict_types=1);

namespace Phan\Language\Type;

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
        return $this->resolved_type_set ?? ($this->resolved_type_set = $this->computeResolvedTypeSet());
    }

    public function asPHPDocUnionType(): UnionType
    {
        return UnionType::of($this->asIndividualTypeInstances());
    }

    public function asRealUnionType(): UnionType
    {
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
