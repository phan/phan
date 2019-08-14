<?php
/**
 * @param string $x
 */
function test_clone_return_type($x) : string {
    $x = clone($x);
    if (is_string($x)) {  // should warn about impossible condition
        return "prefix: $x";
    }
    return $x;  // should infer object
}

function test_clone_return_type2(?stdClass $x) : ArrayObject {
    return clone($x);
}
