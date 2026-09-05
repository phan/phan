<?php
// Regression test for https://github.com/phan/phan/issues/5565
// `!array_is_list($x)` should convert array types to non-empty associative arrays,
// not wrap them as the element type of a new associative array.

class Test5565 {
    /** @var string[] */
    public $testProperty = [];
}

function example5565(string $json): void {
    $array = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    if (array_is_list($array)) {
        return;
    }
    '@phan-debug-var $array';
    $t = new Test5565();
    // Should not warn: $array['test'] is mixed
    $t->testProperty = $array['test'];
}

/**
 * @param array<string,int> $string_keys
 * @param int[] $ints
 * @param list<int> $list
 * @param list<int>|array{a:int} $list_or_shape
 * @param array{0:int,1?:int} $always_list_shape
 * @param array{0?:int,1:int} $maybe_list_shape
 * @param non-empty-list<string> $non_empty_list
 * @param associative-array<string> $assoc
 * @param array|false $array_or_false
 * @param callable $callable
 */
function negated5565(
    array $plain,
    array $string_keys,
    array $ints,
    array $list,
    array $list_or_shape,
    array $always_list_shape,
    array $maybe_list_shape,
    array $non_empty_list,
    array $assoc,
    $array_or_false,
    $callable,
    $unknown
): void {
    if (!array_is_list($plain)) {
        '@phan-debug-var $plain';
    } else {
        '@phan-debug-var $plain';
    }
    if (!array_is_list($string_keys)) {
        '@phan-debug-var $string_keys';
    }
    if (!array_is_list($ints)) {
        '@phan-debug-var $ints';
    }
    if (!array_is_list($list)) {
        '@phan-debug-var $list';
    }
    if (!array_is_list($list_or_shape)) {
        '@phan-debug-var $list_or_shape';
    }
    if (!array_is_list($always_list_shape)) {
        '@phan-debug-var $always_list_shape';
    }
    if (!array_is_list($maybe_list_shape)) {
        '@phan-debug-var $maybe_list_shape';
    }
    if (!array_is_list($non_empty_list)) {
        '@phan-debug-var $non_empty_list';
    }
    if (!array_is_list($assoc)) {
        '@phan-debug-var $assoc';
    }
    if (!array_is_list($array_or_false)) {
        '@phan-debug-var $array_or_false';
    }
    if (is_array($callable) && !array_is_list($callable)) {
        '@phan-debug-var $callable';
    }
    if (!array_is_list($unknown)) {
        '@phan-debug-var $unknown';
    }
}
