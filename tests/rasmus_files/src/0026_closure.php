<?php
function test() {
	$var = 1;
	$a = function(string $arg) use ($var):array { echo $arg, $var, $undefined; return $arg; };
	$a();
}
test();
