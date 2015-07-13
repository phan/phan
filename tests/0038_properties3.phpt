--TEST--
int array property
--FILE--
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
--EXPECTF--
%s:9 TypeError property is declared to be int[] but was assigned string[]
%s:10 TypeError property is declared to be int[] but was assigned int
