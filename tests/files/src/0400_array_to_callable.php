<?php

class A400{
    public function static_method(string $arg) {
        echo "Invoked $arg\n";
    }
}

/** @param array<int,string> $x */
function expect_int_array(array $x) {
    call_user_func($x, 'arg');
}
$arr = ['A400', 'static_method'];
expect_int_array($arr);

/** @param array<string,string> $x */
function expect_string_array(array $x) {
    call_user_func($x, 'arg');
}
$arr2 = ['a' => 'A400', 'b' => 'static_method'];
expect_string_array($arr2);
