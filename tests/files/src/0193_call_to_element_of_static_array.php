<?php

class C10 {
    /** @return static[] */
    public static function f() {
        return [new static()];
    }
}

class C11 extends C10 {
    public function g() {
        return 2;
    }
}

function f() {
    $v = C11::f();
    return $v[0]->g();
}
