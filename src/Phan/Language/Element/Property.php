<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use Closure;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope\PropertyScope;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use TypeError;

/**
 * Phan's representation of a class/trait/interface's property (including magic and dynamic properties)
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 * @phan-file-suppress PhanPluginNoCommentOnPublicMethod TODO: Add comments
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
     * @var UnionType The real union type of this property (typed properties were added in PHP 7.4)
     * This does not change.
     */
    private $real_union_type;

    /**
     * @var ?UnionType the phpdoc union type of this property
     */
    private $phpdoc_union_type;

    /**
     * @var ?UnionType the default type
     */
    private $default_type;

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
        FullyQualifiedPropertyName $fqsen,
        UnionType $real_union_type
    ) {
        $internal_scope = new PropertyScope(
            $context->getScope(),
            $fqsen
        );
        $context = $context->withScope($internal_scope);
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
        $this->real_union_type = $real_union_type;

        // Set an internal scope, so that issue suppressions can be placed on property doc comments.
        // (plugins acting on properties would then pick those up).
        // $fqsen is used to locate this property.
        $this->setInternalScope($internal_scope);
    }

    /**
     * @return FullyQualifiedPropertyName the FQSEN with the original definition (Even if this is private/protected and inherited from a trait). Used for dead code detection.
     *                                    Inheritance tests use getDefiningFQSEN() so that access checks won't break.
     *
     * @suppress PhanPartialTypeMismatchReturn TODO: Allow subclasses to make property types more specific
     */
    public function getRealDefiningFQSEN(): FullyQualifiedPropertyName
    {
        return $this->real_defining_fqsen ?? $this->getDefiningFQSEN();
    }

    /**
     * Returns the visibility for this property (for issue messages and stubs)
     */
    public function getVisibilityName(): string
    {
        if ($this->isPrivate()) {
            return 'private';
        } elseif ($this->isProtected()) {
            return 'protected';
        } else {
            return 'public';
        }
    }

    public function __toString(): string
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

        $string .= "\${$this->name}";

        return $string;
    }

    /**
     * Returns a representation of the visibility for issue messages.
     */
    public function asVisibilityAndFQSENString(): string
    {
        return $this->getVisibilityName() . ' ' . $this->asPropertyFQSENString();
    }

    /**
     * Returns a representation of the property's FQSEN for issue messages.
     */
    public function asPropertyFQSENString(): string
    {
        return $this->getClassFQSEN()->__toString() .
            ($this->isStatic() ? '::$' : '->') .
            $this->name;
    }

    /**
     * @return string the representation of this FQSEN for issue messages.
     * @override
     */
    public function getRepresentationForIssue(): string
    {
        return $this->asPropertyFQSENString();
    }

    /**
     * Override the default getter to fill in a future
     * union type if available.
     * @throws IssueException if getFutureUnionType fails.
     */
    public function getUnionType(): UnionType
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
    public function getFQSEN(): FullyQualifiedPropertyName
    {
        return $this->fqsen;
    }

    public function getMarkupDescription(): string
    {
        $string = $this->getVisibilityName() . ' ';

        if ($this->isStatic()) {
            $string .= 'static ';
        }

        $string .= "\${$this->name}";

        return $string;
    }


    /**
     * Returns a stub declaration for this property that can be used to build a class stub
     * in `tool/make_stubs`.
     */
    public function toStub(): string
    {
        $string = '    ' . $this->getVisibilityName() . ' ';

        if ($this->isStatic()) {
            $string .= 'static ';
        }

        $string .= "\${$this->name}";
        $string .= ';';

        return $string;
    }

    /**
     * Used by daemon mode to restore an element to the state it had before parsing.
     * @internal
     */
    public function createRestoreCallback(): ?Closure
    {
        $future_union_type = $this->future_union_type;
        if ($future_union_type === null) {
            // We already inferred the type for this class constant/global constant.
            // Nothing to do.
            return null;
        }
        // If this refers to a class constant in another file,
        // the resolved union type might change if that file changes.
        return function () use ($future_union_type): void {
            $this->future_union_type = $future_union_type;
            // Probably don't need to call setUnionType(mixed) again...
        };
    }

    /**
     * Returns true if at least one of the references to this property was **reading** the property
     *
     * Precondition: Config::get_track_references() === true
     */
    public function hasReadReference(): bool
    {
        return $this->getPhanFlagsHasState(Flags::WAS_PROPERTY_READ);
    }

    public function setHasReadReference(): void
    {
        $this->enablePhanFlagBits(Flags::WAS_PROPERTY_READ);
    }

    /**
     * Returns true if at least one of the references to this property was **writing** the property
     *
     * Precondition: Config::get_track_references() === true
     */
    public function hasWriteReference(): bool
    {
        return $this->getPhanFlagsHasState(Flags::WAS_PROPERTY_WRITTEN);
    }

    public function setHasWriteReference(): void
    {
        $this->enablePhanFlagBits(Flags::WAS_PROPERTY_WRITTEN);
    }

    /**
     * Copy addressable references from an element of the same subclass
     * @override
     */
    public function copyReferencesFrom(AddressableElement $element): void
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
    protected const _IS_DYNAMIC_OR_MAGIC = Flags::IS_FROM_PHPDOC | Flags::IS_DYNAMIC_PROPERTY;

    /**
     * Equivalent to $this->isDynamicProperty() || $this->isFromPHPDoc()
     * i.e. this is a property that is not created from an AST_PROP_ELEM Node.
     */
    public function isDynamicOrFromPHPDoc(): bool
    {
        return ($this->getPhanFlags() & self::_IS_DYNAMIC_OR_MAGIC) !== 0;
    }

    /**
     * @return bool
     * True if this is a magic phpdoc property (declared via (at)property (-read,-write,) on class declaration phpdoc)
     */
    public function isFromPHPDoc(): bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_FROM_PHPDOC);
    }

    /**
     * @param bool $from_phpdoc - True if this is a magic phpdoc property (declared via (at)property (-read,-write,) on class declaration phpdoc)
     * @suppress PhanUnreferencedPublicMethod the caller now just sets all phan flags at once (including IS_READ_ONLY)
     */
    public function setIsFromPHPDoc(bool $from_phpdoc): void
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
     */
    public function setHasStaticInUnionType(bool $has_static): void
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
    public function hasStaticInUnionType(): bool
    {
        return $this->getPhanFlagsHasState(Flags::HAS_STATIC_UNION_TYPE);
    }

    /**
     * Was this property undeclared (and created at runtime)?
     */
    public function isDynamicProperty(): bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_DYNAMIC_PROPERTY);
    }

    /**
     * Is this property declared in a way hinting that it should only be written to?
     * (E.g. magic properties declared as (at)property-read, regular properties with (at)phan-read-only)
     */
    public function isReadOnly(): bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_READ_ONLY);
    }

    /**
     * Record whether this property is read-only.
     * TODO: Warn about combining IS_READ_ONLY and IS_WRITE_ONLY
     */
    public function setIsReadOnly(bool $is_read_only): void
    {
        $this->setPhanFlags(
            Flags::bitVectorWithState(
                $this->getPhanFlags(),
                Flags::IS_READ_ONLY,
                $is_read_only
            )
        );
    }

    /**
     * Is this property declared in a way hinting that it should only be written to?
     * (E.g. magic properties declared as (at)property-write, regular properties with (at)phan-write-only)
     */
    public function isWriteOnly(): bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_WRITE_ONLY);
    }

    public function setIsDynamicProperty(bool $is_dynamic): void
    {
        $this->setPhanFlags(
            Flags::bitVectorWithState(
                $this->getPhanFlags(),
                Flags::IS_DYNAMIC_PROPERTY,
                $is_dynamic
            )
        );
    }

    public function inheritStaticUnionType(FullyQualifiedClassName $old, FullyQualifiedClassName $new): void
    {
        $union_type = $this->getUnionType();
        foreach ($union_type->getTypeSet() as $type) {
            if (!$type->isObjectWithKnownFQSEN()) {
                continue;
            }
            if (FullyQualifiedClassName::fromType($type) === $old) {
                $union_type = $union_type
                    ->withoutType($type)
                    ->withType($new->asType()->withIsNullable($type->isNullable()));
            }
        }
        $this->setUnionType($union_type);
    }

    /**
     * @return UnionType|null
     * Get the UnionType from a future union type defined
     * on this object or null if there is no future
     * union type.
     * @override
     * @suppress PhanAccessMethodInternal
     */
    public function getFutureUnionType(): ?UnionType
    {
        $future_union_type = $this->future_union_type;
        if ($future_union_type === null) {
            return null;
        }

        // null out the future_union_type before
        // we compute it to avoid unbounded
        // recursion
        $this->future_union_type = null;

        try {
            $union_type = $future_union_type->get();
            if (!$this->real_union_type->isEmpty()
                && !$union_type->canStrictCastToUnionType($future_union_type->getCodeBase(), $this->real_union_type)) {
                    Issue::maybeEmit(
                        $future_union_type->getCodeBase(),
                        $future_union_type->getContext(),
                        Issue::TypeInvalidPropertyDefaultReal,
                        $future_union_type->getContext()->getLineNumberStart(),
                        $this->real_union_type,
                        $this->name,
                        $union_type
                    );
            }
        } catch (IssueException $_) {
            $union_type = UnionType::empty();
        }

        // Don't set 'null' as the type if that's the default
        // given that its the default.
        if ($union_type->isType(NullType::instance(false))) {
            $union_type = UnionType::empty();
        } else {
            $union_type = $union_type->eraseRealTypeSetRecursively();
        }

        return $union_type->withRealTypeSet($this->real_union_type->getTypeSet());
    }

    public function getRealUnionType(): UnionType
    {
        return $this->real_union_type;
    }

    public function setPHPDocUnionType(UnionType $type): void
    {
        $this->phpdoc_union_type = $type;
    }

    public function getPHPDocUnionType(): UnionType
    {
        return $this->phpdoc_union_type ?? UnionType::empty();
    }

    /**
     * Record the union type of the default value (for declared properties)
     */
    public function setDefaultType(UnionType $type): void
    {
        $this->default_type = $type;
    }

    /**
     * Return the recorded union type of the default value (for declared properties).
     * This is null if there is no declared type.
     * (TODO: Consider ways to represent an "undefined" state for php 7.4 typed properties)
     */
    public function getDefaultType(): ?UnionType
    {
        return $this->default_type;
    }
}
