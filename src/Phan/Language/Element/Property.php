<?php declare(strict_types=1);

namespace Phan\Language\Element;

use Closure;
use Phan\Exception\IssueException;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope\PropertyScope;
use Phan\Language\UnionType;
use TypeError;

/**
 * Phan's representation of a class/trait/interface's property (including magic and dynamic properties)
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
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
     * @suppress PhanPartialTypeMismatchReturn TODO: Allow subclasses to make property types more specific
     */
    public function getRealDefiningFQSEN() : FullyQualifiedPropertyName
    {
        return $this->real_defining_fqsen ?? $this->getDefiningFQSEN();
    }

    /**
     * Returns the visibility for this property (for issue messages and stubs)
     */
    public function getVisibilityName() : string
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
     * @return string the representation of this FQSEN for issue messages.
     * @override
     */
    public function getRepresentationForIssue() : string
    {
        return $this->asPropertyFQSENString();
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


    /**
     * Returns a stub declaration for this property that can be used to build a class stub
     * in `tool/make_stubs`.
     */
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
     * Used by daemon mode to restore an element to the state it had before parsing.
     * @internal
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
        if (!($element instanceof Property)) {
            throw new TypeError('Expected $element to be Phan\Language\Element\Property in ' . __METHOD__);
        }
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
     * @internal
     */
    const _IS_DYNAMIC_OR_MAGIC = Flags::IS_FROM_PHPDOC | Flags::IS_DYNAMIC_PROPERTY;

    /**
     * Equivalent to $this->isDynamicProperty() || $this->isFromPHPDoc()
     * i.e. this is a property that is not created from an AST_PROP_ELEM Node.
     */
    public function isDynamicOrFromPHPDoc() : bool
    {
        return ($this->getPhanFlags() & self::_IS_DYNAMIC_OR_MAGIC) !== 0;
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
     * @suppress PhanUnreferencedPublicMethod the caller now just sets all phan flags at once (including IS_READ_ONLY)
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

    /**
     * Record whether this property contains `static` anywhere in the original union type.
     *
     * @param bool $has_static
     * @return void
     */
    public function setHasStaticInUnionType(bool $has_static)
    {
        $this->setPhanFlags(
            Flags::bitVectorWithState(
                $this->getPhanFlags(),
                Flags::HAS_STATIC_UNION_TYPE,
                $has_static
            )
        );
    }

    /**
     * Does this property contain `static` anywhere in the original union type?
     */
    public function getHasStaticInUnionType() : bool
    {
        return $this->getPhanFlagsHasState(Flags::HAS_STATIC_UNION_TYPE);
    }

    /**
     * Was this property undeclared (and created at runtime)?
     */
    public function isDynamicProperty() : bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_DYNAMIC_PROPERTY);
    }

    /**
     * Is this parameter declared in a way hinting that it should only be written to?
     * (E.g. magic properties declared as (at)property-read, regular properties with (at)phan-read-only)
     */
    public function isReadOnly() : bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_READ_ONLY);
    }

    /**
     * Is this parameter declared in a way hinting that it should only be written to?
     * (E.g. magic properties declared as (at)property-write, regular properties with (at)phan-write-only)
     */
    public function isWriteOnly() : bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_WRITE_ONLY);
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

    /**
     * @return void
     */
    public function inheritStaticUnionType(FullyQualifiedClassName $old, FullyQualifiedClassName $new)
    {
        $union_type = $this->getUnionType();
        foreach ($union_type->getTypeSet() as $type) {
            if (!$type->isObjectWithKnownFQSEN()) {
                continue;
            }
            if (FullyQualifiedClassName::fromType($type) === $old) {
                $union_type = $union_type
                    ->withoutType($type)
                    ->withType($new->asType()->withIsNullable($type->getIsNullable()));
            }
        }
        $this->setUnionType($union_type);
    }
}
