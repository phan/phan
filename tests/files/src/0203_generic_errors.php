<?php

/**
 * @generic T
 */
class C {
    /** @var T */
    const C = 42;

    /** @var T */
    public static $p;

    /**
     * @param T $p
     */
    public static function f($p) {}

    /**
     * @return T
     */
    public static function g() {}

    /**
     * @param int
     * @param X
     */
    public function __construct($p1, $p2) {}
}

$v = new C;


/** @generic T */
class C2 {
    /**
     * @param int
     * @param T
     */
    public function __construct($a, $b) {}
}

/** @generic T */
class C3 {
    /**
     * @param T
     * @param int
     */
    public function __construct($a, $b) {}
}
