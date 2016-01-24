<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\FutureUnionType;
use \Phan\Language\UnionType;
use \ast\Node;

trait ConstantTrait {
    use ElementFutureUnionType;

    /**
     * @return string
     * The (not fully-qualified) name of this element.
     */
    abstract public function getName() : string;

    public function __toString() : string
    {
        return 'const ' . $this->getName();
    }

}
