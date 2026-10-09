<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\Language\UnionType;

/**
 * Represents the (at)return annotation of a doc comment.
 *
 * When the annotation is a conditional return type (`(at)return ($x is null ? A : B)`),
 * getType() is the union of every branch and getConditional() holds the structure.
 */
class ReturnComment
{
    /** @var UnionType the (flattened, for conditionals) return type */
    private $type;

    /** @var int the line number of the annotation */
    private $lineno;

    /** @var ?ConditionalReturnType the conditional return type, if the annotation was one */
    private $conditional;

    public function __construct(UnionType $type, int $lineno, ?ConditionalReturnType $conditional = null)
    {
        $this->type = $type;
        $this->lineno = $lineno;
        $this->conditional = $conditional;
    }

    /**
     * Gets the type of this (at)return comment (the union of every branch, for a conditional return type)
     */
    public function getType(): UnionType
    {
        return $this->type;
    }

    /**
     * Sets the type of this (at)return comment
     */
    public function setType(UnionType $type): void
    {
        $this->type = $type;
    }

    /**
     * Gets the conditional return type, if this (at)return comment was one
     */
    public function getConditional(): ?ConditionalReturnType
    {
        return $this->conditional;
    }

    /**
     * Sets the conditional return type
     */
    public function setConditional(?ConditionalReturnType $conditional): void
    {
        $this->conditional = $conditional;
    }

    /**
     * Returns a copy with a different type, preserving the line number and the conditional.
     */
    public function withType(UnionType $type): ReturnComment
    {
        return new ReturnComment($type, $this->lineno, $this->conditional);
    }

    /**
     * Gets the line number of this (at)return comment
     */
    public function getLineno(): int
    {
        return $this->lineno;
    }

    public function __toString(): string
    {
        return "ReturnComment(type=$this->type)";
    }
}
