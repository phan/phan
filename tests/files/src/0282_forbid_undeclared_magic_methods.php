<?php

/**
 * @method array instanceMethod(?int $arg)
 * @method static string static_method(string $arg)
 * @phan-forbid-undeclared-magic-methods
 */
class Magic282 {
    public function __call(string $name, array $args) {
        if ($name == 'instanceMethod') {
            return $args;
        }
        throw new RuntimeException("Bad instance method name");
    }

    public static function __callStatic(string $name, array $args) {
        if ($name == 'static_method') {
            return 'prefix' . $args[0];
        }
        throw new RuntimeException("Bad static method name");
    }
}

function test282() {
    $m = new Magic282();
    $m->instanceMethod(null);  // valid
    $m->instanceMethod("str");  // valid
    $m->missingMethod();  // should warn, because of @phan-forbid-undeclared-magic-methods
    $v = $m->instanceMethod();  // should warn
    echo intdiv($v, 2);  // warn about passing array, expect int.

    Magic282::static_method(22);  // should warn about wrong type
    $s = Magic282::static_method('str');
    echo intdiv($s, 2);  // warn about passing string, expect int
    // Should warn about undeclared static methods because of @phan-forbid-undeclared-magic-methods
    Magic282::undeclared_magic_static_method();
}
