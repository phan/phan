--TEST--
Property in a trait
--FILE--
<?php
trait T {
    /** * @var string $text */
	protected $text;
}

class A {
	use T;
}

class B extends A {
	function test($arg) {
		$this->text = $arg;
	}
}

class C {
	static function test() {
		$b = new B;
		$b->text = [];
		$b->test([1,2,3]);
	}
}

C::test();
--EXPECTF--
%s:20 AccessError Cannot access protected property B::$text
%s:13 TypeError property is declared to be string but was assigned int[]
