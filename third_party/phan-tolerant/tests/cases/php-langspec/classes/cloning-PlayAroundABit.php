/* Auto-generated from php/php-langspec tests */
<?php

class C
{
	private $m;
	public function __construct($p1)
	{
		$this->m = $p1;
	}

///*
	public function __clone()
	{
		echo "Inside " . __METHOD__ . "\n";

//		return NULL;	// ignored; not passed along as the result of 'clone'
	}
//*/
}

$obj1 = new C(10);
var_dump($obj1);

$obj2 = clone $obj1;	// default action is to make a shallow copy
var_dump($obj2);

//$obj3 = $obj1->__clone();	// can't call directly!! Why is that?
//var_dump($obj3);

