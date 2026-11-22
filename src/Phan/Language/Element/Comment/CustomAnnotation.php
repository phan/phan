<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

/**
 * An occurrence of an annotation that a plugin declared with CustomAnnotationCapability, e.g. `(at)phan-x-my-assert $x`.
 *
 * - getName() is the name of the annotation in lowercase, without `@` (e.g. `phan-x-my-assert`).
 * - getText() is the text after the name, trimmed. For `(at)phan-x-my-assert $x` it is the rest of the line,
 *   without the `*` + `/` that ends a doc comment on one line. For the inline form `{(at)phan-x-my-assert $x}`,
 *   it is the text before the `}` that closes the tag (braces in the text must be balanced), or the rest of the line
 *   if there is no such `}`. The text does not continue on the next lines of the doc comment.
 * - getLineno() is the line of the annotation in the file.
 *
 * @see \Phan\PluginV3\CustomAnnotationCapability
 * @phan-immutable
 */
final class CustomAnnotation
{
    /** @var string the name of the annotation in lowercase, without '@' */
    private $name;

    /** @var string the text after the name of the annotation */
    private $text;

    /** @var int the line of the annotation */
    private $lineno;

    public function __construct(string $name, string $text, int $lineno)
    {
        $this->name = $name;
        $this->text = $text;
        $this->lineno = $lineno;
    }

    /**
     * Returns the name of the annotation in lowercase, without '@' (e.g. 'phan-x-my-assert').
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the text after the name of the annotation, trimmed (may be empty).
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Returns the line of the annotation in the file.
     */
    public function getLineno(): int
    {
        return $this->lineno;
    }

    /**
     * Returns the annotation as it could be written, e.g. '@phan-x-my-assert $x'.
     */
    public function __toString(): string
    {
        return '@' . $this->name . ($this->text !== '' ? ' ' . $this->text : '');
    }
}
