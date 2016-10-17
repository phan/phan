<?php

class C1 {
    public function f() {
        return 42;
    }
}

class C2 extends C1 {
    public function f() {
        $v = function() {
            return parent::f();
        };
        return $v();
    }
}
