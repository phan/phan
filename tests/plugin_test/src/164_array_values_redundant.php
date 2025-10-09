<?php
function test_array_values(...$args) {
    $arr = array_values($args);  // variadic parameters can have strings as array keys
    sort($arr);
    return array_values($arr);
}
test_array_values(1,2);
