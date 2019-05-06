<?php
function test121($a) {
    $b = &$a;  // should warn since $b is not read or assigned later
    $a = 2;
    return $a;
}
function test121StaticVar() {
    // This should warn - it's undefined
    echo "Value of b: $staticVar";
    static $staticVar = 'default';  // should warn
    static $staticUnreferencedVar = 'default';  // should warn
    static $staticA = null;
    static $staticB = null;
    if ($staticA === null) {
        $staticA = rand(0, 10);
        $staticB = rand(0, 10);
    }
    var_export([$staticA, $staticB]);
}
test121(2);
test121StaticVar();

function test121GlobalVar() {
    // This should warn - it's undefined
    global $global121;
    global $global121b;
    global $global121c;
    $global121 = 'new value';
    var_export($global121c);
}
$global121 = 'x';
$global121b = 'x';
test121GlobalVar();
var_export($global121);

function test121Closure() {
    $dir_path = 'foo';
    $other = 'xyz';
    $foo = function () use (&$dir_path, $other) {
        echo $other;
    };
    $foo();
}
test121Closure();
