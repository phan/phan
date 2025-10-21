/* Auto-generated from php/php-langspec tests */
<?php

$p->newProp = "abc";
$s = serialize($p);		// dynamic property gets serialized if there is NO __sleep method;
						// otherwise, __sleep has to take care of that.
var_dump($s);
//*/

///*
class ColoredPoint extends Point
{
	const RED = 1;
	const BLUE = 2;

	private $color;

	public function __construct($x = 0, $y = 0, $color = RED)
	{
		parent::__construct($x, $y);
		$this->color = $color;

		echo "\nInside " . __METHOD__ . ", $this\n\n";
	}

	public function __toString()
	{
		return parent::__toString() . $this->color;
	}	

// while this method returns an array containing the names of the two inherited, private
// properties and adds to that the one private property from the current class,
// serialize runs in the context o fthe type of the object given it. If that type is
// ColoredPoint, serialize doesn;t knopw what to do when it comes across the names of the
// inherited, private	properties.

/*
	public function __sleep()
	{
		echo "\nInside " . __METHOD__ . ", $this\n\n";
		
		$a = parent::__sleep();
		var_dump($a);
		$a[] = 'color';
		var_dump($a);
		return $a;
	}
*/
}
//*/

///*
