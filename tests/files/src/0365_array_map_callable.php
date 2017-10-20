<?php

/** @return string[] (incorrect) */
function test365A() {
    $str = 'x';
    // Also providing the wrong type to array_map
    return array_map(function(int $x) : int { return $x * 2; }, [$str]);
}

/** @return string[] (incorrect) */
function test365B() {
    $str = 'x';
    return array_map('strlen', [$str]);
}

class C365 {
    public static function createObject(string $x) : stdClass {
        return (object)['key' => $x];
    }

    /**
     * @return int[]
     */
    public function createArray(int $x) {
        return [$x];
    }
}

/** @return string[] (incorrect, returns stdClass[]) */
function test365C() {
    $str = 'x';
    return array_map('C365::createObject', [$str]);
}

/** @return string[] */
function missingClass365C() {
    $str = 'x';
    return array_map('Missing365::createObject', [$str]);
}

/** @return string[] (incorrect) */
function test365D() {
    $str = 'x';
    return array_map([new C365, 'createArray'], [$str]);
}

function accepts_no_args() {
}

/** @return array */
function test365E() {
    $str = 'x';
    return array_map('accepts_no_args', [$str]);
}

function accepts_twoargs($x, $y) : array {
    return [$x, $y];
}

/** @return array */
function test365ETooFew() {
    $str = 'x';
    return array_map('\accepts_twoargs', [$str]);
}

/** @return string[] */
function accepts_enough_args($x = 'default', $y = 'other_default') : array {
    return [$x, $y];
}

/** @return int[] (incorrect, actually string[][])*/
function test365EEnough() {
    $str = 'x';
    return array_map('\accepts_enough_args', [$str]);
}

/** @return string[] (incorrect) */
function test365F() {
    $str = 'x';
    return array_map([new C365, 'missingMethod'], [$str]);
}

/** @return string[] */
function test365G() {
    $str = 'x';
    return array_map('missing_global_function365', [$str]);
}

/** @return string[] */
function test365GNamespaced() {
    $str = 'x';
    return array_map('MyNS\\missing_global_function365', [$str]);
}

/** @return string[] (actually Node[]) */
function test365GNamespacedValid() {
    $str = '<?php 2; ?>';
    return array_map('ast\parse_code', [$str], [50]);
}

/** @return string[] */
function test365H() {
    $str = 'x';
    return array_map(['C365', 'missing_static_method'], [$str]);
}

/** @return string[] */
function test365HString() {
    $str = 'x';
    return array_map('C365::missing_static_method2', [$str]);
}
