<?php

function test_iterable_param(iterable $x) {
    var_export($x);
}
function test_iterable_return() : iterable {
    return [];
}
test_iterable_param([]);
var_export(test_iterable_return());
