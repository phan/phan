<?php
class A {
	/**
     * @var int[] $prop
     */
	private $prop;

	function test() {
		$this->prop[] = "abc";
		$this->prop = 1;
		$this->prop[] = 2;  // Phan warns because it's set to 1 in this scope.
		$this->prop = [];
		$this->prop[] = 2;  // Phan does not warn
	}
}

$a = new A;
$a->test();
