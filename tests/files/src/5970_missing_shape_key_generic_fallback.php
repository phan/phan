<?php
// A key that is missing from an array shape gets its type from the generic arrays of the union,
// not from the unrelated fields of the shape.

function top5970(array $x): void {
    if (is_string($x['m']['c'])) {
        // $x is array{m:array{c:string}}|non-empty-array<mixed,mixed>: $x['n'] is not array{c:string}
        echo array_sum($x['n']['c']);
        // True positive
        echo array_sum($x['m']['c']);
    }
}
function nested5970(array $x): void {
    if (is_string($x['a']['b']['c'])) {
        echo array_sum($x['a']['d']['c']);
        echo array_sum($x['a']['d']);
        echo array_sum($x['a']['b']['c']);
    }
}
/**
 * The types of the elements of typed generic arrays are used
 * @param array{tax:int}|array<string,string> $typed
 * @param array{tax:int}|list<float> $list
 * @param array{tax:int}|array<string,string>|array<string,bool> $two
 */
function typed5970(array $typed, array $list, array $two): void {
    $a = $typed['other'];
    $b = $list[0];
    $c = $two['other'];
    $d = $typed['tax'];
    '@phan-debug-var $a, $b, $c, $d';
}
/**
 * Unknown when another type may have the key
 * @param array{tax:int}|\ArrayAccess<string,string> $object
 * @param array{tax:int}|mixed $mixed
 */
function unknown5970($object, $mixed): void {
    $a = $object['other'];
    $b = $mixed['other'];
    '@phan-debug-var $a, $b';
}
/**
 * A shape without generic arrays is still checked
 * @param array{tax:int} $shape
 */
function shape5970(array $shape): void {
    echo $shape['other'];
}
/**
 * The offset is still checked against the generic arrays
 * @param array{0:int}|array<int,string> $int_keys
 * @param ?array{tax:int}|array<string,string> $nullable
 */
function checked5970(array $int_keys, ?array $nullable): void {
    echo $int_keys['other'];
    echo $nullable['other'];
}
