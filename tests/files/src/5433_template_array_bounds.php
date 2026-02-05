<?php

/**
 * Test case for GitHub issue #5433
 * Template type bounds not respected for array operations
 *
 * @see https://github.com/phan/phan/issues/5433
 */

/**
 * Test 1: Template bounded by array should allow array access
 * @template T of array
 * @param T $arr
 * @return mixed
 */
function accessArrayTemplate5433(array $arr) {
    // This should NOT emit PhanTypeArraySuspicious since T is bounded by array
    return $arr[0];
}

/**
 * Test 2: Template bounded by int[] should allow array access
 * @template T of int[]
 * @param T $arr
 * @return int
 */
function accessTypedArrayTemplate5433(array $arr): int {
    // This should NOT emit PhanTypeArraySuspicious
    return $arr[0];
}

/**
 * Test 3: Template bounded by array shape should allow array access
 * @template T of array{key: string}
 * @param T $arr
 * @return string
 */
function accessArrayShapeTemplate5433(array $arr): string {
    // This should NOT emit PhanTypeArraySuspicious
    return $arr['key'];
}

/**
 * Test 4: Template bounded by object (not array) should emit warning for array access
 * @template T of object
 * @param T $obj
 * @return mixed
 */
function accessObjectTemplate5433(object $obj) {
    // This SHOULD emit PhanTypeArraySuspicious since T is bounded by object, not array
    return $obj[0];
}

/**
 * Test 5: Template bounded by iterable should allow iteration
 * @template T of iterable
 * @param T $items
 * @return void
 */
function iterateTemplate5433($items): void {
    // This should NOT emit any issues since T is bounded by iterable
    foreach ($items as $item) {
        echo $item;
    }
}

/**
 * Test 6: Unbounded template should conservatively allow array access
 * (since it could be instantiated with an array-like type)
 * @template T
 * @param T $value
 * @return mixed
 */
function accessUnboundedTemplate5433($value) {
    // Conservative behavior: no warning since T could be array-like
    return $value[0];
}

/**
 * Test 7: Template bounded by string should emit warning for array access
 * (Phan does not consider strings as array-like, even though PHP supports $str[0])
 * @template T of string
 * @param T $str
 * @return string
 */
function accessStringTemplate5433(string $str): string {
    // This SHOULD emit PhanTypeArraySuspicious since strings aren't considered array-like
    return $str[0];
}

// Usage tests
$a = accessArrayTemplate5433([1, 2, 3]);
$b = accessTypedArrayTemplate5433([10, 20, 30]);
$c = accessArrayShapeTemplate5433(['key' => 'value']);

class TestObj5433 {}
$obj = new TestObj5433();
$d = accessObjectTemplate5433($obj);

iterateTemplate5433([1, 2, 3]);
iterateTemplate5433(new ArrayIterator([1, 2, 3]));

$e = accessUnboundedTemplate5433(['item']);
$f = accessStringTemplate5433('hello');
