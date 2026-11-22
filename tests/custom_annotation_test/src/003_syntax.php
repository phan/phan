<?php

// The forms of custom annotations in doc comments, and their interplay with the annotations of Phan.

/**
 * Names are case-insensitive, like the annotations of Phan:
 * @PHAN-X-TEST-MARK upper case name
 * @Phan-X-Test-Shared declared by both plugins: both are called
 * @phan-x-test-empty
 * @see {@phan-x-test-mark inline {with braces} text} is the inline form
 * @todo {@phan-x-test-mark inline without a closing brace
 * @phan-x-test-mrak a misspelled custom annotation: the suggestion names the custom annotation
 * @phan-test-mark the name without the prefix phan-x- is another annotation, which no plugin declares
 * @phan-x-unknown-tag an annotation with the prefix that no plugin declares, without a suggestion
 * @phan-unknown-tag an unknown annotation without a suggestion
 * @deprecated
 * @phan-x-test-mark   text after three spaces
 */
function syntax_examples(int $x): int
{
    return $x;
}

/** @phan-x-test-mark on a doc comment of one line */
function one_line(): void
{
}

/**
 * @phan-x-test-mark on the first line
 *   (a continuation line is not part of the text)
 * @param array{
 *   a: int,
 *   b: string
 * } $x a multi-line annotation of Phan, which Phan merges into one line
 * @phan-x-test-mark after the multi-line parameter
 */
function continuation(array $x): void
{
    echo $x['a'];
}

class SharedComment
{
    /** @phan-x-test-mark shared by two properties */
    public $first = 1, $second = 2;
}

/**
 * @param string $s
 * @phan-x-test-mark next to annotations of Phan
 * @return string
 * @phan-x-test-second only the second plugin declares this
 */
function with_builtin($s)
{
    return $s;
}

/**
 * @phan-suppress PhanPluginCustomAnnotationSeen
 * @phan-x-test-mark the issue that the plugin emits for this is suppressed
 * @suppress PhanMisspelledAnnotation
 * @phan-x-test-typo a suppressed misspelling
 */
function suppressed(): void
{
}

/**
 * @phan-x-test-mark the issue that the plugin emits for this class is suppressed
 * @suppress PhanPluginCustomAnnotationSeen
 */
class SuppressedClass
{
    /**
     * @suppress PhanPluginCustomAnnotationSeen
     * @phan-x-test-mark the issue that the plugin emits for this property is suppressed
     */
    public $suppressed_property = 1;

    /**
     * @phan-x-test-mark the issue that the plugin emits for this method is not suppressed by the class
     */
    public function method(): void
    {
    }
}

/**
 * @phan-x-test-mark first occurrence
 * @phan-x-test-mark second occurrence
 */
function two_occurrences(): void
{
}

function use_syntax(SharedComment $s): void
{
    syntax_examples(1);
    one_line();
    continuation(['a' => 1, 'b' => 'x']);
    echo with_builtin(2);
    echo $s->first, $s->second;
    suppressed();
    two_occurrences();
    $c = new SuppressedClass();
    echo $c->suppressed_property;
    $c->method();
}
