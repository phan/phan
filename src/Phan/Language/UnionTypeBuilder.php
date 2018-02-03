<?php declare(strict_types=1);

namespace Phan\Language;

final class UnionTypeBuilder {
    /** @var array<int,Type> */
    private $type_set;

    /** @param array<int,Type> $type_set (must be unique) */
    public function __construct(array $type_set = []) {
        $this->type_set = $type_set;
    }

    /**
     * @return void
     */
    public function addType(Type $type) {
        if (\in_array($type, $this->type_set, true)) {
            return;
        }
        $this->type_set[] = $type;
    }

    /**
     * @return void
     */
    public function addUnionType(UnionType $union_type) {
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
    public function removeType(Type $type) {
        $i = \array_search($type, $this->type_set, true);
        if ($i !== false) {
            // equivalent to unset($new_type_set[$i]) but fills in the gap in array keys.
            // TODO: How do other versions affect performance on large projects?
            $replacement_type = \array_pop($this->type_set);
            if ($replacement_type !== $type) {
                $this->type_set[$i] = $replacement_type;
            }
        }
    }

    public function isEmpty() : bool {
        return \count($this->type_set) === 0;
    }

    /**
     * @return array<int,Type>
     */
    public function getTypeSet() : array {
        return $this->type_set;
    }

    public function getUnionType() : UnionType {
        return UnionType::of($this->type_set);
    }
}
