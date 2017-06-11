<?php

/**
 * @param int[]|null $x
 */
function arrayCheck291($x) {
    $x->badMethodCall();
    if (is_array($x)) {
        foreach ($x as $element) {
            $element->badMethodCall();
        }
    }
}

/**
 * @param ArrayAccess|null $x
 */
function objectCheck291($x) {
    if (is_object($x)) {
        $x->offsetExists('key');
        $x->offsetExisssssssts('key');
        intdiv($x, 2);  // make Phan show inferred union type of \ArrayAccess
    }
}

/**
 * @param stdClass|false $x
 */
function scalarCheck291($x) {
    if (is_scalar($x)) {
        intdiv($x, 2);  // make Phan show inferred union type of bool
    }
}
