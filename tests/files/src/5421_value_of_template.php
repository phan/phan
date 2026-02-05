<?php

/**
 * Test case for GitHub issue #5421
 * value-of<T> and key-of<T> with template types
 *
 * @see https://github.com/phan/phan/issues/5421
 */

/**
 * @template T of array
 * @param T $arr
 * @param int|string $key
 * @return value-of<T>
 */
function getValue5421(array $arr, $key) {
    return $arr[$key];
}

/**
 * @template T of array
 * @param T $arr
 * @return key-of<T>|null
 */
function getKey5421(array $arr) {
    // @phan-suppress-next-line PhanTypeMismatchArgumentInternal
    return array_key_first($arr);
}

// Test value-of<T> with list
$a = [1, 2, 3];
$b = getValue5421($a, 0);
'@phan-debug-var $b';  // Should be int (1|2|3), not mixed

// Test value-of<T> with associative array
$c = ['foo' => 'bar', 'baz' => 'qux'];
$d = getValue5421($c, 'foo');
'@phan-debug-var $d';  // Should be 'bar'|'qux', not mixed

// Test key-of<T> with associative array
$e = ['foo' => 1, 'bar' => 2];
$f = getKey5421($e);
'@phan-debug-var $f';  // Should be 'foo'|'bar'|null, not array-key|null

/**
 * @param int[] $arr
 * @return void
 */
function testWithIntArray5421(array $arr) {
    $v = getValue5421($arr, 0);
    '@phan-debug-var $v';  // Should be int
    echo $v;  // Use $v to avoid unused warning
}
testWithIntArray5421([1, 2, 3]);
