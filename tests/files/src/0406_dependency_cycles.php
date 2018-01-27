<?php

class B406 extends C406 {
    public function d() : A406 {
        return new A406;
    }
}

class C406 {
    public $p = A406::class;
    public $q = Missing406::class;
    public static function e() {}
}

class A406 extends B406 {
    private function f() {
        self::e();
    }
}
