<?php

class C764 {
    private static function bindAndCheck()
    {
        $byFn = function (stdClass $x) {
            return isset($x->prop);
        };

        $boundByFn = Closure::bind($byFn, null, self::class);
        if ($boundByFn === false) {  // Not PhanImpossibleTypeComparison
            throw new RuntimeException();
        }

        return $boundByFn;
    }
}
