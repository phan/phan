<?php
// Regression test for an uncaught error in Phan. The expected output may be empty in the future.

abstract class A373 { }

/** @method B373 foo() */
abstract class B373 extends A373 {
    /** @return B373 */
    abstract protected function foo() : A373;
}
