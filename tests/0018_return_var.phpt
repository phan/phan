--TEST--
Return type with passed var
--FILE--
<?php
function test($arg):int {
	return $arg;
}
test("abc");
--EXPECTF--
%s:3 TypeError return string but test() is declared to return int
