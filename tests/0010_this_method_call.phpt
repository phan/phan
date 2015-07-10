--TEST--
$this method call
--FILE--
<?php
class A {
	function testA($arg) { }
}

class B extends A {
	function testB() { }
	function test() {
		$this->testA();
	}
}
--EXPECTF--
%s:9 ParamError call with 0 arg(s) to testA() which requires 1 arg(s) defined at %s:3
