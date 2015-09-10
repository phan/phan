--TEST--
Property access and type tracking
--FILE--
<?php
class A {
	public $prop = [1,2,3];
	function test(string $arg) {
		return $arg;
	}
}
$var = new A;
$var->test($var->prop);
--EXPECTF--
%s:9 TypeError arg#1(arg) is int[] but A::test() takes string defined at %s:4
