<?php
function test188($x, $y) {
    return substr($x, $y) === null;
}
var_export(test188([], []));
