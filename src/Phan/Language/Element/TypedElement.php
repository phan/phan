<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FileRef;
use Phan\Language\UnionType;

/**
 * Any PHP structural element that also has a type and is
 * addressable such as a class, method, closure, property,
 * constant, variable, ...
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 * @phan-file-suppress PhanPluginNoCommentOnPublicMethod TODO: Add comments
 */
#[\AllowDynamicProperties]
abstract class TypedElement implements TypedElementInterface
{
    /**
     * @var string
     * The name of the typed structural element
     * @readonly
     */
    protected $name;

    /**
     * @var UnionType
     * A set of types satisfied by this typed structural
     * element.
     */
    private $type;

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
     * The Phan flags property contains node specific flags that
     * are internal to Phan.
     */
    private $phan_flags = 0;

    /**
     * @var Context
     * The context in which the structural element lives
     */
    private $context;

    /**
     * @var array<string,int>
     * A set of issues types to be suppressed.
     * Maps to the number of times an issue type was suppressed.
     */
    private $suppress_issue_list = [];

    /**
     * The phan flags whose changes do not change the state version (see getStateVersion()):
     * both are set again for every inherited copy of a method (Clazz::addMethod()).
     * @internal
     */
    public const UNVERSIONED_PHAN_FLAGS = Flags::IS_OVERRIDE | Flags::IS_OVERRIDDEN_BY_ANOTHER;

