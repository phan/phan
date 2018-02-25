<?php

class A {
    public static $alpha = 42;
    public $beta = 'string';
    const FOURTY_TWO = 42;
}

class B extends A {
    public static $gamma = parent::$alpha;  // FIXME: PHP Fatal error: Constant expression contains invalid operations, but this test expects no Issues.
    public $delta = parent::$beta;  // Note: This is not a valid way to fetch a parent instance property. Emit an issue here as well.
    public $epsilon = parent::FOURTY_TWO;
}
// FIXME: this test should access parent properties within functions. Property declaration defaults expect constant expressions.
