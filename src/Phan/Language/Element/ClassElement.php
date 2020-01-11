<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use AssertionError;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Exception\RecursionDepthException;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassElement;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\UnionType;
use TypeError;

/**
 * ClassElement is a base class of an element belonging to a class/trait/interface
 * (such as properties, methods, and class constants)
 */
abstract class ClassElement extends AddressableElement
{
    /** @var FullyQualifiedClassName the FQSEN of the class this ClassElement belongs to */
    private $class_fqsen;

    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FullyQualifiedClassElement $fqsen
    ) {
        parent::__construct($context, $name, $type, $flags, $fqsen);
        $this->class_fqsen = $fqsen->getFullyQualifiedClassName();
    }

    /**
     * @param FullyQualifiedClassElement $fqsen
     * @override
     * @suppress PhanParamSignatureMismatch deliberately more specific
     */
    public function setFQSEN(FQSEN $fqsen): void
    {
        if (!($fqsen instanceof FullyQualifiedClassElement)) {
            throw new TypeError('Expected $fqsen to be a subclass of Phan\Language\Element\FullyQualifiedClassElement');
        }
        parent::setFQSEN($fqsen);
        $this->class_fqsen = $fqsen->getFullyQualifiedClassName();
    }

    /**
     * @var FullyQualifiedClassElement|null
     * The FQSEN of this element where it is originally
     * defined.
     */
    private $defining_fqsen = null;

    /**
     * @return bool
     * True if this element has a defining FQSEN defined
     */
    public function hasDefiningFQSEN(): bool
    {
        return ($this->defining_fqsen != null);
    }

    /**
     * @return FullyQualifiedClassElement
     * The FQSEN of the original definition of this class element (before inheritance).
     */
    public function getDefiningFQSEN(): FullyQualifiedClassElement
    {
        if ($this->defining_fqsen === null) {
            throw new AssertionError('should check hasDefiningFQSEN');
        }
        return $this->defining_fqsen;
    }

    /**
     * Gets the real defining FQSEN.
     * This differs from getDefiningFQSEN() if the definition was from a trait.
     *
     * @return FullyQualifiedClassElement
     */
    public function getRealDefiningFQSEN()
    {
        return $this->getDefiningFQSEN();
    }

    /**
     * @return FullyQualifiedClassName
     * The FQSEN of of the class originally defining this class element.
     *
     * @throws CodeBaseException if this was called without first checking
     * if hasDefiningFQSEN()
     */
    public function getDefiningClassFQSEN(): FullyQualifiedClassName
    {
        if (\is_null($this->defining_fqsen)) {
            throw new CodeBaseException(
                $this->fqsen,
                "No defining class for {$this->fqsen}"
            );
        }
        return $this->defining_fqsen->getFullyQualifiedClassName();
    }

    /**
     * Sets the FQSEN of the class element in the location in which
     * the element was originally defined.
     *
     * @param FullyQualifiedClassElement $defining_fqsen
     */
    public function setDefiningFQSEN(
        FullyQualifiedClassElement $defining_fqsen
    ): void {
        $this->defining_fqsen = $defining_fqsen;
    }

    /**
     * @return Clazz
     * The class on which this element was originally defined
     * @throws CodeBaseException if hasDefiningFQSEN is false
     */
    public function getDefiningClass(CodeBase $code_base): Clazz
    {
        $class_fqsen = $this->getDefiningClassFQSEN();

        if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
            throw new CodeBaseException(
                $class_fqsen,
                "Defining class $class_fqsen for {$this->fqsen} not found"
            );
        }

        return $code_base->getClassByFQSEN($class_fqsen);
    }

    /**
     * @return FullyQualifiedClassName
     * The FQSEN of the class on which this element lives
     */
    public function getClassFQSEN(): FullyQualifiedClassName
    {
        return $this->class_fqsen;
    }

    /**
     * @param CodeBase $code_base
     * The code base with which to look for classes
     *
     * @return Clazz
     * The class that defined this element
     *
     * @throws CodeBaseException
     * An exception may be thrown if we can't find the
     * class
     */
    public function getClass(
        CodeBase $code_base
    ): Clazz {
        $class_fqsen = $this->class_fqsen;

        if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
            throw new CodeBaseException(
                $class_fqsen,
                "Defining class $class_fqsen for {$this->fqsen} not found"
            );
        }

        return $code_base->getClassByFQSEN($class_fqsen);
    }

    /**
     * @return bool
     * True if this element overrides another element
     */
    public function isOverride(): bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_OVERRIDE);
    }

    /**
     * True if this element overrides another element (deprecated)
     * @deprecated use isOverride
     * @suppress PhanUnreferencedPublicMethod
     */
    final public function getIsOverride(): bool
    {
        return $this->isOverride();
    }

    /**
     * Sets whether this element overrides another element
     *
     * @param bool $is_override
     * True if this element overrides another element
     */
    public function setIsOverride(bool $is_override): void
    {
        $this->setPhanFlags(Flags::bitVectorWithState(
            $this->getPhanFlags(),
            Flags::IS_OVERRIDE,
            $is_override
        ));
    }

    /**
     * @return bool
     * True if this is a static element
     */
    public function isStatic(): bool
    {
        return $this->getFlagsHasState(\ast\flags\MODIFIER_STATIC);
    }

    /**
     * @param CodeBase $code_base
     * The code base in which this element exists.
     *
     * @return bool
     * True if this is an internal element
     */
    public function isNSInternal(CodeBase $code_base): bool
    {
        return (
            parent::isNSInternal($code_base)
            || $this->getClass($code_base)->isNSInternal($code_base)
        );
    }

    public function getElementNamespace(): string
    {
        // Get the namespace that the class is within
        return $this->class_fqsen->getNamespace() ?: '\\';
    }

    /**
     * @param CodeBase $code_base used for access checks to protected properties
     * @param ?FullyQualifiedClassName $accessing_class_fqsen the class FQSEN of the current scope.
     *                                    null if in the global scope.
     * @return bool true if this can be accessed from the scope of $accessing_class_fqsen
     */
    public function isAccessibleFromClass(CodeBase $code_base, ?FullyQualifiedClassName $accessing_class_fqsen): bool
    {
        if ($this->isPublic()) {
            return true;
        }
        if (!$accessing_class_fqsen) {
            // Accesses from outside class scopes can only access public FQSENs
            return false;
        }
        $defining_fqsen = $this->getDefiningClassFQSEN();
        if ($defining_fqsen === $accessing_class_fqsen) {
            return true;
        }
        $real_defining_fqsen = $this->getRealDefiningFQSEN()->getFullyQualifiedClassName();
        if ($real_defining_fqsen === $accessing_class_fqsen) {
            return true;
        }
        if ($this->isPrivate()) {
            if ($code_base->hasClassWithFQSEN($defining_fqsen)) {
                $defining_class = $code_base->getClassByFQSEN($defining_fqsen);
                foreach ($defining_class->getTraitFQSENList() as $trait_fqsen) {
                    if ($trait_fqsen === $accessing_class_fqsen) {
                        return true;
                    }
                }
            }
            return false;
        }
        return self::checkCanAccessProtectedElement($code_base, $defining_fqsen, $accessing_class_fqsen);
    }

    /**
     * Check if a class can access a protected property defined in another class.
     *
     * Precondition: The property in $defining_fqsen is protected.
     */
    private static function checkCanAccessProtectedElement(CodeBase $code_base, FullyQualifiedClassName $defining_fqsen, FullyQualifiedClassName $accessing_class_fqsen): bool
    {
        $accessing_class_type = $accessing_class_fqsen->asType();
        $type_of_class_of_property = $defining_fqsen->asType();

        // If the definition of the property is protected,
        // then the subclasses of the defining class can access it.
        try {
            foreach ($accessing_class_type->asExpandedTypes($code_base)->getTypeSet() as $type) {
                if ($type->canCastToType($type_of_class_of_property)) {
                    return true;
                }
            }
            // and base classes of the defining class can access it
            foreach ($type_of_class_of_property->asExpandedTypes($code_base)->getTypeSet() as $type) {
                if ($type->canCastToType($accessing_class_type)) {
                    return true;
                }
            }
        } catch (RecursionDepthException $_) {
        }
        return false;
    }
}
