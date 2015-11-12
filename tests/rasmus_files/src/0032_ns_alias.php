<?php
namespace NS1\SubA {
	class A {
		public $prop = [1,2,3];
		function test(string $arg) {
			return $arg;
		}
	}
}
namespace NS1\SubB {
	use NS1\SubA\A as AliasA;

	$var = new AliasA;
	$var->test($var->prop);

}
