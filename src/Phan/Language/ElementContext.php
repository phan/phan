<?php declare(strict_types=1);

namespace Phan\Language;

use Phan\CodeBase;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\TypedElement;

/**
 * A context referring to an element that hasn't been created yet.
 * This is used when issues can be emitted before an object is added to the codebase.
 */
class ElementContext extends Context
{
    /** @var AddressableElement $element */
    private $element;
    public function __construct(AddressableElement $element)
    {
        $this->copyPropertiesFrom($element->getContext());
        $this->element = $element;
    }

    public function isInElementScope() : bool
    {
        return true;
    }

    public function getElementInScope(CodeBase $unused_code_base) : TypedElement
    {
        return $this->element;
    }

    public function isInGlobalScope() : bool
    {
        return false;
    }
}
