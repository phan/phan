<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Language\UnionType;

/**
 * This class represents a global variable in a local scope, allowing to partially
 * proxy write reference to the global object.
 */
class GlobalVariable extends Variable
{
    use ElementProxyTrait;

    public function getName(): string
    {
        return $this->element->getName();
    }

    public function setUnionType(UnionType $type): void
    {
        $this->type = $type;
        // Always merge the type on the actual global.
        // Convert undefined to null before merging in the type.
        $this->element->setUnionType($this->element->getUnionType()->withUnionType($type->eraseRealTypeSetRecursively())->withIsPossiblyUndefined(false));
    }
}
