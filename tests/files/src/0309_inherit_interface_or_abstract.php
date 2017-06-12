<?php

interface I309 {
    /**
     * @param stdClass $o (omit $y)
     */
    public function interfaceMethod($o, $y);
}

trait T309 {
    /**
     * @param object $o
     * @return bool
     */
    public abstract function traitMethod($o);
}

abstract class A309 {
    /**
     * @param int $x (omit $y)
     * @return void
     */
    public abstract function foo($x, $y);
}

class B309 extends A309 implements I309 {
    use T309;

    /**
     * @param $y
     * @param int $o (NOTE: This test reverses the parameter name order here to check that phan is analyzing by position)
     */
    public function interfaceMethod($y, $o) {
        echo intdiv($y, 2);  // warn, $y is stdClass
        echo spl_object_hash($o);  // warn, $o is an int
    }

    /**
     * @param $arg1 (NOTE: This test reverses the parameter name order here to check that phan is analyzing by position)
     * Should warn about failing to return a value.
     */
    public function traitMethod($arg1) {
        echo intdiv($arg1, 2);  // warn, $y is stdClass
    }

    /**
     * @param int $x
     * @param string $y
     */
    public function foo($x, $y) {
        echo intdiv($y, 3);  // warn, y is string
        echo strlen($x);  // warn, x is int
        return true;  // warn, this is void
    }
}
