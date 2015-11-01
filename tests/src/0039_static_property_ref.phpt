--TEST--
static property by-ref
--FILE--
<?php
class A {
	public static $var;
}
function test(&$arg) { $arg = 2; }
function test2(&$arg2) { $arg2 = "abc"; }
function test3(array $arg) { }
test(A::$var);
test2(A::$var);
test3(A::$var);
--EXPECTF--
%s:10 TypeError arg#1(arg) is int|string but test3() takes array defined at %s:7