    /**
     * @var int
     * The version of the state owned by this element, see getStateVersion().
     */
    private $state_version = 0;

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
        int $flags
    ) {
        $this->context = clone($context);
        $this->name = $name;
        $this->type = $type;
        $this->flags = $flags;
        $this->setIsPHPInternal($context->isPHPInternal());
    }

    /**
     * After a clone is called on this object, clone our
     * type and fqsen so that they survive copies intact
     *
     * @return null
     */
    public function __clone()
    {
        $this->context = clone($this->context);
    }

    /**
     * Returns the version of the state that this element owns.
     *
     * The version is incremented by every setter, in-place mutation and cache fill of the state owned by this element
     * (in this class and its subclasses), so "has this element's owned state changed since version X"
     * is a cheap check. A later change uses it to create inherited method copies lazily from a remembered copy of
     * the method they are cloned from, which is only valid while that method's state is unchanged.
     *
     * Not covered: objects that are immutable or shared with clones (the Comment, Context, UnionType and ast\Node
     * objects), the counts of suppressed issues (incrementSuppressIssueCount()) and the phan flags in
     * UNVERSIONED_PHAN_FLAGS. Writes to a new object while it is created (e.g. in Method::fromNode() or on a clone
     * before it is returned) do not increment it either. A clone starts with the version of the element it was cloned from.
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
     * Sets the phan flags to $phan_flags, incrementing the state version
     * unless only bits in UNVERSIONED_PHAN_FLAGS change.
     */
    private function writePhanFlags(int $phan_flags): void
    {
        if (($this->phan_flags ^ $phan_flags) & ~self::UNVERSIONED_PHAN_FLAGS) {
            ++$this->state_version;
        }
        $this->phan_flags = $phan_flags;
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
     */
    public function getUnionType(): UnionType
    {
        return $this->type;
    }

    /**
     * @param UnionType $type
     * Set the type of this element
     *
     * TODO: A helper addUnionType(), accounting for variadic
     */
    public function setUnionType(UnionType $type): void
    {
        ++$this->state_version;
        $this->type = $type;
    }

    /**
     * Variables can't be variadic. This is the same as getUnionType for
     * variables, but not necessarily for subclasses. Method will return
     * the element type (such as `DateTime`) for variadic parameters.
     */
    public function getNonVariadicUnionType(): UnionType
    {
        return $this->getUnionType();
    }

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


    public function setFlags(int $flags): void
    {
        ++$this->state_version;
        $this->flags = $flags;
    }

    public function getPhanFlags(): int
    {
        return $this->phan_flags;
    }

    /**
     * @param int $flag
     * The flag we'd like to get the state for
     *
     * @return bool
     * True if all bits in the Phan flags are enabled in the bit
     * vector, else false.
     */
    public function getPhanFlagsHasState(int $flag): bool
    {
        return ($this->phan_flags & $flag) === $flag;
    }

    public function setPhanFlags(int $phan_flags): void
    {
        // Inlined writePhanFlags(), this is called frequently.
        if (($this->phan_flags ^ $phan_flags) & ~self::UNVERSIONED_PHAN_FLAGS) {
            ++$this->state_version;
        }
        $this->phan_flags = $phan_flags;
    }

    /**
     * @param int $bits combination of flags from Flags::* constants to enable
     */
    public function enablePhanFlagBits(int $bits): void
    {
        $this->writePhanFlags($this->phan_flags | $bits);
    }

    /**
     * @param int $bits combination of flags from Flags::* constants to disable
     * @suppress PhanUnreferencedPublicMethod keeping this for consistency
     */
    public function disablePhanFlagBits(int $bits): void
    {
        $this->writePhanFlags($this->phan_flags & ~$bits);
    }

    /**
     * @return Context
     * The context in which this structural element exists
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return FileRef
     * A reference to where this element was found
     */
    public function getFileRef(): FileRef
    {
        // TODO: Kill the context and make this a pure
        //       FileRef.
        return $this->context;
    }

    /**
     * @return bool
     * True if this element is marked as deprecated
     */
    public function isDeprecated(): bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_DEPRECATED);
    }

    /**
     * @param bool $is_deprecated
     * Set this element as deprecated
     */
    public function setIsDeprecated(bool $is_deprecated): void
    {
        $this->writePhanFlags(Flags::bitVectorWithState(
            $this->phan_flags,
            Flags::IS_DEPRECATED,
            $is_deprecated
        ));
    }

    /**
     * Set the set of issue names to suppress.
     * If the values are 0, the suppressions haven't been used yet.
     *
     * @param array<string,int> $suppress_issue_set
     */
    public function setSuppressIssueSet(array $suppress_issue_set): void
    {
        ++$this->state_version;
        $this->suppress_issue_list = $suppress_issue_set;
    }

    /**
     * @return array<string,int>
     */
    public function getSuppressIssueList(): array
    {
        return $this->suppress_issue_list ?: [];
    }

    /**
     * Increments the number of times $issue_name was suppressed.
     * (This does not change the state version, see getStateVersion())
     */
    public function incrementSuppressIssueCount(string $issue_name): void
    {
        ++Issue::$emit_attempt_count;
        ++$this->suppress_issue_list[$issue_name];
    }

    /**
     * @return bool
     * True if this element would like to suppress the given
     * issue name
     * @see self::checkHasSuppressIssueAndIncrementCount() for the most common usage
     */
    public function hasSuppressIssue(string $issue_name): bool
    {
        return isset($this->suppress_issue_list[$issue_name]);
    }

    /**
     * @return bool
     * True if this element would like to suppress the given
     * issue name.
     *
     * If this is true, this automatically calls incrementSuppressIssueCount.
     * Most callers should use this, except for uses similar to UnusedSuppressionPlugin.
     */
    public function checkHasSuppressIssueAndIncrementCount(string $issue_name): bool
    {
        if ($this->hasSuppressIssue($issue_name)) {
            $this->incrementSuppressIssueCount($issue_name);
            return true;
        }
        return false;
    }

    /**
     * @return bool
     * True if this was an internal PHP object
     */
    public function isPHPInternal(): bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_PHP_INTERNAL);
    }

    private function setIsPHPInternal(bool $is_internal): void
    {
        $this->writePhanFlags(Flags::bitVectorWithState(
            $this->phan_flags,
            Flags::IS_PHP_INTERNAL,
            $is_internal
        ));
    }

    /**
     * This method must be called before analysis
     * begins.
     * @unused-param $code_base
     * @suppress PhanUnreferencedPublicMethod not called directly, a future version may remove this.
     */
    public function hydrate(CodeBase $code_base): void
    {
        // Do nothing unless overridden
    }
}
