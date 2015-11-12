<?php
class A {
	public $prop = [1,2,3];
	function test(string $arg) {
		return $arg;
	}
}
$var = new A;
$var->test($var->prop);
