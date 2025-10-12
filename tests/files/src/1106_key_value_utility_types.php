<?php
/**
 * @phan-file-suppress PhanPluginRedundantFunctionComment
 * @phan-file-suppress PhanPluginCanUseUnionReturnType
 */

/**
 * @return key-of<array{foo: int, bar: string}>
 */
function valid_key_of_shape(): string
{
    return 'foo';
}

/**
 * @return key-of<array{foo: int, bar: string}>
 */
function invalid_key_of_shape(): string
{
    return 'baz';
}

/**
 * @return key-of<array<string, int>>
 */
function invalid_key_of_generic(): string
{
    return 3;
}

/**
 * @return value-of<array{foo: int, bar: string}>
 */
function valid_value_of_shape()
{
    return 42;
}

/**
 * @return value-of<array{foo: int, bar: string}>
 */
function invalid_value_of_shape()
{
    return false;
}

/**
 * @return value-of<array<string, int>>
 */
function invalid_value_of_generic(): int
{
    return 'nope';
}
