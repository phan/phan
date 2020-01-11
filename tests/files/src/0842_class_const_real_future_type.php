<?php
class MyClass {
    const VALS = [
        'a' => null,
        'b' => true,
    ];
    function test(string $s) : array {
        $v = self::VALS[$s];
        '@phan-debug-var $v';
        return $v;
    }
}
