<?php
// These will result in ArgumentCountErrors for internal functions
var_dump(a: 123);
$args = ['a-b' => 123];
var_dump(...$args);
function dump(string $key) {
    var_dump(...[$key => $key]);
}
dump('x');
