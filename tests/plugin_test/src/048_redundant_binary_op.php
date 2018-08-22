<?php
$x = new stdClass();
$x->prop = 4;
class A48 {
    public static $prop = 2;
}

var_export((2+2) > 2+2);
var_export($x->prop == $x->prop);
var_export(A48::$prop != A48::$prop);
var_export($x || $x);
var_export($x->prop ** $x->prop);  // should not warn
