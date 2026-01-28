<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Generator;
use Phan\CodeBase;
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
        // Don't expand if inner type contains unresolved template types
        $inner_union = $this->template_parameter_type_list[0] ?? UnionType::empty();
        if ($inner_union->hasTemplateTypeRecursive()) {
            return [$this];
        }
        return $this->resolved_type_set ?? ($this->resolved_type_set = $this->computeResolvedTypeSet());
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
        // Create new KeyOfType with substituted inner type and resolve it
        return (new KeyOfType(
            '\\',
            'key-of',
            [$new_inner],
            $this->is_nullable
        ))->asPHPDocUnionType();
    }

    public function asPHPDocUnionType(): UnionType
    {
        // If inner type contains unresolved template types, don't expand
        // Create UnionType directly to avoid infinite recursion via UnionType::of
        $inner_union = $this->template_parameter_type_list[0] ?? UnionType::empty();
        if ($inner_union->hasTemplateTypeRecursive()) {
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
        // key-of<T> represents primitive values (array keys), not classes
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
        return false;
    }

    /**
     * @override
     */
    public function asFQSENString(): string
    {
        return 'key-of';
    }

    /**
     * key-of is not a class type, so it doesn't have parent classes to expand to.
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
