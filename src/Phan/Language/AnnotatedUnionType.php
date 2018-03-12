<?php declare(strict_types=1);
namespace Phan\Language;

/**
 * This is used to represent a union type that has various annotations.
 * Currently, the only annotation is is_possibly_undefined, which is used in optional fields of array shapes.
 * (It might eventually be used for variables that are defined in some branches but not others)
 *
 * NOTE: Instances should be immutable after UnionType returns them.
 * They are not unique for combination of types and options.
 *
 * The internal representation may change in the future.
 */
class AnnotatedUnionType extends UnionType
{
    /** @var bool */
    protected $is_possibly_undefined = false;

    /**
     * @override
     */
    public function withIsPossiblyUndefined(bool $is_possibly_undefined) : UnionType
    {
        if ($is_possibly_undefined === $this->is_possibly_undefined) {
            return $this;
        }
        $result = clone($this);
        $result->is_possibly_undefined = $is_possibly_undefined;
        return $result;
    }

    public function getIsPossiblyUndefined() : bool
    {
        return $this->is_possibly_undefined;
    }

    public function __toString() : string
    {
        $result = parent::__toString();
        if ($this->is_possibly_undefined) {
            return $result . '=';
        }
        return $result;
    }
}
