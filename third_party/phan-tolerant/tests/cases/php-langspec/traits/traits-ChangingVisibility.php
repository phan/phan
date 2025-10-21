/* Auto-generated from php/php-langspec tests */
<?php

trait T3
{
	public function m1() { echo "Inside " . __METHOD__ . "\n"; }
	protected function m2() { echo "Inside " . __METHOD__ . "\n"; }
	private function m3() { echo "Inside " . __METHOD__ . "\n"; }

	function m4() { echo "Inside " . __METHOD__ . "\n"; }	// implicitly public
}

class C3
{
	use T3
	{
		m1 as protected;		// reduce visibility to future, derived classes
		m2 as private;
		m3 as public;
		m3 as protected z3;
	}
}

$c3 = new C3;
//$c3->m1();		// accessible, by default, but not once protected
//$c3->m2();		// inaccessible, by default
$c3->m3();			// inaccessible, by default
$c3->m4();			// accessible, by default

