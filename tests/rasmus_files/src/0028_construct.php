<?php
class A {
	function __construct(int $arg) {
		echo $arg;
	}
}
$a = new A("wrong");
