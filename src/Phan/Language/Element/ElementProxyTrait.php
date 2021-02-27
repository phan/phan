<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Language\FileRef;
use Phan\Language\UnionType;

/**
 * Trait for classes that wrap an element and proxy
 * calls to that element.
 * @property UnionType $type
 * @phan-file-suppress PhanPluginNoCommentOnPublicMethod Undocumented methods are simple proxies
 * @phan-file-suppress PhanUnreferencedPublicMethod
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
    public function __construct($element)
    {
        $this->element = $element;
        $this->type = $element->getUnionType();
    }

    public function getFlags(): int
    {
        return $this->element->getFlags();
    }

    public function getFlagsHasState(int $bits): bool
    {
        return $this->element->getFlagsHasState($bits);
    }

    public function setFlags(int $flags): void
    {
        $this->element->setFlags($flags);
    }

    public function getPhanFlags(): int
    {
        return $this->element->getPhanFlags();
    }

    public function getPhanFlagsHasState(int $bits): bool
    {
        return $this->element->getPhanFlagsHasState($bits);
    }

    public function setPhanFlags(int $phan_flags): void
    {
        $this->element->setPhanFlags($phan_flags);
    }

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
