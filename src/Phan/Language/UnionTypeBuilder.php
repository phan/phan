<?php declare(strict_types=1);

namespace Phan\Language;

use Phan\Library\Set;

/**
 * Utilities to build a union type.
 * Mostly used internally when the number of types in the resulting union type may be large.
 *
 * @see UnionType::withType()
 * @see UnionType::withoutType()
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 * @phan-file-suppress PhanPluginNoCommentOnPublicMethod TODO: Add comments
 */
final class UnionTypeBuilder
{
    /** @var Set the list of unique types in this builder instance. */
    private $type_set;

    /** @param list<Type> $type_set (must be unique) */
    public function __construct(array $type_set = [])
    {
        $this->type_set = new Set($type_set);
    }

    public function addType(Type $type) : void
    {
        if ($this->type_set->contains($type)) {
            return;
        }
        $this->type_set->attach($type);
    }

    public function addUnionType(UnionType $union_type) : void
    {
        $old_type_set = $this->type_set;
        foreach ($union_type->getTypeSet() as $type) {
            if (!$old_type_set->contains($type)) {
                $this->type_set->attach($type);
            }
        }
    }

    public function removeType(Type $type) : void
    {
        $this->type_set->detach($type);
    }

    /**
     * Checks if this currently contains an empty list of types
     */
    public function isEmpty() : bool
    {
        return \count($this->type_set) === 0;
    }

    /**
     * @return list<Type>
     */
    public function getTypeSet() : array
    {
        return $this->type_set->toArray();
    }

    public function clearTypeSet() : void
    {
        $this->type_set = new Set();
    }

    /**
     * Build and return the UnionType for the unique type set that this was building.
     */
    public function getPHPDocUnionType() : UnionType
    {
        return UnionType::of($this->type_set->toArray(), []);
    }

    /**
     * Build and return the UnionType for the unique type set that this was building.
     * @deprecated use self::getPHPDocUnionType()
     */
    public function getUnionType() : UnionType
    {
        return self::getPHPDocUnionType();
    }
}
