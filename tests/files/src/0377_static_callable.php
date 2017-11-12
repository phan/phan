<?php

class TestStaticCallable377 {
    public static function main() {
        var_export(array_map([static::class, 'triple'], [2, 3, -1]));
        var_export(array_map(Closure::fromCallable([static::class, 'triple']), [2, 3, -1]));

        var_export(array_map([self::class, 'triple'], [2, 3, -1]));
        var_export(array_map(Closure::fromCallable([self::class, 'triple']), [2, 3, -1]));

        var_export(array_map([self::class, 'triple'], [new stdClass()]));
        var_export(array_map(Closure::fromCallable([self::class, 'triple']), [new stdClass()]));

        var_export(array_map('self::triple', [2, 3, -1]));
        var_export(array_map('static::triple', [2, 3, -1]));

        var_export(array_map('self::triple', [[]]));
        var_export(array_map('static::triple', [[]]));

        var_export(array_map([static::class, 'triple'], [new stdClass()]));  // should warn
        var_export(array_map(Closure::fromCallable([static::class, 'triple']), [new stdClass()]));  // should warn
    }

    public static function triple(int $x) : int {
        return $x * 3;
    }
}
TestStaticCallable377::main();
