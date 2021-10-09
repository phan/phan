<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use InvalidArgumentException;
use Phan\CodeBase;
use Phan\Debug\Frame;
use Phan\Exception\RecursionDepthException;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;

/**
 * A temporary representation of `array<KeyType, T1|T2...>`
 *
 * Callers should split this up into multiple GenericArrayType instances.
 *
 * This is generated from phpdoc `array<int, T1|T2>` where callers expect a subclass of Type.
 * @phan-pure
 */
final class GenericMultiType extends Type implements MultiType
{
    private const NAME = 'mixed';

    /**
     * @var non-empty-list<Type>
     * The list of possible types of every element in this array (2 or more)
     */
    private $types;


    /**
     * @param non-empty-list<Type> $types
     * The 2 or more possible types of every element in this array
     *
     * @throws InvalidArgumentException if there are less than 2 types in $types
     */
    protected function __construct(array $types)
    {
        if (\count($types) < 2) {
            throw new InvalidArgumentException('Expected $types to have at least 2 types');
        }
        // Could de-duplicate, but callers should be able to do that as well when converting to UnionType.
        // E.g. array<int|int> is int[].
        parent::__construct('\\', self::NAME, [], false);
        $this->types = $types;
    }

    /**
     * Create a Type or GenericMultiType from a type set
     * @param list<Type> $type_set
     */
    public static function fromTypeSet(array $type_set): Type
    {
        if (\count($type_set) === 1) {
            return \reset($type_set);
        } elseif (!$type_set) {
            throw new InvalidArgumentException('Expected $types to have at least 1 type');
        }
        return new self($type_set);
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
        $result = [];
        foreach ($this->types as $type) {
            if ($is_nullable) {
                $result[] = $type->withIsNullable(true);
            } elseif (!$type instanceof NullType && !$type instanceof VoidType) {
                $result[] = $type->withIsNullable(false);
            }
        }
        return self::fromTypeSet($result);
    }

    /**
     * @return non-empty-list<Type>
     * @override
     */
    public function asIndividualTypeInstances(): array
    {
        return $this->types;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        foreach ($this->types as $inner) {
            if ($inner->canCastToNonNullableType($type, $code_base)) {
                return true;
            }
        }
        return false;
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        foreach ($this->types as $inner) {
            if ($inner->canCastToNonNullableTypeWithoutConfig($type, $code_base)) {
                return true;
            }
        }
        return false;
    }

    public function __toString(): string
    {
        return '(' . \implode('|', $this->types) . ')';
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

        // TODO: Use UnionType::merge from a future change?
        $result = new UnionTypeBuilder();
        try {
            foreach ($this->types as $type) {
                $result->addUnionType($type->asExpandedTypes($code_base, $recursion_depth + 1));
            }
        } catch (RecursionDepthException $_) {
            return MixedType::instance(false)->asPHPDocUnionType();
        }
        return $result->getPHPDocUnionType();
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

        // TODO: Use UnionType::merge from a future change?
        $result = new UnionTypeBuilder();
        try {
            foreach ($this->types as $type) {
                $result->addUnionType($type->asExpandedTypesPreservingTemplate($code_base, $recursion_depth + 1));
            }
        } catch (RecursionDepthException $_) {
            return MixedType::instance(false)->asPHPDocUnionType();
        }
        return $result->getPHPDocUnionType();
    }
}
