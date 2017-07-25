<?php
error_reporting(E_ALL);

class C {}

trait T {
    public static function f(C $p) { }
    public function gAbstract(C $p) {}
    public static function gAbstractStatic(C $p) {}
}

class C1 {
    use T;
    public static function f($p) {}
    public function gAbstract($p) : int {return 1;}  // This doesn't throw at runtime, so RealSignatureMismatch shouldn't be emitted. Maybe something else in the future.
    public static function gAbstractStatic($p) : int {return 0;}  // This doesn't throw
}
$c125 = new C1();  // This does not throw
C1::f(42);  // Neither does this.
