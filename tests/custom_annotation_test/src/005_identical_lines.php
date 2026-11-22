<?php

// Identical lines in a doc comment: each occurrence of a custom annotation is reported at its own line.

/**
 * @phan-x-test-mark
 * @phan-x-test-mark
 */
function two_identical_lines(): void
{
}

class IdenticalLinesOnProperty
{
    /**
     * @phan-x-test-mark the same text
     * @phan-x-test-mark the same text
     */
    public $prop = 1;
}

/**
 * @phan-x-test-mark three times
 * @phan-x-test-mark three times
 * @phan-x-test-mark three times
 */
function three_identical_lines(): void
{
}

#[Attribute]
class MarkerAttribute
{
    public function __construct(public string $value = '')
    {
    }
}

/**
 * @phan-x-test-mark separated
 * Another line
 * @phan-x-test-mark separated
 */
#[MarkerAttribute(
    'an attribute on several lines between the doc comment and the declaration'
)]
function identical_lines_and_attribute(): void
{
}

function use_identical_lines(IdenticalLinesOnProperty $p): void
{
    two_identical_lines();
    echo $p->prop;
    three_identical_lines();
    identical_lines_and_attribute();
}
