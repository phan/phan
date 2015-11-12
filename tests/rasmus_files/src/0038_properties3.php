<?php
class A {
	/**
     * @var int[] $prop
     */
	private $prop;

	function test() {
		$this->prop[] = "abc";
		$this->prop = 1;
		$this->prop[] = 2;
	}
}

$a = new A;
$a->test();
