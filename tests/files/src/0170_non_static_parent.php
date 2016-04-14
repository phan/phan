<?php

class C1 {
    public function f() {
    }
}

class C2 extends C1 {
    public function g() {
        parent::f();
    }
}
