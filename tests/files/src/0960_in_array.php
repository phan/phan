<?php

/**
 * @param iterable<string,int> $iterable
 */
function test960($element, iterable $iterable): int {
    // in_array implies the $array argument is non-empty and must be an array.
    if (in_array($element, $iterable, true)) {
        '@phan-debug-var $element, $iterable';
        return count($iterable);
    }
    if (in_array($iterable, $element, true)) {
        '@phan-debug-var $element, $iterable';
        return count($element);
    }
    return 0;
}
