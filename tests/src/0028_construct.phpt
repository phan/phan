--TEST--
Constructor type check
--FILE--
<?php
class A {
	function __construct(int $arg) {
		echo $arg;
	}
}
$a = new A("wrong");
--EXPECTF--
%s:7 TypeError arg#1(arg) is string but A::__construct() takes int defined at %s:3
