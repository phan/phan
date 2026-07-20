<?php

// Regression test for issue #5553: a never-returning instance method called on a
// local variable with a statically known class should be treated as unreachable.
class Fatal321 {
    public function neverReturns(): never {
        exit();
    }
}

class Runner321 {
    public function neverReturns(): never {
        $foo = new Fatal321();
        $foo->neverReturns();
    }
}

(new Runner321())->neverReturns();
