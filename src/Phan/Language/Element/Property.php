<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Exception\IssueException;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope\PropertyScope;
use Phan\Language\UnionType;

use Closure;

class Property extends ClassElement
{
    use ElementFutureUnionType;
    use ClosedScopeElement;

    /**
     * @var ?FullyQualifiedPropertyName If this was originally defined in a trait, this is the trait's defining fqsen.
     * This is tracked separately from getDefiningFQSEN() in order to not break access checks on protected/private properties.
     * Used for dead code detection.
     */
    private $real_defining_fqsen;

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param string $name
     * The name of the typed structural element
     *
     * @param UnionType $type
     * A '|' delimited set of types satisfied by this
     * typed structural element.
     *
     * @param int $flags
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FullyQualifiedPropertyName $fqsen
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags,
            $fqsen
        );

        // Presume that this is the original definition
        // of this property, and let it be overwritten
        // if it isn't.
        $this->setDefiningFQSEN($fqsen);
        $this->real_defining_fqsen = $fqsen;

        // Set an internal scope, so that issue suppressions can be placed on property doc comments.
        // (plugins acting on properties would then pick those up).
        // $fqsen is used to locate this property.
        $this->setInternalScope(new PropertyScope(
            $context->getScope(),
            $fqsen
        ));
    }

    /**
     * @return FullyQualifiedPropertyName the FQSEN with the original definition (Even if this is private/protected and inherited from a trait). Used for dead code detection.
     *                                    Inheritance tests use getDefiningFQSEN() so that access checks won't break.
     *
     * @suppress PhanUnreferencedPublicMethod this is used, but the invocation could be one of multiple classes.
     * @suppress PhanPartialTypeMismatchReturn TODO: Allow subclasses to make property types more specific
     */
    public function getRealDefiningFQSEN() : FullyQualifiedPropertyName
    {
        return $this->real_defining_fqsen ?? $this->getDefiningFQSEN();
    }

    private function getVisibilityName() : string
    {
        if ($this->isPrivate()) {
            return 'private';
        } elseif ($this->isProtected()) {
            return 'protected';
        } else {
            return 'public';
        }
    }

    public function __toString() : string
    {
        $string = $this->getVisibilityName() . ' ';

        if ($this->isStatic()) {
            $string .= 'static ';
        }

        // Since the UnionType can be a future, and that
        // can throw an exception, we catch it and ignore it
        try {
            $union_type = $this->getUnionType()->__toString();
            if ($union_type !== '') {
                $string .= "$union_type ";
            } // Don't emit 2 spaces if there is no union type
        } catch (\Exception $_) {
            // do nothing
        }

        $string .= "\${$this->getName()}";

        return $string;
    }

    /**
     * Used for generating issue messages
     */
    public function asVisibilityAndFQSENString() : string
    {
        return $this->getVisibilityName() . ' ' . $this->asPropertyFQSENString();
    }

    /**
     * Used for generating issue messages
     */
    public function asPropertyFQSENString() : string
    {
        return $this->getClassFQSEN()->__toString() .
            ($this->isStatic() ? '::$' : '->') .
            $this->getName();
    }

    /**
     * Override the default getter to fill in a future
     * union type if available.
     * @throws IssueException if getFutureUnionType fails.
     */
    public function getUnionType() : UnionType
    {
        if (null !== ($union_type = $this->getFutureUnionType())) {
            $this->setUnionType(parent::getUnionType()->withUnionType($union_type->asNonLiteralType()));
        }

        return parent::getUnionType();
    }

    /**
     * @return FullyQualifiedPropertyName
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() : FullyQualifiedPropertyName
    {
        \assert(!empty($this->fqsen), "FQSEN must be defined");
        return $this->fqsen;
    }

    public function getMarkupDescription() : string
    {
        $string = $this->getVisibilityName() . ' ';

        if ($this->isStatic()) {
            $string .= 'static ';
        }

        $string .= "\${$this->getName()}";

        return $string;
    }


    public function toStub() : string
    {
        $string = '    ' . $this->getVisibilityName() . ' ';

        if ($this->isStatic()) {
            $string .= 'static ';
        }

        $string .= "\${$this->getName()}";
        $string .= ';';

        return $string;
    }

    /**
     * @internal - Used by daemon mode to restore an element to the state it had before parsing.
     * @return ?Closure
     */
    public function createRestoreCallback()
    {
        $future_union_type = $this->future_union_type;
        if ($future_union_type === null) {
            // We already inferred the type for this class constant/global constant.
            // Nothing to do.
            return null;
        }
        // If this refers to a class constant in another file,
        // the resolved union type might change if that file changes.
        return function () use ($future_union_type) {
            $this->future_union_type = $future_union_type;
            // Probably don't need to call setUnionType(mixed) again...
        };
    }

    /**
     * Returns true if at least one of the references to this property was **reading** the property
     *
     * Precondition: Config::get_track_references() === true
     */
    public function hasReadReference() : bool
    {
        return $this->getPhanFlagsHasState(Flags::WAS_PROPERTY_READ);
    }

    /**
     * @return void
     */
    public function setHasReadReference()
    {
        $this->enablePhanFlagBits(Flags::WAS_PROPERTY_READ);
    }

    /**
     * Returns true if at least one of the references to this property was **writing** the property
     *
     * Precondition: Config::get_track_references() === true
     */
    public function hasWriteReference() : bool
    {
        return $this->getPhanFlagsHasState(Flags::WAS_PROPERTY_WRITTEN);
    }

    /**
     * @return void
     */
    public function setHasWriteReference()
    {
        $this->enablePhanFlagBits(Flags::WAS_PROPERTY_WRITTEN);
    }

    /**
     * Copy addressable references from an element of the same subclass
     * @override
     * @return void
     */
    public function copyReferencesFrom(AddressableElement $element)
    {
        if ($this === $element) {
            // Should be impossible
            return;
        }
        \assert($element instanceof Property);
        foreach ($element->reference_list as $key => $file_ref) {
            $this->reference_list[$key] = $file_ref;
        }
        if ($element->hasReadReference()) {
            $this->setHasReadReference();
        }
        if ($element->hasWriteReference()) {
            $this->setHasWriteReference();
        }
    }

    /**
     * @return bool
     * True if this is a magic phpdoc property (declared via (at)property (-read,-write,) on class declaration phpdoc)
     */
    public function isFromPHPDoc() : bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_FROM_PHPDOC);
    }

    /**
     * @param bool $from_phpdoc - True if this is a magic phpdoc property (declared via (at)property (-read,-write,) on class declaration phpdoc)
     * @return void
     */
    public function setIsFromPHPDoc(bool $from_phpdoc)
    {
        $this->setPhanFlags(
            Flags::bitVectorWithState(
                $this->getPhanFlags(),
                Flags::IS_FROM_PHPDOC,
                $from_phpdoc
            )
        );
    }

    public function isDynamicProperty() : bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_DYNAMIC_PROPERTY);
    }

    /**
     * @return void
     */
    public function setIsDynamicProperty(bool $is_dynamic)
    {
        $this->setPhanFlags(
            Flags::bitVectorWithState(
                $this->getPhanFlags(),
                Flags::IS_DYNAMIC_PROPERTY,
                $is_dynamic
            )
        );
    }
}
