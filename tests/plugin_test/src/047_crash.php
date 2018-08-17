<?php
// NOTE: This is a regression test for a bug that occurs only when dead code detection/reference tracking is enabled.

class X47 {
    const MY_METHOD = 'myMethod';
    public static function myMethod(int $arg) {}
}
/**
 * @param object $unknown
 */
function example($unknown) {
    X47::{X47::MY_METHOD}('my arg');

    // We don't expect this to warn, the class is unknown
    $unknown::{X47::MY_METHOD}(42);
}
