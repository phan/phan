<?php

$closure = function() {
    return 42;
};

class A {
    private $a = 42;

    public function f() {
        $b = 2;

        $closure = function(int $p) use (&$b, $c) {
            $b = 'string';
            return ($p + $this->a + $b);
        };

        return $closure(3);
    }
}
