<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\Language\Element\Comment;

/**
 * A comment with annotations that plugins declared (CustomAnnotationCapability).
 *
 * Comment\Builder creates this subclass only for the comments that have such annotations,
 * so that the other comments do not need a property for them.
 */
final class CommentWithCustomAnnotations extends Comment
{
    /** @var non-empty-list<CustomAnnotation> */
    private $custom_annotations;

    /**
     * Sets the custom annotations of this comment. Called once, by Comment\Builder.
     *
     * @param non-empty-list<CustomAnnotation> $custom_annotations
     * @internal
     */
    public function setCustomAnnotations(array $custom_annotations): void
    {
        $this->custom_annotations = $custom_annotations;
    }

    /**
     * @return list<CustomAnnotation> the annotations of this comment that plugins declared, in the order of the comment
     * @override
     */
    public function getCustomAnnotations(): array
    {
        return $this->custom_annotations;
    }
}
