<?php
function accepts_one($x) {}
class Test {
    public static function no_args() {}
}
call_user_func(function () {
    accepts_one(1, $a);
    echo strlen('x', $other);
    // Should warn about missing $invalid
    $x = new class ($invalid) {};
    // Should warn about missing $a and $b (and PhanParamTooMany), but not $x
    $fn = fn() => [
        $z,
        new class ($a, $b) { public function __construct($first) {} public static function test(int $x) { return $x;}},
        Test::no_args(
            $c,
            $d
        ),

    ];
    return $fn;
});
