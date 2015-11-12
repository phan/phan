<?php
function test() {
	static $var = 1+2.5;
	return $var;
}
function test2(int $arg) { }
test2(test());
