<?php
namespace NS1 {
	function test(string $arg) { }
}
namespace NS2 {
	use function NS1\test;
	test([1,2,3]);
}
