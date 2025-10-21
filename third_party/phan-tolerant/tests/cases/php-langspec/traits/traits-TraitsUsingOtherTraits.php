/* Auto-generated from php/php-langspec tests */
<?php


trait Tx1
{
	function k()
	{
		echo "Inside " . __TRAIT__ . "\n";
		echo "Inside " . __CLASS__ . "\n";
		echo "Inside " . __METHOD__ . "\n";
	}
}

trait Tx2
{
	function m()
	{
		echo "Inside " . __TRAIT__ . "\n";
		echo "Inside " . __CLASS__ . "\n";
		echo "Inside " . __METHOD__ . "\n";
	}
}

trait T4
{
	use Tx1, Tx2;
	use T2a, T2b, T3
	{
		Tx1::k as kk;
		T2a::f insteadof T2b;
	}
}

class C4
{
	use T4;
}

$c4 = new C4;

echo "-------\n";
$c4->f();

echo "-------\n";
$c4->m1();

echo "-------\n";
$c4->k();

echo "-------\n";
$c4->m();

