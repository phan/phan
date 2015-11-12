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
	return ["oops"];
}
$var = test(5);
$var[7] = 'abc';
