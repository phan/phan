<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\Language\UnionType;

/**
 * Represents information about a given (at)property annotation in a PHPDoc comment
 */
class Property
{

    /**
     * @var string
     * The name of the property
     */
    private $name;

    /**
     * @var UnionType
     * The type of the property
     */
    private $type;

    /**
     * @var int
     * The line of this comment property
     */
    private $line;

    /**
     * @var int
     * The flags of this comment property
     * (0 or IS_READ_ONLY_PROPERTY or IS_WRITE_ONLY_PROPERTY)
     */
    private $flags;

    /**
     * @param string $name
     * The name of the property
     *
     * @param UnionType $type
     * The type of the property
     *
     * @param int $flags
     * Additional flags added to a property
     */
    public function __construct(
        string $name,
        UnionType $type,
        int $line,
        int $flags
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->line = $line;
        $this->flags = $flags;
    }

    /**
     * @return string
     * The name of the property
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return UnionType
     * The type of the property
     */
    public function getUnionType(): UnionType
    {
        return $this->type;
    }

    /**
     * @return int
     * The line of the property
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * @return int
     * The flags of the property
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    public function __toString(): string
    {
        $string = '';

        if (!$this->type->isEmpty()) {
            $string .= "{$this->type} ";
        }
        $string .= '$' . $this->name;

        return $string;
    }
}
