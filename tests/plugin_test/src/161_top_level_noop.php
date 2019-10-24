<?php
function some_nullable161(int $i) : ?array {
    return $i ? [$i] : null;
}
function test161(string $unrelated) {
    // The value of strlen/json_encode isn't used. UseReturnValuePlugin should warn.
    ($x = file_get_contents(__FILE__)) ? strlen($x) : json_encode($x);
    ($x = file_get_contents(__FILE__)) ?: json_encode($x);
    // The value of the right hand side isn't used. UseReturnValuePlugin should warn.
    ($x = file_get_contents(__FILE__)) && strlen($x);
    ($x = file_get_contents(__FILE__)) || var_export($x, true);

    some_nullable161(123) ?? strlen($unrelated);
}
test161('value');
