<?php
class A {
	function test($var, $str) {
		echo $str;
	}
}
class B {
	static function test($str='') {
		echo $str;
	}
}

function test($str) {
	$var = "Some formatting with $str inside";
	echo "More formatting and $var inside";
}
$q = "1 {$_SERVER['QUERY_STRING']} 2";
$a = new A;
$a->test(0, "$q");
$x = "abc{$q}def";
B::test($x);
test("abc"."trying to hide the tainted data some more $x - did it work?");
echo $x;
