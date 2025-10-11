<?php

// Test native readonly vs PHPDoc @phan-read-only (issue #4703)

class x{
	public readonly mixed $x;

	public function __construct(){
		$this->x = 1;
	}
}

class y{
	public readonly mixed $x;

	public function __construct(){
		$this->x = 1;  // Native readonly: Allowed in __construct
	}
}

class z{
	/** @phan-read-only */
	public mixed $x;

	public function __construct(){
		$this->x = 1;
	}
}

class invalidPhpDoc{
	/** @phan-read-only */
	public mixed $x;

	public function __construct(){
		self::doSetup();
	}

	public function doSetup(){
		$this->x = 1;  // PHPDoc @phan-read-only: Should warn (only allowed in __construct)
	}
}

class invalidRealPhp{
	public readonly mixed $x;

	public function __construct(){
		$this->x = 1;
		$this->x = 2;  // Native readonly: Should warn (multiple assignments)
	}
}

$a	= [
	new x(),
	new y(),
	new z(),
	new invalidPhpDoc(),
	new invalidRealPhp(),
];
