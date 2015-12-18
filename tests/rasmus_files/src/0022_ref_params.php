<?php
class A {
	function test1(&$farg1) {
		$farg1 = 1.5;
	}
}
class B {
	static function test2(int &$farg2) { }
}
function test3(&$farg3) { }

$a = new A;
$arg1 = [1,2,3];
$a->test1($arg1);
B::test2($arg1);
// No errors from undefined vars since they are by-ref
test3($arg3);
preg_match("/(a)/","a",$match);

