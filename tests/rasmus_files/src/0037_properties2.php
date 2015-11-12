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
