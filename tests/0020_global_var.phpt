--TEST--
Global var
--FILE--
<?php
function test() {
	global $var;
	$var = "abc";
}
function test2(array $arg) {

}

$var = 1;
test();
test2($var);
--EXPECTF--
%s:12 TypeError arg#1(arg) is string|int but test2() takes array defined at %s:6
