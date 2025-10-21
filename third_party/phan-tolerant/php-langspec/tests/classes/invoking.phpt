--TEST--
PHP Spec test generated from ./classes/invoking.php
--FILE--
<?php

error_reporting(-1);

class C
{
///*
	public function __invoke($p)
	{
		echo "Inside " . __METHOD__ . " with arg $p\n";

		return "xxx";
	}
//*/
}

$c = new C;
var_dump(is_callable($c)); // returns TRUE is __invoke exists; otherwise, FALSE
$r = $c(123);
var_dump($r);
$r = $c("Hello");
var_dump($r);

--EXPECT--
bool(true)
Inside C::__invoke with arg 123
string(3) "xxx"
Inside C::__invoke with arg Hello
string(3) "xxx"
