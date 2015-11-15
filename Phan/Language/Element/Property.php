<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\UnionType;
use \Phan\Language\Element\Comment;

class Property extends TypedStructuralElement {

    /**
     * @var UnionType
     * The declared type of the property
     */
    private $declared_type;

    /**
     * @param \phan\Context $context
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
            $context,
            $comment,
            $name,
            $type,
            $flags
        );
    }

    /**
     * @return UnionType
     * Get the declared type of the property
     */
    public function getDeclaredUnionType() : UnionType {
        return $this->declared_type;
    }

    /**
     * @param UnionType $type
     * Set the declared type of the property
     *
     * @return null
     */
    public function setDeclaredUnionType(UnionType $type) {
        $this->declared_type = $type;
    }

    /**
     * @return bool
     * True if this is a public property
     */
    public function isPublic() {
        return !(
            $this->isProtected() || $this->isPrivate()
        );
    }

    /**
     * @return bool
     * True if this is a protected property
     */
    public function isProtected() {
        return (bool)(
            $this->getFlags() & \ast\flags\MODIFIER_PROTECTED
        );
    }

    /**
     * @return bool
     * True if this is a private property
     */
    public function isPrivate() {
        return (bool)(
            $this->getFlags() & \ast\flags\MODIFIER_PRIVATE
        );
    }

    public function __toString() : string {
        $string = '';

        if ($this->isPublic()) {
            $string .= 'public ';
        } else if ($this->isProtected()) {
            $string .= 'protected ';
        } else if ($this->isPrivate()) {
            $string .= 'private ';
        }

        $string .= "{$this->getUnionType()} {$this->getName()}";

        return $string;
    }

}
