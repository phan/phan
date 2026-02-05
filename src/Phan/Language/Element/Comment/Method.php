<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\Language\UnionType;

/**
 * Phan's representation of a magic method
 * (i.e. an (at)method declaration on a class-like's doc comment)
 */
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
     * @var list<Parameter>
     * A list of phpdoc parameters
     */
    private $parameters;

    /**
     * @var bool
     * Whether or not this is a static magic method
     */
    private $is_static;

    /**
     * @var bool
     * Whether or not this method returns by reference
     */
    private $is_returns_ref;

    /**
     * @var int
     * The line of this method
     */
    private $line;

    /**
     * @param string $name
     * The name of the method
     *
     * @param UnionType $type
     * The return type of the method
     *
     * @param list<Parameter> $parameters
     * 0 or more comment parameters for this magic method
     *
     * @param bool $is_static
     * Whether this method is static
     *
     * @param bool $is_returns_ref
     * Whether this method returns by reference
     *
     * @param int $line
     * The line of this method
     */
    public function __construct(
        string $name,
        UnionType $type,
        array $parameters,
        bool $is_static,
        bool $is_returns_ref,
        int $line
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->parameters = $parameters;
        $this->is_static = $is_static;
        $this->is_returns_ref = $is_returns_ref;
        $this->line = $line;
    }

    /**
     * @return string
     * The name of the magic method
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return UnionType
     * The return type of the magic method
     */
    public function getUnionType(): UnionType
    {
        return $this->type;
    }

    /**
     * @return list<Parameter> - comment parameters of magic method, from phpdoc.
     */
    public function getParameterList(): array
    {
        return $this->parameters;
    }

    /**
     * @return bool
     * Whether or not the magic method is static
     */
    public function isStatic(): bool
    {
        return $this->is_static;
    }

    /**
     * @return bool
     * Whether or not this method returns by reference
     */
    public function returnsRef(): bool
    {
        return $this->is_returns_ref;
    }

    /**
     * @return int
     * The line of this method
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * @return int
     * Number of required parameters of this method
     */
    public function getNumberOfRequiredParameters(): int
    {
        return \array_reduce(
            $this->parameters,
            static function (int $carry, Parameter $parameter): int {
                return ($carry + ($parameter->isRequired() ? 1 : 0));
            },
            0
        );
    }

    /**
     * @return int
     * Number of optional parameters of this method
     */
    public function getNumberOfOptionalParameters(): int
    {
        return \array_reduce(
            $this->parameters,
            static function (int $carry, Parameter $parameter): int {
                return ($carry + ($parameter->isOptional() ? 1 : 0));
            },
            0
        );
    }

    public function __toString(): string
    {
        if ($this->is_static) {
            $string = 'static function ';
        } else {
            $string = 'function ';
        }
        if ($this->is_returns_ref) {
            $string .= '&';
        }
        $string .= $this->name;

        $string .= '(' . \implode(', ', $this->parameters) . ')';

        if (!$this->type->isEmpty()) {
            $string .= ' : ' . (string)$this->type;
        }

        return $string;
    }
}
