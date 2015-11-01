--TEST--
Self call
--FILE--
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
--EXPECTF--
%s:9 UndefError static call to undeclared method B::func3()
