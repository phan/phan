--TEST--
Parent call
--FILE--
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
--EXPECTF--
%s:8 UndefError static call to undeclared method A::func2()
