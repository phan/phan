<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\Element\Comment;
use \Phan\Language\FQSEN;
use \Phan\Language\UnionType;

class TypedStructuralElement extends StructuralElement {

    /**
     * @var name
     * The name of the typed structural element
     */
    private $name;

    /**
     * @var UnionType
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     */
    private $type = null;

    /**
     * @var int
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    private $flags = 0;

    /**
     * @var FQSEN
     * The fully-qualified structural element name
     */
    protected $fqsen = null;

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param CommentElement $comment,
     * Any comment block associated with the class
     *
     * @param string $name,
     * The name of the typed structural element
     *
     * @param UnionType $type,
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     *
     * @param int $flags,
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    public function __construct(
        Context $context,
        Comment $comment,
        string $name,
        UnionType $type,
        int $flags
    ) {
        parent::__construct(
            $context, $comment
        );

        $this->name = $name;
        $this->type = $type;
        $this->flags = $flags;
    }

    /**
     * @return string
     * The (not fully-qualified) name of this element.
     */
    public function getName() : string {
        return $this->name;
    }

    /**
     * @return FQSEN
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() : FQSEN {
        // Get the stored FQSEN if it exists
        if ($this->fqsen) {
            return $this->fqsen;
        }

        // else generate it
        return $this->getContext()->getScopeFQSEN();
    }

    /**
     * @return string
     * A string representing the fully-qualified structural
     * element name of this structural element.
     */
    public function getFQSENString() : string {
        return (string)$this->getFQSEN();
    }

    /**
     * @param FQSEN $fqsen
     * A fully qualified structural element name to set on
     * this element
     *
     * @return null
     */
    public function setFQSEN(FQSEN $fqsen) {
        $this->fqsen = $fqsen;
    }

    /**
     * @return UnionType
     * Get the type of this structural element
     */
    public function getUnionType() : UnionType {
        return $this->type;
    }

    /**
     * @param UnionType $type
     * Set the type of this element
     *
     * @return null
     */
    public function setUnionType(UnionType $type) {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getFlags() : int {
        return $this->flags;
    }

    /**
     * @param int $flags
     * @return null
     */
    public function setFlags(int $flags) {
        $this->flags = $flags;
    }

}
