<?php
function test_duplicate_array($x, $y, ArrayObject $a) {
    var_export([
        $a->count() => null,
        'prefix' . $x => 'x',
        $x => 'x',
        $x => 'x',
        $y => 'y',
        'prefix' . $x => 'x',
        $a->count() => 22,
    ]);
}
test_duplicate_array(2, 3, new ArrayObject());
