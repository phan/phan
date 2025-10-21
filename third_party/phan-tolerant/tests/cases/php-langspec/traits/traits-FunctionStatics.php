/* Auto-generated from php/php-langspec tests */
<?php

trait T6
{
	public function f()
	{
		echo "Inside " . __METHOD__ . "\n";

		static $v = 0;			// static is class-specific
		echo "\$v = " . $v++ . "\n";
    }
}

class C6a
{
	use T6;
}

class C6b
{
	use T6;
}

$v1 = new C6a;
$v1->f();		// method run twice with same $v
$v1->f();

echo "-------\n";

$v2 = new C6b;
$v2->f();		// method run three times with a different $v
$v2->f();
$v2->f();

