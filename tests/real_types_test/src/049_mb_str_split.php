<?php

function split_default(string $value): array
{
    return mb_str_split($value);
}

function split_with_length(string $value): array
{
    return mb_str_split($value, 2);
}

function split_with_encoding(string $value): array
{
    return mb_str_split($value, 1, 'UTF-8');
}

