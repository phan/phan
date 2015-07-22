--TEST--
Trait pirority check
--FILE--
<?php
trait T {
	function __get($key) { echo $key; }
}
class A {
    use T;
	function __get($var) { echo $var; }
}

$a = new A;
echo $a->var;
--EXPECTF--
