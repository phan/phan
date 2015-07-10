--TEST--
Generics
--FILE--
<?php
/**
 * @param int $cnt
 * @return int[]
 */
function test($cnt) {
	return range(0,$cnt);
}
/**
 * @return DateTime[]
 */
function test2() {
	return ["oops", new DateTime()];
}
$var = test(5);
$var[7] = 'abc';
--EXPECTF--
%s:13 TypeError return string[] but test2() is declared to return datetime[]
%s:16 TypeError Assigning string to $var which is int[]
