<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Config;
use Phan\Language\Context;
use Phan\Language\FileRef;
use Phan\Language\UnionType;
use Stringable;

/**
 * Any PHP structural element that also has a type and is
 * does not store a reference to its context (such as a variable).
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
#[\AllowDynamicProperties]
abstract class UnaddressableTypedElement implements Stringable
{
    /**
     * @var FileRef
     * The FileRef where this element lives. Will be instance of Context if
     * `record_variable_context_and_scope` is true.
     */
    private $file_ref;

    /**
     * @var string
     * The name of the typed structural element
     */
    private $name;

    /**
     * @var UnionType
     * A set of types satisfied by this typed structural
     * element.
     * Prefer using getUnionType() over $this->type - getUnionType() is overridden by VariadicParameter
     */
    protected $type;

    /**
     * @var int
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    private $flags = 0;

    /**
     * @var int
     * This property contains node specific flags that
     * are internal to Phan.
     */
    private $phan_flags = 0;

    /**
     * @var int
     * The version of the state owned by this element, see getStateVersion().
     */
    private $state_version = 0;

    /**
     * @param Context $context
     * The Context in which the structural element lives.
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
        FileRef $context,
        string $name,
        UnionType $type,
        int $flags
    ) {
        if (Config::getValue('record_variable_context_and_scope')) {
            // The full Context is being recorded for plugins such as the taint check plugin for wikimedia. Note that
            // 1. Fetching record_variable_context_and_scope is inlined for performance
            // 2. Phan allows phpdoc parameter types to be more specific than (e.g. subclasses of) real types.
            $this->file_ref = $context;
        } else {
            // Convert the Context to a FileRef, to avoid creating a reference
            // cycle that can't be garbage collected)
            $this->file_ref = FileRef::copyFileRef($context);
        }
        $this->name = $name;
        $this->type = $type;
        $this->flags = $flags;
    }

    /**
     * Returns the version of the state that this element owns.
     *
     * Same as TypedElement::getStateVersion(): incremented by every setter, in-place mutation and cache fill of
     * the state owned by this element (in this class and its subclasses), except for changes of the phan flags in
     * TypedElement::UNVERSIONED_PHAN_FLAGS. Shared and immutable objects (the UnionType, FileRef and default value
     * ast\Node objects) are not covered. A clone starts with the version of the element it was cloned from.
     */
    public function getStateVersion(): int
    {
        return $this->state_version;
    }

    /**
     * Records a change of the state owned by this element (see getStateVersion()).
     */
    protected function bumpStateVersion(): void
    {
        ++$this->state_version;
    }

    /**
     * @return string
     * The (not fully-qualified) name of this element.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return UnionType
     * Get the type of this structural element
     * If this is a parameter, get the variadic version seen inside the function.
     */
    public function getUnionType(): UnionType
    {
        return $this->type;
    }

    /**
     * @return UnionType
     * Get the type of this structural element.
     * If this is a parameter, get the non-variadic version seen by callers for checking arguments.
     *
     * @suppress PhanUnreferencedPublicMethod possibly used by PassByReferenceVariable
     */
    public function getNonVariadicUnionType(): UnionType
    {
        return $this->type;
    }

    /**
     * @param UnionType $type
     * Set the type of this element
     */
    public function setUnionType(UnionType $type): void
    {
        ++$this->state_version;
        $this->type = $type;
    }

    protected function convertToNullable(): void
    {
        // Avoid a redundant clone of nonNullableClone()
        $type = $this->type;
        if ($type->isEmpty() || $type->containsNullable()) {
            return;
        }
        ++$this->state_version;
        $this->type = $type->nullableClone();
    }

    /**
     * Returns the flags of the node declaring/defining this element.
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * @param int $flag
     * The flag we'd like to get the state for
     *
     * @return bool
     * True if all bits in the ast\Node flags are enabled in the bit
     * vector, else false.
     */
    public function getFlagsHasState(int $flag): bool
    {
        return ($this->flags & $flag) === $flag;
    }


    /**
     * @param int $flags
     *
     * @suppress PhanUnreferencedPublicMethod unused, other modifiers are used by Phan right now
     */
    public function setFlags(int $flags): void
    {
        ++$this->state_version;
        $this->flags = $flags;
    }

    /**
     * Records the Phan flags for this element
     * @see \Phan\Flags
     */
    public function getPhanFlags(): int
    {
        return $this->phan_flags;
    }

    /**
     * @param int $flag
     * The flag we'd like to get the state for
     *
     * @return bool
     * True if all bits in the phan flag are enabled in the bit
     * vector, else false.
     */
    public function getPhanFlagsHasState(int $flag): bool
    {
        return ($this->phan_flags & $flag) === $flag;
    }

    /**
     * @param int $phan_flags
     *
     * @suppress PhanUnreferencedPublicMethod potentially used in the future
     */
    public function setPhanFlags(int $phan_flags): void
    {
        if (($this->phan_flags ^ $phan_flags) & ~TypedElement::UNVERSIONED_PHAN_FLAGS) {
            ++$this->state_version;
        }
        $this->phan_flags = $phan_flags;
    }

    /**
     * Enable an individual bit of phan flags.
     */
    public function enablePhanFlagBits(int $new_bits): void
    {
        $phan_flags = $this->phan_flags | $new_bits;
        if (($this->phan_flags ^ $phan_flags) & ~TypedElement::UNVERSIONED_PHAN_FLAGS) {
            ++$this->state_version;
        }
        $this->phan_flags = $phan_flags;
    }

    /**
     * Disable an individual bit of phan flags.
     */
    public function disablePhanFlagBits(int $new_bits): void
    {
        $phan_flags = $this->phan_flags & ~$new_bits;
        if (($this->phan_flags ^ $phan_flags) & ~TypedElement::UNVERSIONED_PHAN_FLAGS) {
            ++$this->state_version;
        }
        $this->phan_flags = $phan_flags;
    }

    /**
     * @return FileRef
     * A reference to where this element was found. This will return a Context object if
     * `record_variable_context_and_scope` is true, and a FileRef otherwise.
     */
    public function getFileRef(): FileRef
    {
        return $this->file_ref;
    }

    /**
     * Returns whether this element stores Context and Scope.
     * @suppress PhanUnreferencedPublicMethod
     */
    public function storesContext(): bool
    {
        return Config::getValue('record_variable_context_and_scope');
    }

    abstract public function __toString(): string;
}
