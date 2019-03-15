<?php
var_export(clone []);
var_export(clone (new stdClass()));
var_export(clone 'str');
var_export(clone null);
/** @param mixed $x */
function test($x) {
    return clone($x);
}
