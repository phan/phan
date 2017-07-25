<?php

function f344(?string $x) : int {
    if (!is_string($x)) {
        $b = 2;
        return $x;
    } else {
        $b = 'x';
    }
    echo intdiv($x, 2);  // should warn
    echo intdiv($b, 2);  // should warn about $b being a string
    return strlen($x);
}

/**
 * @param $x
 */
function g344($x) : int {
    if (!is_array($x)) {
        if (rand() % 2) {
            return 4;
        } else {
            echo "Some code\n";
            throw new \Exception("Unconditionally threw or returned");
        }
    }
    echo intdiv($x, 2);  // should warn that $x is an array.
    return count($x);
}

/**
 * @param $x
 */
function h344($x) : int {
    if (!is_array($x)) {  // This may or may not return.
        if (rand() % 2) {
            return 4;
        } else {
            echo "Some code\n";
            $x = 'alternative';
        }
    }
    echo intdiv($x, 2);  // currently, doesn't warn, because the type is uncertain.
    if (!is_int($x)) {
        return -1;
    }

    return $x;
}
