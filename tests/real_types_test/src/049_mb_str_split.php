<?php

/**
 * @return list<string>
 */
function split_default(string $value): array
{
    return mb_str_split($value);
}

/**
 * @return list<string>
 */
function split_with_length(string $value): array
{
    return mb_str_split($value, 2);
}

/**
 * @return list<string>
 */
function split_with_encoding(string $value): array
{
    return mb_str_split($value, 1, 'UTF-8');
}

split_default('example');
split_with_length('example');
split_with_encoding('example');
