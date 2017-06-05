<?php  // Test for #457
trait A {
    protected abstract function fn();

    protected abstract function fn2(int $x);
}

class B {
    function __construct() {
        $this->fn(1);
    }
    protected function fn() {
        $args = func_get_args();
        return true;
    }

    protected function fn2(int $x) {
        $args = func_get_args();
        return $x;
    }
}

class C extends B {
    use A;

    function __construct() {
        $this->fn(1);
        $this->fn2('incompatible', ['extra param']);
    }
}

$c = new C;
