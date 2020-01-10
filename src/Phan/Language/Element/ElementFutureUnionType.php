<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Exception\IssueException;
use Phan\Language\FutureUnionType;
use Phan\Language\UnionType;

/**
 * Implements functionality of an element with a union type that is evaluated lazily.
 *
 * This lets Phan deal with elements (e.g. properties, constants)
 * where the default type is another constant with a type
 * that is not yet known during parsing.
 */
trait ElementFutureUnionType
{

    /**
     * @var FutureUnionType|null
     * A FutureUnionType is evaluated lazily only when
     * the type is actually needed.
     * This lets Phan deal with elements (e.g. properties, constants)
     * where the default type is another constant with a type
     * that is not yet known during parsing.
     */
    protected $future_union_type = null;

    /**
     * Set the type of this element
     * @param UnionType $type
     */
    abstract public function setUnionType(UnionType $type): void;

    /**
     * Sets a value that can be used once parsing/hydration is completed,
     * to resolve the union type of this element.
     */
    public function setFutureUnionType(
        FutureUnionType $future_union_type
    ): void {
        $this->future_union_type = $future_union_type;
    }

    /**
     * @return bool
     * Returns true if this element has an unresolved union type.
     *
     * @internal because this is mostly useful for Phan internals
     *           (e.g. a property with an unresolved future union type can't have a template type)
     */
    public function hasUnresolvedFutureUnionType(): bool
    {
        return $this->future_union_type !== null;
    }

    /**
     * @return UnionType|null
     * Get the UnionType from a future union type defined
     * on this object or null if there is no future
     * union type.
     */
    public function getFutureUnionType(): ?UnionType
    {
        $future_union_type = $this->future_union_type;
        if ($future_union_type === null) {
            return null;
        }

        // null out the future_union_type before
        // we compute it to avoid unbounded
        // recursion
        $this->future_union_type = null;

        try {
            return $future_union_type->get();
        } catch (IssueException $_) {
            return null;
        }
    }
}
