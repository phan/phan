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

$var = test(5);
$var[7] = 'abc';
--EXPECTF--
%s:11 TypeError Assigning string to $var which is int[]
