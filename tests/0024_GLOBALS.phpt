--TEST--
$GLOBALS assignment
--FILE--
<?php
function test() {
	$GLOBALS['a']['b'] = 1;
	$GLOBALS['c'] = 1;
}
function test1(array $arr) { }
function test2(int $arr) { }
test();
test1($c);
test2($a);
--EXPECTF--
%s:9 TypeError arg#1(arr) is int but test1() takes array defined at %s:6
%s:10 TypeError arg#1(arr) is array but test2() takes int defined at %s:7

