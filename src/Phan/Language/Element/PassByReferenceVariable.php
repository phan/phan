<?php declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Analysis\AssignmentVisitor;
use Phan\CodeBase;
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

    /**
     * @var ?CodeBase set to a CodeBase if $element is a Property, for type checking
     */
    private $code_base;

    /**
     * @var ?Context set to a Context if $element is a Property, for emitting issues
     */
    private $context_of_created_reference;

    /**
     * @param TypedElement|UnaddressableTypedElement $element
     * NOTE: Non-null $code_base will be mandatory for $element Property in a future Phan release
     * NOTE: Non-null $context will be mandatory for $element Property in a future Phan release
     */
    public function __construct(
        Variable $parameter,
        $element,
        CodeBase $code_base = null,
        Context $context_of_created_reference = null
    ) {
        $this->parameter = $parameter;
        $this->element = $element;
        $this->type = $element->getNonVariadicUnionType();
        if ($element instanceof Property) {
            $this->code_base = $code_base;
            $this->context_of_created_reference = $context_of_created_reference;
        }
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
        return $this->type;
    }

    public function getUnionType() : UnionType
    {
        return $this->type;
    }

    /**
     * @suppress PhanAccessMethodInternal
     */
    public function setUnionType(UnionType $type) : void
    {
        $this->type = $type;
        if ($this->element instanceof Property && $this->code_base) {
            // TODO: Also warn about incompatible types
            AssignmentVisitor::addTypesToPropertyStandalone(
                $this->code_base,
                $this->element->getContext(),
                $this->element,
                $type
            );
            return;
        }
        $this->element->setUnionType($type->eraseRealTypeSetRecursively());
    }

    public function getFlags() : int
    {
        return $this->element->getFlags();
    }

    public function getFlagsHasState(int $bits) : bool
    {
        return $this->element->getFlagsHasState($bits);
    }

    public function setFlags(int $flags) : void
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

    public function setPhanFlags(int $phan_flags) : void
    {
        $this->element->setPhanFlags($phan_flags);
    }

    public function getFileRef() : FileRef
    {
        return $this->element->getFileRef();
    }

    /**
     * Gets the context (only set if this is a reference to an AddressableElement such as a property)
     * @deprecated - use getElement() instead and check if the result is an AddressableElement.
     * @throws \Error if the element is an UnaddressableElement
     * @suppress PhanPossiblyUndeclaredMethod
     * @suppress PhanUnreferencedPublicMethod not sure why
     */
    public function getContext() : Context
    {
        return $this->element->getContext();
    }

    /**
     * Returns the context where this reference was created.
     * This is currently only available for references to properties.
     */
    public function getContextOfCreatedReference() : ?Context
    {
        return $this->context_of_created_reference;
    }

    /**
     * Is the variable/property this is referring to part of a PHP module?
     * (only possible for properties)
     */
    public function isPHPInternal() : bool
    {
        return $this->element instanceof AddressableElement && $this->element->isPHPInternal();
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
