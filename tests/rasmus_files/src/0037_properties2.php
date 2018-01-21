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

class C {  // Wrong because C doesn't extend A or B
	static function test() {
		$b = new B;
		$b->text = [];
		$b->test([1,2,3]);
	}
}

C::test();

class D extends A {  // Correct because D extends A
	static function test() {
		$b = new B;
		$b->text = [];
		$b->test([1,2,3]);
	}
}
D::test();
