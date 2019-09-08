<?php

namespace NS768;

function accepts_iterable(iterable $i) : void {
    var_export($i);
}
/** @param array $array */
function test($array, $size, ?array $nullable_array) : iterable {
    // array_chunk can return null if it's passed an invalid argument type.
    // However, phan is optimistic and assumes that invalid argument types/counts will be detected by other static/runtime checks,
    // so it doesn't warn about this being possibly nullable.
    $result = array_chunk($array, $size);
    accepts_iterable($result);
    // Phan should emit PhanTypeMismatchArgumentNullable here.
    accepts_iterable($nullable_array);
    return $result;
}
test([1, 2, 3], 2, [2,3]);
