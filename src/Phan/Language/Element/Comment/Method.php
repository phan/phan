<?php
declare(strict_types=1);
namespace Phan\Language\Element\Comment;

use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\UnionType;

class Method
{

    /**
     * @var string
     * The name of the method
     */
    private $name;

    /**
     * @var UnionType
     * The return type of the magic method
     */
    private $type;

    /**
     * @var Parameter[]
     * A list of phpdoc parameters
     */
    private $parameters;

    /**
     * @var bool
     * Whether or not this is a static magic method
     */
    private $is_static;

    /**
     * @param string $name
     * The name of the method
     *
     * @param UnionType $type
     * The return type of the method
     *
     * @param Parameter[] $parameters
     * 0 or more comment parameters for this magic method
     *
     * @param bool $is_static
     * Whether this method is static
     */
    public function __construct(
        string $name,
        UnionType $type,
        array $parameters,
        bool $is_static
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->parameters = $parameters;
        $this->is_static = $is_static;
    }

    /**
     * @return string
     * The name of the magic method
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @return UnionType
     * The return type of the magic method
     */
    public function getUnionType() : UnionType
    {
        return $this->type;
    }

    /**
     * @return Parameter[] - comment parameters of magic method, from phpdoc.
     */
    public function getParameterList() : array
    {
        return $this->parameters;
    }

    /**
     * @return bool
     * Whether or not the magic method is static
     */
    public function isStatic() : bool
    {
        return $this->is_static;
    }

    /**
     * @return int
     * Number of required parameters of this method
     */
    public function getNumberOfRequiredParameters() : int
    {
        return array_reduce(
            $this->parameters,
            function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isRequired() ? 1 : 0));
            },
            0
        );
    }

    /**
     * @return int
     * Number of optional parameters of this method
     */
    public function getNumberOfOptionalParameters() : int
    {
        return array_reduce(
            $this->parameters,
            function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isOptional() ? 1 : 0));
            },
            0
        );
    }

    public function __toString() : string
    {
        if ($this->isStatic()) {
            $string = 'static function ';
        } else {
            $string = 'function ';
        }
        // Magic methods can't be by ref?
        $string .= $this->getName();

        $string .= '(' . implode(', ', $this->getParameterList()) . ')';

        if (!$this->getUnionType()->isEmpty()) {
            $string .= ' : ' . (string)$this->getUnionType();
        }

        return $string;
    }
}
