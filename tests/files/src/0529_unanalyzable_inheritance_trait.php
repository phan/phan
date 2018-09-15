<?php

trait T {
    public static function f() {
        echo "In class T\n";
    }
}

trait U {
    use T;
    public static function f() {
        echo "In class U\n";
    }
}

class X {
    use U { f as g; }
    public static function f() { self::g(); }
}

X::f();  // echoes "In class U"
