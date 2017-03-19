<?php
declare(strict_types=1);
namespace Phan\Language\Element\Comment;

use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;

class Parameter
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
     * @var bool
     * Whether or not the parameter is variadic (in the comment)
     */
    private $is_variadic;

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
        bool $is_variadic = false
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->is_variadic = $is_variadic;
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

    /**
     * @return bool
     * Whether or not the parameter is variadic
     */
    public function isVariadic() : bool
    {
        return $this->is_variadic;
    }

    public function __toString() : string
    {
        $string = '';

        if (!$this->type->isEmpty()) {
            $string .= "{$this->type} ";
        }
        if ($this->is_variadic) {
            $string .= '...';
        }

        $string .= $this->name;

        return $string;
    }
}
