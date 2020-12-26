<?php
function test921(array $value) {
    if (count($value)) {
        foreach ($value as $x) {
        }
        // should not warn
        return $x;
    }

    // $value is the empty array
    return $value[0];
}
