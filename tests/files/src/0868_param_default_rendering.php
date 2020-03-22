<?php

class X {
    const X = 3;

    public static function test(int $x, bool $flag = true, int $y = self::X, int $z = 2 + 2) {
        return [$x, $flag, $y, $z];
    }
}
X::test();
