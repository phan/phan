<?php
declare(strict_types=1);
namespace Phan\Language\Element\Comment;

use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\UnionType;

class Parameter
{

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
     * @param string $name
     * The name of the parameter
     *
     * @param UnionType $type
     * The type of the parameter
     */
    public function __construct(
        string $name,
        UnionType $type
    ) {
        $this->name = $name;
        $this->type = $type;
    }

    /**
     *
     */
    public function asVariable(
        Context $context,
        int $flags = 0
    ) : Variable
    {
        return new Variable(
            $context,
            $this->getName(),
            $this->getUnionType(),
            $flags
        );
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

    public function __toString() : string
    {
        $string = '';

        if (!$this->type->isEmpty()) {
            $string .= "{$this->type} ";
        }

        $string .= $this->name;

        return $string;
    }
}
