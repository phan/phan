<?php

class A {
	function doA(): static {
		return $this;
	}
}

interface X {
	function doX(): static;
}

class B extends A implements X {
	function doX(): static {
		return $this;
	}
}

function newB(): A&X {
	return new B;
}

$a = (new B)->doA()->doX();
$b = newB();
$c = newB()->doA();
$d = newB()->doA()->doX();
'@phan-debug-var $a, $b, $c, $d';
