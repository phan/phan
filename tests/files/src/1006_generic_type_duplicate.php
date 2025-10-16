<?php

/**
 * @template T
 */
class A {
	/** @var T */
	private $t;

	/** @param T $t */
	function __construct($t) {
		$this->t = $t;
	}

	/**
	 * @template T
	 * @param T $t
	 * @return T
	 */
	function test($t) {
		return $t;
	}

	/**
	 * @template T
	 * @param T $t
	 * @return T
	 */
	static function test2($t) {
		return $t;
	}
}

/**
 * @template T
 * @template T
 */
class B {
	/** @var T */
	private $t;

	/** @param T $t */
	function __construct($t) {
		$this->t = $t;
	}

	/**
	 * @template U
	 * @template U
	 * @param U $t
	 * @return U
	 */
	function test($t) {
		return $t;
	}
}
