<?php

declare(strict_types=1);

namespace Phan\Language;

/**
 * Utilities to build a union type.
 * Mostly used internally when the number of types in the resulting union type may be large.
 *
 * @see UnionType::withType()
 * @see UnionType::withoutType()
 * @phan-file-suppress PhanPluginNoCommentOnPublicMethod TODO: Add comments
 */
final class UnionTypeBuilder
{
    /** @var list<Type> the list of non-unique types in this builder instance. */
    private $type_set;

    /** @param list<Type> $type_set (must be unique) */
    public function __construct(array $type_set = [])
    {
        $this->type_set = $type_set;
    }

    public function addType(Type $type): void
    {
        $this->type_set[] = $type;
    }

    public function addUnionType(UnionType $union_type): void
    {
        foreach ($union_type->getTypeSet() as $type) {
            $this->type_set[] = $type;
        }
    }

    public function removeType(Type $type): void
    {
        $found = false;
        foreach ($this->type_set as $i => $other_type) {
            if ($other_type === $type) {
                $found = true;
                unset($this->type_set[$i]);
            }
        }
        if ($found) {
            // deduplicate the properties
            $this->type_set = \array_values(UnionType::getUniqueTypes($this->type_set));
        }
    }

    /**
     * Checks if this currently contains an empty list of types
     */
    public function isEmpty(): bool
    {
        return \count($this->type_set) === 0;
    }

    /**
     * @return list<Type> as a side-effect, this deduplicates the type_set property.
     */
    public function getTypeSet(): array
    {
        $type_set = $this->type_set;
        if (\count($type_set) <= 1) {
            return $type_set;
        }
        return $this->type_set = UnionType::getUniqueTypes($type_set);
    }

    public function clearTypeSet(): void
    {
        $this->type_set = [];
    }

    /**
     * Build and return the UnionType for the unique type set that this was building.
     */
    public function getPHPDocUnionType(): UnionType
    {
        return UnionType::of($this->type_set, []);
    }

    /**
     * Build and return the UnionType for the unique type set that this was building.
     * @deprecated use self::getPHPDocUnionType()
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getUnionType(): UnionType
    {
        return self::getPHPDocUnionType();
    }
}
