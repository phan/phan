--TEST--
Method call
--FILE--
<?php
class A {
	function test($arg1, $arg2, $arg3=0) { }
}

$a = new A;
$a->test(1);
--EXPECTF--
%s:7 ParamError call with 1 arg(s) to test() which requires 2 arg(s) defined at %s:3
