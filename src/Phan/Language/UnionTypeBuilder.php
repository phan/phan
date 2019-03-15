<?php declare(strict_types=1);

namespace Phan\Language;

/**
 * Utilities to build a union type.
 * Mostly used internally when the number of types in the resulting union type may be large.
 *
 * @see UnionType::withType()
 * @see UnionType::withoutType()
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
final class UnionTypeBuilder
{
    /** @var array<int,Type> the list of unique types in this builder instance. */
    private $type_set;

    /** @param array<int,Type> $type_set (must be unique) */
    public function __construct(array $type_set = [])
    {
        $this->type_set = $type_set;
    }

    /**
     * @return void
     */
    public function addType(Type $type)
    {
        if (\in_array($type, $this->type_set, true)) {
            return;
        }
        $this->type_set[] = $type;
    }

    /**
     * @return void
     */
    public function addUnionType(UnionType $union_type)
    {
        $old_type_set = $this->type_set;
        foreach ($union_type->getTypeSet() as $type) {
            if (!\in_array($type, $old_type_set, true)) {
                $this->type_set[] = $type;
            }
        }
    }

    /**
     * @return void
     */
    public function removeType(Type $type)
    {
        $i = \array_search($type, $this->type_set, true);
        if ($i !== false) {
            // equivalent to unset($new_type_set[$i]) but fills in the gap in array keys.
            // TODO: How do other ways of unsetting the type affect performance on large projects?
            $replacement_type = \array_pop($this->type_set);
            if ($replacement_type !== $type) {
                // @phan-suppress-next-line PhanPartialTypeMismatchProperty $replacement_type is guaranteed to not be false
                $this->type_set[$i] = $replacement_type;
            }
        }
    }

    /**
     * Checks if this currently contains an empty list of types
     */
    public function isEmpty() : bool
    {
        return \count($this->type_set) === 0;
    }

    /**
     * @return array<int,Type>
     */
    public function getTypeSet() : array
    {
        return $this->type_set;
    }

    /**
     * Build and return the UnionType for the unique type set that this was building.
     */
    public function getUnionType() : UnionType
    {
        return UnionType::of($this->type_set);
    }
}
