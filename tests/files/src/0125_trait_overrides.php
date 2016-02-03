<?php

class C {}

trait T {
    public static function f(C $p) {}
}

class C1 {
    use T;
    public static function f($p) {}
}
