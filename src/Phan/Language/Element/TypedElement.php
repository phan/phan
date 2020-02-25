<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\CodeBase;
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
        $this->phan_flags = $phan_flags;
    }

    /**
     * @param int $bits combination of flags from Flags::* constants to enable
     */
    public function enablePhanFlagBits(int $bits): void
    {
        $this->phan_flags |= $bits;
    }

    /**
     * @param int $bits combination of flags from Flags::* constants to disable
     * @suppress PhanUnreferencedPublicMethod keeping this for consistency
     */
    public function disablePhanFlagBits(int $bits): void
    {
        $this->phan_flags &= (~$bits);
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
        $this->phan_flags = Flags::bitVectorWithState(
            $this->phan_flags,
            Flags::IS_DEPRECATED,
            $is_deprecated
        );
    }

    /**
     * Set the set of issue names to suppress.
     * If the values are 0, the suppressions haven't been used yet.
     *
     * @param array<string,int> $suppress_issue_set
     */
    public function setSuppressIssueSet(array $suppress_issue_set): void
    {
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
     */
    public function incrementSuppressIssueCount(string $issue_name): void
    {
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
        $this->phan_flags = Flags::bitVectorWithState(
            $this->phan_flags,
            Flags::IS_PHP_INTERNAL,
            $is_internal
        );
    }

    /**
     * This method must be called before analysis
     * begins.
     * @suppress PhanUnreferencedPublicMethod not called directly, a future version may remove this.
     */
    public function hydrate(CodeBase $unused_code_base): void
    {
        // Do nothing unless overridden
    }
}
