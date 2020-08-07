<?php
/**
 * @param Closure(int $name):void $cb
 */
function test_closure(Closure $cb) {
    $cb(other: 0);
    $cb(name: 0);
    $other_cb = function (int $a = 0, bool $flag = false) {
        var_export([$a, $flag]);
    };
    $other_cb(a: 1, flag: true);
    $other_cb(flag: true, a: 1);
    $other_cb(flag: 1, a: true);
}
