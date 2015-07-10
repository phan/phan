--TEST--
Method call
--FILE--
<?php
class A {
	function test() { }
}

$a = new A;
$a->test(1);
--EXPECTF--
%s:7 ParamError call with 1 arg(s) to test() which only takes 0 arg(s) defined at %s:3
