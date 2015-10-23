<?php
declare(strict_types=1);
namespace phan\element;

/**
 *
 */
class StructuralElement {

    /**
     * @var \phan\Context
     * The context in which the structural element lives
     */
    private $context = null;

    /**
     * @var CommentElement
     * Any comment block associated with the structural
     * element
     */
    private $comment_element = null;

    /**
     * @param \phan\Context $context
     * The context in which the structural element lives
     *
     * @param CommentElement $comment,
     * Any comment block associated with the class
     */
    public function __construct(
        \phan\Context $context,
        CommentElement $comment_element
    ) {
        $this->context = $context;
        $this->comment = $comment_element;
    }
}
