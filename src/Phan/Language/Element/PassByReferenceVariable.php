<?php declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Language\Context;
use Phan\Language\FileRef;
use Phan\Language\UnionType;

/**
 * This class wraps a parameter and an element and proxies
 * calls to the element but keeps the name of the parameter
 * allowing us to pass an element into a method as a
 * pass-by-reference parameter so that its value can be
 * updated when re-analyzing the method.
 */
class PassByReferenceVariable extends Variable
{

    /**
     * @var Variable the parameter which accepts references
     */
    private $parameter;

    /**
     * The element that was passed in as an argument (e.g. variable or static property)
     * @var TypedElement|UnaddressableTypedElement
     * TODO: Make a common interface which has methods implemented
     */
    private $element;

    /** @param TypedElement|UnaddressableTypedElement $element */
    public function __construct(
        Variable $parameter,
        $element
    ) {
        $this->parameter = $parameter;
        $this->element = $element;
    }

    public function getName() : string
    {
        return $this->parameter->getName();
    }

    /**
     * Variables can't be variadic. This is the same as getUnionType for
     * variables, but not necessarily for subclasses. Method will return
     * the element type (such as `DateTime`) for variadic parameters.
     */
    public function getNonVariadicUnionType() : UnionType
    {
        return $this->element->getNonVariadicUnionType();
    }

    public function getUnionType() : UnionType
    {
        return $this->element->getUnionType();
    }

    public function setUnionType(UnionType $type)
    {
        $this->element->setUnionType($type);
    }

    public function getFlags() : int
    {
        return $this->element->getFlags();
    }

    public function getFlagsHasState(int $bits) : bool
    {
        return $this->element->getFlagsHasState($bits);
    }

    public function setFlags(int $flags)
    {
        $this->element->setFlags($flags);
    }

    public function getPhanFlags() : int
    {
        return $this->element->getPhanFlags();
    }

    public function getPhanFlagsHasState(int $bits) : bool
    {
        return $this->element->getPhanFlagsHasState($bits);
    }

    public function setPhanFlags(int $phan_flags)
    {
        $this->element->setPhanFlags($phan_flags);
    }

    /**
     * Returns the context in which the element this is a reference to
     * was declared.
     */
    public function getContext() : Context
    {
        return $this->element->getContext();
    }

    public function getFileRef() : FileRef
    {
        return $this->element->getFileRef();
    }

    /**
     * Is the variable/property this is referring to part of a PHP module?
     * (only possible for properties)
     */
    public function isPHPInternal() : bool
    {
        return $this->element->isPHPInternal();
    }

    /**
     * Get the argument passed in to this object.
     * @return TypedElement|UnaddressableTypedElement
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getElement()
    {
        return $this->element;
    }
}
