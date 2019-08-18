<?php

namespace NS153;

class X {
    /** @phan-pure */
    public function mul1(int $x) {
        echo "Checking $x\n";
        return $x * 2;
    }

    public function mul2(int $x) {
        echo "Checking $x\n";
        return $x * 2;
    }
}

class Tripler extends X {
    public function mul1(int $x) {
        echo "DEBUG: Tripling $x\n";
        return $x * 3;
    }

    public function mul2(int $x) {
        echo "DEBUG: Tripling $x\n";
        return $x * 3;
    }
}

$t = new X();
$t->mul1(3);  // should warn about being unused
$t->mul2(4);  // should not warn
$t = new Tripler();
$t->mul1(3);  // should warn about being unused
$t->mul2(4);  // should not warn
