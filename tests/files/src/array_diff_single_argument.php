<?php
// Regression test for https://github.com/phan/phan/issues/5566
// Since PHP 8.0, array_diff(), array_intersect() and their variants only require one array.

/**
 * $arrays may be empty, so this still warns that 0 args may be passed (consistent with max(...$arrays))
 * @param array[] $arrays
 */
function intersectAll5566(array $arrays): array {
    return array_intersect(...$arrays);
}

/** @param non-empty-list<array> $arrays */
function intersectAllNonEmpty5566(array $arrays): array {
    return array_intersect(...$arrays);
}

/**
 * @param list<int> $a
 * @param list<int> $b
 * @param list<int> $c
 */
function single5566(array $a, array $b, array $c): void {
    // One array is valid for all six plain functions
    var_dump(array_diff($a));
    var_dump(array_diff_assoc($a));
    var_dump(array_diff_key($a));
    var_dump(array_intersect($a));
    var_dump(array_intersect_assoc($a));
    var_dump(array_intersect_key($a));
    var_dump(array_diff_key($a, $b, $c));
    $result = array_diff($a);
    '@phan-debug-var $result';
    var_dump($result);

    // Still invalid
    var_dump(array_intersect());
    var_dump(array_intersect($a, 'x'));
    var_dump(array_diff($a, $b, 'x'));

    // Callback variants accept one array plus the callback(s)
    var_dump(array_udiff($a, 'strcmp'));
    var_dump(array_udiff($a, $b, 'strcmp'));
    var_dump(array_udiff($a, $b, $c, 'strcmp'));
    var_dump(array_udiff_assoc($a, 'strcmp'));
    var_dump(array_udiff_uassoc($a, 'strcmp', 'strcmp'));
    var_dump(array_uintersect($a, 'strcmp'));
    var_dump(array_uintersect_assoc($a, 'strcmp'));
    var_dump(array_uintersect_uassoc($a, 'strcmp', 'strcmp'));
    var_dump(array_diff_ukey($a, 'strcmp'));
    var_dump(array_diff_uassoc($a, 'strcmp'));
    var_dump(array_intersect_ukey($a, 'strcmp'));
    var_dump(array_intersect_uassoc($a, 'strcmp'));

    // Still invalid: the callback is not a callable
    var_dump(array_udiff($a, 42));
    // Still invalid: with three or more arguments, argument 2 must be an array.
    // The one-array alternate signature must not be used for type checking here.
    var_dump(array_diff_ukey($a, 'strcmp', 'strcmp'));
    var_dump(array_udiff($a, 'strcmp', 'strcmp'));
    var_dump(array_udiff_uassoc($a, 'strcmp', 'strcmp', 'strcmp'));
    var_dump(array_intersect_uassoc($a, 'strcmp', 'strcmp'));
    // Still invalid: missing everything
    var_dump(array_udiff($a));
    // Missing callback: not detected, since Phan allows arrays to cast to callable
    var_dump(array_udiff($a, $b));
}
