<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Language\FileRef;
use Phan\Language\UnionType;

/**
 * Trait for classes that wrap an element and proxy
 * calls to that element.
 * @property UnionType $type
 */
trait ElementProxyTrait
{
    /**
     * The element that was passed in as an argument (e.g. variable or static property)
     * @var TypedElement|UnaddressableTypedElement
     * TODO: Make a common interface which has methods implemented
     */
    private $element;

    /**
     * @param TypedElement|UnaddressableTypedElement $element
     */
    public function __construct($element) {
        $this->element = $element;
        $this->type = $element->getUnionType();
    }

    /**
     * @return int
     */
    public function getFlags(): int
    {
        return $this->element->getFlags();
    }

    /**
     * @param int $bits
     * @return bool
     */
    public function getFlagsHasState(int $bits): bool
    {
        return $this->element->getFlagsHasState($bits);
    }

    /**
     * @param int $flags
     */
    public function setFlags(int $flags): void
    {
        $this->element->setFlags($flags);
    }

    /**
     * @return int
     */
    public function getPhanFlags(): int
    {
        return $this->element->getPhanFlags();
    }

    /**
     * @param int $bits
     * @return bool
     */
    public function getPhanFlagsHasState(int $bits): bool
    {
        return $this->element->getPhanFlagsHasState($bits);
    }

    /**
     * @param int $phan_flags
     */
    public function setPhanFlags(int $phan_flags): void
    {
        $this->element->setPhanFlags($phan_flags);
    }

    /**
     * @return FileRef
     */
    public function getFileRef(): FileRef
    {
        return $this->element->getFileRef();
    }

    /**
     * Get the argument passed in to this object.
     * @return TypedElement|UnaddressableTypedElement
     */
    public function getElement()
    {
        return $this->element;
    }
}
