<?php

/**
 * Nonsense comment. This gets parsed as an $x being an int, $y being array of arrays,
 * @param int ...$x
 * @param array $y
 */
function badvariadic258($x, ...$y) {
}

/**
 * Parsed as $y being a variadic array of integers
 * @param $x
 * @param int $y
 */
function badvariadic258b($x, ...$y) {
}

class Variadic258 {
    /**
     * Nonsense comment. This gets parsed as an $x being an int, $y being array of arrays,
     * @param int ...$x
     * @param array $y
     */
    public static function bar($x, ...$y) {}

    /**
     * Parsed as $z being a variadic array of integers
     * @param $x
     * @param int $z
     */
    public static function baz($x = null, ...$z) {}
}

badvariadic258(2, [3], [4]);
badvariadic258b(2, 3, []);
Variadic258::bar(2, [3], [4]);
Variadic258::baz(2, 3);
