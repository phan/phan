/* Auto-generated from php/php-langspec tests */
<?php

trait T9a
{
	public function compute(/* ... */) { /* ... */ }
}

trait T9b
{
	public function compute(/* ... */) { /* ... */ }
}

trait T9c
{
	public function sort(/* ... */) { /* ... */ }
}

trait T9d
{
	use T9c;
	use T9a, T9b
	{
		T9a::compute insteadof T9b;
		T9c::sort as private sorter;
	}
}

trait T10
{
	private $prop1 = 1000;
	protected static $prop2;
	var $prop3;
	public function compute() {}
	public static function getData() {}
}
