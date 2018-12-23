<?php
declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;

/**
 * The representation of an (at)return comment
 */
class ReturnComment
{
    /** @var UnionType the return type of the comment*/
    private $type;

    /** @var int the line number of the comment */
    private $lineno;

    public function __construct(UnionType $type, int $lineno)
    {
        $this->type = $type;
        $this->lineno = $lineno;
    }

    /**
     * Gets the type of this (at)return comment
     */
    public function getType() : UnionType
    {
        return $this->type;
    }

    /**
     * Sets the type of this (at)return comment
     * @return void
     */
    public function setType(UnionType $type)
    {
        $this->type = $type;
    }

    /**
     * Gets the line number of this (at)return comment's declaration in PHPDoc
     */
    public function getLineno() : int
    {
        return $this->lineno;
    }

    /**
     * Replace the resolved reference to class T (possibly namespaced) with a regular template type.
     *
     * @param array<string,TemplateType> $template_fix_map maps the incorrectly resolved name to the template type
     * @return void
     */
    public function convertTypesToTemplateTypes(array $template_fix_map)
    {
        $this->type = $this->type->withConvertTypesToTemplateTypes($template_fix_map);
    }

    /**
     * Helper for debugging
     */
    public function __toString() : string
    {
        return "ReturnComment(type=$this->type)";
    }
}
