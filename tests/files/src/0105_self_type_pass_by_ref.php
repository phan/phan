<?php
class C {
    public static $v = 1;

    private static function f(&$p) {}

    public static function g() {
        return self::f(self::$v);
    }
}
