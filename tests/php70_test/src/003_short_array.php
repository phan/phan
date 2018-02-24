<?php

function test_short_array() {
    $values = [[2], [3]];
    foreach ($values as list($value)) {
        echo strlen($value);
    }
    foreach ($values as [$value2]) {
        echo strlen($value2);
    }
    list($x) = $values;
    echo count($x);
    echo strlen($x);

    [$y] = $values;
    echo count($y);
    echo strlen($y);

    list(
        $a,
        1 => $z
    ) = $values;
    echo count($z);
    echo strlen($z);
}
test_short_array();
