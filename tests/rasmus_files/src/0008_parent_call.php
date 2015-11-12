<?php
class A {
	static function func1() { }
}
class B extends A {
	function func2() {
		parent::func1();
		parent::func2();
	}
}
