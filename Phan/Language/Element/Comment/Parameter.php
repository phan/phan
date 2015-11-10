<?php
declare(strict_types=1);
namespace Phan\Language\Element\Comment;

use \Phan\Language\UnionType;

class Parameter {

    /**
     * @var string
     * The name of the parameter
     */
    private $name = null;

    /**
     * @var UnionType
     * The type of the parameter
     */
    private $type = null;

    /**
     * @var string
     * An optional comment string associated with the parameter
     */
    private $comment = null;

    /**
     * @param string $name
     * The name of the parameter
     *
     * @param UnionType $type
     * The type of the parameter
     *
     * @param string $comment
     * An optional comment string associated with the parameter
     */
    public function __construct(
        string $name,
        UnionType $type,
        string $comment
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->comment = $comment;
    }

    /**
     * @return string
     * The name of the parameter
     */
    public function getName() : string {
        return $this->name;
    }

    /**
     * @return UnionType
     * The type of the parameter
     */
    public function getUnionType() : UnionType {
        return $this->type;
    }

    /**
     * @return string
     * An optional comment string associated with the parameter
     */
    public function getComment() : string {
        return $this->comment;
    }

    public function __toString() : string {
        $string = '';

        if (!$this->type->isEmpty()) {
            $string .= "{$this->type} ";
        }

        $string .= $this->name;

        return $string;
    }

}
