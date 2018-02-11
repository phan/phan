<?php

// Ensure that phan can properly hydrate classes if method parameter defaults use inherited constants.

class A416 {
    const CONSTNAME = 2;
}

class C416 extends B416 {
    public function test($param = D416::CONSTNAME) {
        return $param;
    }
    /** @var int $param */
    public function test2($param = D416::CONSTNAME) {
        return $param;
    }
}

class D416 extends C416 {
}

class B416 extends A416 {
    /** @var int $param */
    public function test3($param = D416::CONSTNAME) {
        return $param;
    }
    public function test4($param = D416::CONSTNAME) {
        return $param;
    }
    public function test5($param = D416::MISSING_CONSTNAME) {
        return $param;
    }
}
