<?php
declare(strict_types=1);
namespace Phan\Language\Element\Comment;

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

    public function getType() : UnionType
    {
        return $this->type;
    }

    /**
     * @return void
     */
    public function setType(UnionType $type)
    {
        $this->type = $type;
    }

    public function getLineno() : int
    {
        return $this->lineno;
    }
}
