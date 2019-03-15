<?php

/**
 * @template TTemplate1
 * @template TTemplate2
 * @param TTemplate1 $a
 * @param TTemplate2 $b
 * @return array<int,TTemplate1|TTemplate2>
 */
function test_template($a, $b) {
    // Should warn about undeclared classes : Templates only make sense in PHPDoc
    call_user_func(function (TTemplate1 $x) : TTemplate1 {
        var_export($x);
        return $x;
    }, $a);
    // Should warn about undeclared classes : Templates only make sense in PHPDoc
    call_user_func(function (\TTemplate2 $y) : namespace\TTemplate2{
        var_export($y);
        return $y;
    }, $b);
    /**
     * Should not warn
     * @param TTemplate2 $y
     * @return TTemplate2
     */
    call_user_func(function ($y) {
        var_export($y);
        return $y;
    }, $b);
    /**
     * Should warn
     * @param \TTemplate2 $y
     * @return \TTemplate2
     */
    call_user_func(function ($y) {
        var_export($y);
        return $y;
    }, $b);
    // Should warn about undeclared class TTemplate1
    var_export(new TTemplate1());
    return [$a, $b];
}
test_template(new stdClass(), new ArrayObject());
