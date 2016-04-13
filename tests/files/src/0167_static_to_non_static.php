<?php

class C {
    public function g() {}
    public static function f() {
        return $this->g();
    }
}
