/* Auto-generated from php/php-langspec tests */
<?php

class Employee
{
	private $name;

	public function __construct($name)
	{
		$this->name = $name;
	}

	public function __clone()
	{
		echo "Inside " . __METHOD__ . "\n";
//		$v = parent::__clone(); // as class has no parent, this is diagnosed

		// make a copy of Employee object

		return 999;	// ignored; not passed along as the result of 'clone'

	}
}

class Manager extends Employee
{
	private $level;

	public function __construct($name, $level)
	{
		parent::__construct($name);
		$this->level = $level;
	}

	public function __clone()
	{
		echo "Inside " . __METHOD__ . "\n";

		$v = parent::__clone();
		echo "\n====>>>>"; var_dump($v);

// make a copy of Manager object

//		return 999;	// ignored; not passed along as the result of 'clone'

	}
}

$obj3 = new Manager("Smith", 23);
var_dump($obj3);

$obj4 = clone $obj3;
var_dump($obj4);
