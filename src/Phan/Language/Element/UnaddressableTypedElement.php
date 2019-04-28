<?php declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Language\FileRef;
use Phan\Language\UnionType;

/**
 * Any PHP structural element that also has a type and is
 * does not store a reference to its context (such as a variable).
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
abstract class UnaddressableTypedElement
{
    /**
     * @var FileRef
     * Reference to the file and line number in which the structural element lives
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
     * @param FileRef $file_ref
     * The Context or FileRef in which the structural element lives
     * (Will be converted to FileRef, to avoid creating a reference
     * cycle that can't be garbage collected)
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
        FileRef $file_ref,
        string $name,
        UnionType $type,
        int $flags
    ) {
        $this->file_ref = FileRef::copyFileRef($file_ref);
        $this->name = $name;
        $this->type = $type;
        $this->flags = $flags;
    }

    /**
     * @return string
     * The (not fully-qualified) name of this element.
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @return UnionType
     * Get the type of this structural element
     */
    public function getUnionType() : UnionType
    {
        return $this->type;
    }

    /**
     * @return UnionType
     * Get the type of this structural element
     *
     * @suppress PhanUnreferencedPublicMethod possibly used by PassByReferenceVariable
     */
    public function getNonVariadicUnionType() : UnionType
    {
        return $this->type;
    }

    /**
     * @param UnionType $type
     * Set the type of this element
     *
     * @return void
     */
    public function setUnionType(UnionType $type)
    {
        $this->type = $type;
    }

    /**
     * @return void
     */
    protected function convertToNullable()
    {
        // Avoid a redundant clone of nonNullableClone()
        $type = $this->type;
        if ($type->isEmpty() || $type->containsNullable()) {
            return;
        }
        $this->type = $type->nullableClone();
    }

    /**
     * @return int
     */
    public function getFlags() : int
    {
        return $this->flags;
    }

    /**
     * @param int $flag
     * The flag we'd like to get the state for
     *
     * @return bool
     * True if all bits in the flag are enabled in the bit
     * vector, else false.
     */
    public function getFlagsHasState(int $flag) : bool
    {
        return ($this->flags & $flag) === $flag;
    }


    /**
     * @param int $flags
     *
     * @return void
     * @suppress PhanUnreferencedPublicMethod unused, other modifiers are used by Phan right now
     */
    public function setFlags(int $flags)
    {
        $this->flags = $flags;
    }

    /**
     * @return int
     */
    public function getPhanFlags() : int
    {
        return $this->phan_flags;
    }

    /**
     * @param int $flag
     * The flag we'd like to get the state for
     *
     * @return bool
     * True if all bits in the flag are enabled in the bit
     * vector, else false.
     */
    public function getPhanFlagsHasState(int $flag) : bool
    {
        return ($this->phan_flags & $flag) === $flag;
    }

    /**
     * @param int $phan_flags
     *
     * @return void
     * @suppress PhanUnreferencedPublicMethod potentially used in the future
     */
    public function setPhanFlags(int $phan_flags)
    {
        $this->phan_flags = $phan_flags;
    }

    /**
     * @return void
     */
    public function enablePhanFlagBits(int $new_bits)
    {
        $this->phan_flags |= $new_bits;
    }

    /**
     * @return void
     */
    public function disablePhanFlagBits(int $new_bits)
    {
        $this->phan_flags &= (~$new_bits);
    }

    /**
     * @return FileRef
     * A reference to where this element was found
     */
    public function getFileRef() : FileRef
    {
        return $this->file_ref;
    }

    abstract public function __toString() : string;
}
