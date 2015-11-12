<?php
function test() {
	global $var;
	$var = "abc";
}
function test2(array $arg) {

}

$var = 1;
test();
test2($var);
