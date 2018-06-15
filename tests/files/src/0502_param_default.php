<?php

class Example502 {
    const DEFAULT = 'x';
    public static function test($x = self::DEFAULT) {
        var_export($x);
    }
}

Example502::test();
Example502::test('y');
Example502::test(new stdClass());
