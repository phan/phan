<?php

class A {}
class B {}

/**
 * @template T
 */
class X {
	/** @var T */
	public $a;
	/** @var T[] */
	public $b = [];
	/** @var T&A */
	public $c;
	/** @var (T&A)[] */
	public $d = [];

	/** @param T $x */
	function a($x) {}
	/** @param T&A $x */
	function c($x) {}

	/**
	 * @param T $foo
	 */
	public function __construct( $foo ) {
		if ( $foo instanceof A ) {
			// $foo is T&A
			$this->a = $foo; // OK
			$this->b[] = $foo; // OK
			$this->c = $foo; // OK
			$this->d[] = $foo; // OK
			$this->a($foo); // OK
			$this->c($foo); // OK
		} elseif ( $foo instanceof B ) {
			// $foo is T&B
			$this->a = $foo; // OK
			$this->b[] = $foo; // OK
			$this->c = $foo; // Error
			$this->d[] = $foo; // Error
			$this->a($foo); // OK
			$this->c($foo); // Error
		} else {
			// $foo is T
			$this->a = $foo; // OK
			$this->b[] = $foo; // OK
			$this->c = $foo; // Error
			$this->d[] = $foo; // Error
			$this->a($foo); // OK
			$this->c($foo); // Error
		}
	}
}

$x = new X( new A() );
$x = new X( new B() );
$x = new X( new stdClass() );
