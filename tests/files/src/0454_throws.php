<?php
namespace MyNs;

use RuntimeException;
use Throwable;

function do_stuff() {
    echo "stuff\n";
}

/**
 * @throws RuntimeException
 * @return MissingType
 */
function example1() { do_stuff(); }

/** @throws \InvalidArgumentException */
function example2() { do_stuff(); }

/** @throws MissingException */
function example3() { do_stuff(); }

/** @throw RuntimeException (not phpdoc2) */
function example4() { do_stuff(); }

trait MyTrait {}

class Test454 {
    /** @throws \stdClass description */
    public function a() { do_stuff(); }

    /** @throws false */
    public function b() { do_stuff(); }

    /** @throws Throwable */
    public function c() { do_stuff(); }

    /** @throws Test454 */
    public function d() { do_stuff(); }

    /** @throws resource */
    public function e() { do_stuff(); }

    /**
     * @throws RuntimeException description
     * @throws RuntimeException|false
     */
    public function f() { do_stuff(); }

    /** @throws \Error */
    public function throwError() { do_stuff(); }

    /** @throws \Exception */
    public function throwException() { do_stuff(); }

    /** @throws \ArrayAccess */
    public function throwInterface() { do_stuff(); }

    /** @throws MyTrait */
    public function throwTrait() { do_stuff(); }
}
