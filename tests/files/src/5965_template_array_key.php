<?php
// A template used as the key type of an array parameter (array<TKey,TValue>) is inferred
// from the keys of the argument, not from the whole array.

/**
 * @template TKey of array-key
 * @template TValue
 * @param array<TKey, TValue> $items
 * @return array<TKey, TValue>
 */
function identity_5965(array $items): array { return $items; }

/**
 * @template TKey
 * @param array<TKey, mixed> $items
 * @return list<TKey>
 * @suppress PhanTypeMismatchReturn
 */
function keys_5965(array $items): array { return array_keys($items); }

/**
 * @template TKey of int
 * @param array<TKey, mixed> $items
 * @return TKey
 * @suppress PhanTypeMismatchReturn
 */
function first_int_key_5965(array $items) { return array_key_first($items); }

/** @param array<string,int> $map */
function test_5965(array $map): void {
    $a = identity_5965(['a' => 1]);                // no PhanTemplateTypeConstraintViolation
    '@phan-debug-var $a';
    $b = identity_5965($map);
    '@phan-debug-var $b';
    $c = keys_5965(['x' => 1, 'y' => 2]);
    '@phan-debug-var $c';
    var_export([$a, $b, $c]);
    echo intdiv(first_int_key_5965([5 => 'v']), 2);
    var_export(first_int_key_5965(['x' => 1]));    // still reported: 'x' is not an int
}
