<?php

/**
 * @param string[]|null $x
 * @param string[]|null $y
 * @param string[]|null $z
 */
function foo($x = null, $y = null, $z = null) {
    if ($x == null) {
        $x = [];
    }
    // Expected postcondition: $x is string[] (and possibly array)
    echo count($x);
    echo strlen($x);
    if ($y != "") {
        echo strlen($y);  // inferred type should be string[]
    }
    if ($z == false) {
    } else {
        echo strlen($z);
    }
    // This check isn't comprehensive, e.g. Phan isn't aware that `!= []` implies not null.
}
