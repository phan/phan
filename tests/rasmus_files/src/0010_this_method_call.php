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
