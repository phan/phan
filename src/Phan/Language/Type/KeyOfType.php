<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;

/**
 * Represents the utility type `key-of<T>` which resolves to the union of possible keys of `T`.
 */
final class KeyOfType extends \Phan\Language\Type implements MultiType
{
    /**
     * Memoized list of resolved key types for this instance.
     *
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
            self::addFallbackKeyTypes($builder);
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
                    $builder->addUnionType($type->getKeyUnionType());
                    continue;
                }
                if ($type instanceof GenericArrayInterface) {
                    $builder->addUnionType(GenericArrayType::unionTypeForKeyType($type->getKeyType()));
                    continue;
                }
                if ($type instanceof GenericIterableType) {
                    $builder->addUnionType($type->getKeyUnionType());
                    continue;
                }
                if ($type instanceof IterableType || $type instanceof ArrayType) {
                    $builder->addUnionType(GenericArrayType::unionTypeForKeyType(GenericArrayType::KEY_MIXED));
                    continue;
                }
                if ($type instanceof TemplateType) {
                    self::addFallbackKeyTypes($builder);
                    continue;
                }

                // Unknown types default to array-key
                self::addFallbackKeyTypes($builder);
            }
        }

        if ($builder->isEmpty()) {
            self::addFallbackKeyTypes($builder);
        }

        if ($this->is_nullable) {
            $builder->addType(NullType::instance(false));
        }

        $type_set = $builder->getTypeSet();
        if (!$type_set) {
            $type_set = [ArrayKeyType::instance(false)];
        }

        return $type_set;
    }

    private static function addFallbackKeyTypes(UnionTypeBuilder $builder): void
    {
        $builder->addType(ArrayKeyType::instance(false));
    }
}
