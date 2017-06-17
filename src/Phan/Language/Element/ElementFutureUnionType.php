<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Language\FutureUnionType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;

trait ElementFutureUnionType
{

    /**
     * @var FutureUnionType|null
     * A FutureUnionType is evaluated lazily only when
     * the type is actually needed. This lets us deal
     * with constants who's default type is another
     * constant who's type is not yet known during
     * parsing.
     */
    private $future_union_type = null;

    /**
     * @param UnionType $type
     * Set the type of this element
     *
     * @return void
     */
    abstract public function setUnionType(UnionType $type);

    /**
     * @return void
     */
    public function setFutureUnionType(
        FutureUnionType $future_union_type
    ) {
        $this->future_union_type = $future_union_type;
    }

    /**
     * @return UnionType|null
     * Get the UnionType from a future union type defined
     * on this object or null if there is no future
     * union type.
     */
    public function getFutureUnionType()
    {
        if (empty($this->future_union_type)) {
            return null;
        }

        // null out the future_union_type before
        // we compute it to avoid unbounded
        // recursion
        $future_union_type = $this->future_union_type;
        $this->future_union_type = null;

        $union_type = $future_union_type->get();

        // Don't set 'null' as the type if that's the default
        // given that its the default default.
        if ($union_type->isType(NullType::instance(false))) {
            $union_type = new UnionType();
        }

        return $union_type;
    }
}
