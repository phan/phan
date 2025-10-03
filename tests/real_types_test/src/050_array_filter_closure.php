<?php

/**
 * @param array<int,?string> $parts
 */
function filter_and_implode(array $parts): string
{
    $filtered = array_filter(
        $parts,
        static fn (?string $value): bool => $value !== null
    );

    return implode('', $filtered);
}

