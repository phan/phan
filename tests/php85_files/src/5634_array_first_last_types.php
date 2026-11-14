<?php

namespace NS5634;

use stdClass;

/**
 * array_first() and array_last() should infer the element type of the array, instead of mixed.
 *
 * @param list<string> $list
 * @param non-empty-list<string> $non_empty_list
 * @param array{a:int,b:string} $shape
 * @param array<string,stdClass> $objects
 */
function test(array $list, array $non_empty_list, array $shape, array $objects, array $plain): void {
    $a = array_first($list);
    '@phan-debug-var $a';
    $b = array_last($list);
    '@phan-debug-var $b';

    // No null for arrays that are known to be non-empty
    $c = array_first($non_empty_list);
    '@phan-debug-var $c';
    $d = array_last($non_empty_list);
    '@phan-debug-var $d';
    if ($list) {
        $e = array_first($list);
        '@phan-debug-var $e';
    }

    $f = array_first($shape);
    '@phan-debug-var $f';
    $g = array_last($shape);
    '@phan-debug-var $g';

    $h = array_first($objects);
    '@phan-debug-var $h';
    $i = array_last($objects);
    '@phan-debug-var $i';

    $j = array_first([1, 2, 3]);
    '@phan-debug-var $j';

    // Nothing is known about the elements
    $k = array_first($plain);
    '@phan-debug-var $k';

    // The inferred types are used to detect real problems
    echo strlen($h);
    echo count(array_last($list));
    echo $i->undeclaredMethod();
}
test([], ['x'], ['a' => 1, 'b' => 'x'], [], []);
