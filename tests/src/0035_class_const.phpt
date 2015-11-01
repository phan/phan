--TEST--
Class constants
--FILE--
<?php
class A {
	const C1 = [1,2,3];
}
function test(int $arg) { }
test(A::C1);
--EXPECTF--
%s:6 TypeError arg#1(arg) is int[] but test() takes int defined at %s:5
