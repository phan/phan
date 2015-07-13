--TEST--
Namespace aliased function access
--FILE--
<?php
namespace NS1 {
	function test(string $arg) { }
}
namespace NS2 {
	use function NS1\test;
	test([1,2,3]);
}
--EXPECTF--
%s:7 TypeError arg#1(arg) is int[] but NS1\test() takes string defined at %s:3
