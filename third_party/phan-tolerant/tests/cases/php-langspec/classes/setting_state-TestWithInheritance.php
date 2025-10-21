/* Auto-generated from php/php-langspec tests */
<?php

class B
{
	private $bprop;

	public function __construct($p)
	{
		$this->bprop = $p;
	}

	static public function __set_state(array $properties)
	{
		echo "Inside " . __METHOD__ . "\n";
		var_dump($properties);

		$b = new static($properties['bprop']);
//		$b->bprop = $properties['bprop'];
		var_dump($b);
		echo "about to return from " . __METHOD__ . "\n";
		return $b;
	}
}

class D extends B
{
	private $dprop = 123;

	public function __construct($bp, $dp = NULL)
	{
		$this->dprop = $dp;
		parent::__construct($bp);
	}
///*
	static public function __set_state(array $properties)
	{
		echo "Inside " . __METHOD__ . "\n";
		var_dump($properties);

		$d = parent::__set_state($properties);
		var_dump($d);
		$d->dprop = $properties['dprop'];
		var_dump($d);
		echo "about to return from " . __METHOD__ . "\n";
		return $d;
	}
//*/
}

