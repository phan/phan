<?php
class A {
	static function func1() { }
}
class B extends A {
	static function func2() {
		self::func1();
		self::func2();
		self::func3();
	}
}
