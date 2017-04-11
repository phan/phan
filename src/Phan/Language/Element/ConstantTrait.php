<?php declare(strict_types=1);
namespace Phan\Language\Element;

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
