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
