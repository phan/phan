<?php

namespace NS821;

class Example {
    const A = 1;
    const B = 2;

    public function test_redundant() {
        $x = rand(0,1) ? self::A : self::B;
        if ($x === self::B) {
            '@phan-debug-var $x';
            // This is a regression test - Phan would previously incorrectly infer the union type 2(real=1)
            echo "Saw $x\n";
            $x = self::A;
        }
        return $x;
    }
}
