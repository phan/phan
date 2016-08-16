<?php
class B extends C {
    public function d() : A {
        return new A;
    }
}
class C {
    public $p = A::class;
    public static function e() {}
}
class A extends B {
    private function f() {
        self::e();
    }
}
