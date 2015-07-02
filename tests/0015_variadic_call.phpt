--TEST--
Variadic call
--FILE--
<?php
	function test($req, ...$params) { }

	test(1,2,3,4,5);
	test();
--EXPECTF--
%s:5 ParamError call with 0 arg(s) to test() that requires 2 arg(s) defined at %s:2
