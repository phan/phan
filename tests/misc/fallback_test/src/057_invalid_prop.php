<?php
class C57 {
    public static $v = 1;

    private static function f(&$p) {}

    public static function g() {
        return self::f(self::${0});
    }
}
C57::g();
