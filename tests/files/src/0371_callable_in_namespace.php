<?php
namespace Foo\NS371;

function strlen371(string $x) : int {
    return \strlen($x);
}
class C371 {
    public static function strlen371(string $x) : int {
        return \strlen($x);
    }
}

namespace Foo\Bar;

use stdClass;

/** @return string[] (incorrect) */
function my_test371B() {
    $str = 'x';
    return array_map('Foo\NS371\strlen371', [$str]);
}

/** @return int[] (correct) */
function my_test371C() {
    $obj = new stdClass();
    return array_map('\Foo\NS371\strlen371', [$obj]);
}

/** @return int[] (correct) */
function my_test371D() {
    $obj = new stdClass();
    return array_map('\Foo\NS371\C371::strlen371', [$obj]);
}

/** @return string[] (incorrect) */
function my_test371E() {
    $str = 'x';
    return array_map('Foo\NS371\C371::strlen371', [$str]);
}

my_test371E();
my_test371D();
my_test371C();
my_test371B();

class C371 {
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
function test371C() {
    $str = 'x';
    return array_map('Foo\Bar\C371::createObject', [$str]);
}

/** @return string[] (incorrect, returns stdClass[]) */
function test371C2() {
    $str = 'x';
    return array_map([C371::class, 'createObject'], [$str]);
}

/** @return string[] */
function missingClass371C() {
    $str = 'x';
    return array_map('Foo\Bar\Missing371::createObject', [$str]);
}

/** @return string[] */
function missingClass371C2() {
    $str = 'x';
    return array_map('C371::createObject', [$str]);
}

/** @return string[] */
function missingClass371C3() {
    $str = 'x';
    return array_map([Missing371::class, 'createObject'], [$str]);
}

/** @return string[] (incorrect) */
function test371D() {
    $str = 'x';
    return array_map([new C371, 'createArray'], [$str]);
}

function accepts_no_args() {
}

/** @return array */
function test371E() {
    $str = 'x';
    return array_map('Foo\Bar\accepts_no_args', [$str]);
}

function accepts_twoargs($x, $y) : array {
    return [$x, $y];
}

/** @return array */
function test371ETooFew() {
    $str = 'x';
    return array_map('\Foo\bar\accepts_twoargs', [$str]);
}

/** @return string[] */
function accepts_enough_args($x = 'default', $y = 'other_default') : array {
    return [$x, $y];
}

/** @return int[] (incorrect, actually string[][])*/
function test371EEnough() {
    $str = 'x';
    return array_map('\accepts_enough_args', [$str]);
}

/** @return string[] (incorrect) */
function test371F() {
    $str = 'x';
    return array_map([new C371, 'missingMethod'], [$str]);
}

/** @return string[] (actually Node[]) */
function test371GNamespacedValid() {
    $str = '<?php 2; ?>';
    return array_map('ast\parse_code', [$str], [50]);
}

/** @return string[] */
function test371H() {
    $str = 'x';
    return array_map([C371::class, 'missing_static_method'], [$str]);
}

/** @return string[] */
function test371HString() {
    $str = 'x';
    return array_map('C371::missing_static_method2', [$str]);
}
