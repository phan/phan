<?php

trait T {
    private function f() {}
}

class C {
    use T;
    function g() {
        return $this->f();
    }
}
