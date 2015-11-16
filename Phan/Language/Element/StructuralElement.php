<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\Element\Comment;

/**
 *
 */
class StructuralElement {

    /**
     * @var Context
     * The context in which the structural element lives
     */
    private $context = null;

    /**
     * @var Comment
     * Any comment block associated with the structural
     * element
     */
    private $comment = null;

    /**
     * @var bool
     * True if this element is marked as deprecated
     */
    private $is_deprecated = false;

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param Comment $comment,
     * Any comment block associated with the class
     */
    public function __construct(
        Context $context,
        Comment $comment
    ) {
        $this->context = $context;
        $this->comment = $comment;
    }

    /**
     * After a clone is called on this object, clone our
     * deep objects.
     *
     * @return null
     */
    public function __clone() {
        $this->context = $this->context
            ? clone($this->context)
            : $this->context;

        $this->comment = $this->comment
            ? clone($this->comment)
            : $this->comment;
    }

    /**
     * @return Context
     * The context in which this structural element exists
     */
    public function getContext() : Context {
        return $this->context;
    }

    /**
     * @return Comment
     * A possibly null comment associated with this structural
     * element.
     */
    public function getComment() : Comment {
        return $this->comment;
    }

    /**
     * @return bool
     * True if this element is marked as deprecated
     */
    public function isDeprecated() : bool {
        return $this->is_deprecated;
    }

    /**
     * @param bool $is_deprecated
     * Set this element as deprecated
     *
     * @return null
     */
    public function setIsDeprecated(bool $is_deprecated) {
        $this->is_deprecated = $is_deprecated;
    }


    /**
     * @return bool
     * True if this was an internal PHP object
     */
    public function isInternal() : bool {
        return 'internal' === $this->getContext()->getFile();
    }

}
