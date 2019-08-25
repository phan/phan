<?php

class Test003 {
    public static $prop;
    public static function f($obj, int $i) : bool {
        return isset($obj[$i]);
    }
}
Test003::$prop = rand() % 2 > 0;
if (Test003::$prop) {
    echo "True\n";
}
Test003::f("arg", 1);
Test003::f(rand() % 2 ? "valid" : null, 0);
$f = function ($x) {
    return $x * 2;
};
$f(123);
function fetch_value($x) : int {
    return rand(0, $x);
}
fetch_value(112);
