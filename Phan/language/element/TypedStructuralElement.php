<?php
declare(strict_types=1);
namespace phan\element;

require_once(__DIR__.'/StructuralElement.php');

abstract class TypedStructuralElement extends StructuralElement {

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
     * @param \phan\Context $context
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
        \phan\Context $context,
        CommentElement $comment
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
     * @return \phan\language\FQSEN
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() : \phan\language\FQSEN;

    /**
     * @return string
     * A string representing the fully-qualified structural
     * element name of this structural element.
     */
    public function getFQSENString() : string {
        return $this->getFQSEN()->__toString();
    }

}
