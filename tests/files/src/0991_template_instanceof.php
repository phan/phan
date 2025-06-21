<?php

class A {}

/**
 * @template T
 */
class X {
	/** @var ?T */
	public $a;
	/** @var T[] */
	public $b = [];
	/** @var ?T&A */
	public $c;
	/** @var (T&A)[] */
	public $d = [];

	/**
	 * @param T $foo
	 */
	public function __construct( $foo ) {
		if ( $foo instanceof A ) {
			$this->a = $foo;
			$this->b[] = $foo;
			$this->c = $foo;
			$this->d[] = $foo;
		}
	}
}

$x = new X( new A() );
