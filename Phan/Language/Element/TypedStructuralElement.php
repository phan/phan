<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\Element\Comment;
use \Phan\Language\FQSEN;
use \Phan\Language\Type;

class TypedStructuralElement extends StructuralElement {

    /**
     * @var name
     * The name of the typed structural element
     */
    private $name;

    /**
     * @var Type
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
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param CommentElement $comment,
     * Any comment block associated with the class
     *
     * @param string $name,
     * The name of the typed structural element
     *
     * @param Type $type,
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
        Type $type,
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
        return FQSEN::fromContext(
            $this->getContext()
        );
    }

    /**
     * @return string
     * A string representing the fully-qualified structural
     * element name of this structural element.
     */
    public function getFQSENString() : string {
        return $this->getFQSEN()->__toString();
    }

    /**
     * @return Type
     * Get the type of this structural element
     */
    public function getType() : Type {
        return $this->type;
    }

}
