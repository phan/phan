<?php
// test type inference for iterables and intersection types

function test_iterable941(iterable $x): object {
    if (is_countable($x)) {
        '@phan-debug-var $x';
        if (is_object($x)) {
            '@phan-debug-var $x';
            var_dump($x instanceof Traversable);  // should warn about redundant check
            return $x;
        }
        return $x;
    }
    return $x;
}
/** @param iterable<string, stdClass> $x */
function test_iterable941b(iterable $x): object {
    if (is_countable($x)) {
        '@phan-debug-var $x';
        if (is_object($x)) {
            '@phan-debug-var $x';
            foreach ($x as $key => $value) {
                echo intdiv($key, $value);
            }
            return $x;
        }
        return $x;
    }
    return $x;
}
