<?php

/**
 * @param array<int,?string> $parts
 */
function filter_and_implode(array $parts): string
{
    $filtered = array_filter($parts, static function (?string $value): bool {
        return $value !== null;
    });

    return implode('', $filtered);
}

filter_and_implode(['a', null, 'b']);
