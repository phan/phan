<?php

class Example42 {
    const DEFAULT = 'x';
    public static function test($x = self::DEFAULT) {
        var_export($x);
    }
}

Example42::test();
Example42::test('y');
Example42::test(new stdClass());
