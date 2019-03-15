<?php

class Example {
    public static function main() : bool {
        return self::main() ? false : (rand(0,1) > 0);
    }

    public static function notInfinite() : bool {
        return rand(0,1) > 0 ? self::notInfinite() : (rand(0,2) > 0);
    }

    public static function notInfinite2() : bool {
        return rand(0,1) > 0 ? (rand(0,2) > 0) : self::notInfinite2();
    }

    public static function infiniteRecursion() : bool {
        $x = rand(0,1) > 0 ? (rand(0,2) > 0) : self::infiniteRecursion();
        var_export($x);
        return self::infiniteRecursion();
    }
}
