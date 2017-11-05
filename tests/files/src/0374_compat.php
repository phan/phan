<?php

class A374 {
    /** @return mixed */
    public function f1() {
        return 'x';
    }
    /** @return string */
    public function f2() {
        return 'x';
    }

    public function f3() : string {
        return 'x';
    }

    public function f4() { }

    public function bar($x) {}
}

class B374 extends A374 {

    /** @return string (Allowed to make return type stricter) */
    public function f1() {
        return 'x';
    }
    /** @return mixed (Not allowed to make it weaker) */
    public function f2() {
        return 'x';
    }
    /** Not allowed to make it weaker */
    public function f3() {
        return 'x';
    }

    /** Allowed */
    public function f4() : string {
        return 'x';
    }

    public function bar(stdClass $x) {}
}
