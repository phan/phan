--TEST--
docComment
--FILE--
<?php
/**
 * @param int|string $arg
 * @return array
 */
function test($arg) {
	return $arg;
}
$var = [1,2,3];
test($var);
--EXPECTF--
%s:7 TypeError return int|string but test() is declared to return array
%s:10 TypeError arg#1(arg) is array but test() takes int|string defined at %s:6
