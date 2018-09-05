<?php
declare(strict_types=1);
namespace Phan\Language\Element\Comment;

use Phan\Language\UnionType;

class Property
{

    /**
     * @var string
     * The name of the parameter
     */
    private $name;

    /**
     * @var UnionType
     * The type of the parameter
     */
    private $type;

    /**
     * @var int
     * The line of this comment property
     */
    private $line;

    /**
     * @param string $name
     * The name of the parameter
     *
     * @param UnionType $type
     * The type of the parameter
     */
    public function __construct(
        string $name,
        UnionType $type,
        int $line
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->line = $line;
    }

    /**
     * @return string
     * The name of the parameter
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @return UnionType
     * The type of the parameter
     */
    public function getUnionType() : UnionType
    {
        return $this->type;
    }

    /**
     * @return int
     * The line of the parameter
     */
    public function getLine() : int
    {
        return $this->line;
    }

    public function __toString() : string
    {
        $string = '';

        if (!$this->type->isEmpty()) {
            $string .= "{$this->type} ";
        }
        $string .= '$' . $this->name;

        return $string;
    }
}
