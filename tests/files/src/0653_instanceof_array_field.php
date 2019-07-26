<?php

namespace NS653;

/**
 * @param array{field:object} $array
 */
function check_instanceof($array) {
    if ($array['field'] instanceof \ArrayAccess) {
        $array['field']->someMethod();
        return;
    }
    echo strlen($array['field']);
}

/**
 * @param array{0:\stdClass|\Countable} $array
 */
function check_instanceof_negate($array) {
    if ($array[0] instanceof \Countable) {
        var_export($array[0]->count());
        var_export($array['field']->count());
        return;
    }
    var_export($array[0]->count());
}

const FIVE = 5;

/**
 * @param array{5:\stdClass|\Countable} $array
 */
function check_instanceof_negate_2($array) {
    if ($array[FIVE] instanceof \Countable) {
        var_export($array[FIVE]->count());
        var_export($array[4]->count());
        return;
    }
    var_export($array[FIVE]->count());
}

/**
 * @param \stdClass|int $o
 */
function check_var_negate($o) : string {
    if ($o instanceof \stdClass) {
        return $o;
    }
    return $o;
}
