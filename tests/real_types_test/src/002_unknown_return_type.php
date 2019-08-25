<?php
/** @param array<string,stdClass> $x */
function test2(array $x) {
    return array_values($x);
}
class MyClass2 {
    /** @param array<string,array> $x */
    public static function test(array $x) {
        return array_values($x);
    }
}
var_export(test2(['x' => new stdClass()]));
var_export(MyClass2::test(['x' => []]));
