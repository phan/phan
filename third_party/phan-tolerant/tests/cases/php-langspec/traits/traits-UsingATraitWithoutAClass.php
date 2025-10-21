/* Auto-generated from php/php-langspec tests */
<?php

trait T7
{
	public static $pubs = 123;

	function f()	// implicitly public
	{
		echo "Inside " . __TRAIT__ . "\n";
		echo "Inside " . __CLASS__ . "\n";
		echo "Inside " . __METHOD__ . "\n";
		var_dump($this);
	}

	public static function g()
	{
		echo "Inside " . __TRAIT__ . "\n";
		echo "Inside " . __CLASS__ . "\n";
		echo "Inside " . __METHOD__ . "\n";
	}
}

T7::f(); 	// calls f like a static function with class name being the trait name

echo "-------\n";
T7::g();

/*
echo "-------\n";
var_dump(T7::pubs); // doesn't work for static properties
*/

