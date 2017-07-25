<?php

function test_sort_preserves_type() {
    $x = [new stdClass(), new stdClass()];
    sort($x);
    echo intdiv($x[0], 2);
}
