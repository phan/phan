<?php
// Regression test: reading an offset of a union of an array shape and `mixed`
// (e.g. `$g[$k]['tax']` after `$g[$k]['tax'] = $v;` on an `array $g` with an unknown key $k, where `$g[$k]` is
// `array{tax:int}|mixed`) is the shape's field type; the `mixed` alternative must not add an unwarranted null.
// Only an array that may itself be null (`null`, `?array{...}`, `?mixed`, an unbounded template type) still does.
class Money5948 {}
function fmt5948(Money5948 $m): void {
    var_export($m);
}
// The field type is unknown (untyped parameter): the element is unknown, not null.
function untyped_value5948(array $g, string $k, $v): void {
    $g[$k]['tax'] = $v;
    $tax = $g[$k]['tax'];
    '@phan-debug-var $tax';
    fmt5948($g[$k]['tax']);
}
// The field type is known: the element is int, not int|null.
function typed_value5948(array $g, string $k, int $v): void {
    $g[$k]['tax'] = $v;
    $tax = $g[$k]['tax'];
    '@phan-debug-var $tax';
    fmt5948($g[$k]['tax']);
}
/** @param array{tax:int}|mixed $x */
function shape_or_mixed5948($x): void {
    $tax = $x['tax'];
    '@phan-debug-var $tax';
}
/** @param array{tax:int}|array<string,string> $x */
function shape_or_typed_array5948(array $x): void {
    $tax = $x['tax'];
    '@phan-debug-var $tax';
    $other = $x['other'];
    '@phan-debug-var $other';
}
/** @param array{tax:int}|array $x */
function shape_or_array5948(array $x): void {
    $tax = $x['tax'];
    '@phan-debug-var $tax';
}
/** @param array{tax:int}|null $x */
function nullable_shape5948(?array $x): void {
    $tax = $x['tax'];
    '@phan-debug-var $tax';
}
/** @param array{tax:int} $x */
function shape_only5948(array $x): void {
    $tax = $x['tax'];
    '@phan-debug-var $tax';
    fmt5948($x['tax']);
}
/** @param array{tax:int}|list<string> $x */
function shape_or_list5948(array $x): void {
    $tax = $x['tax'];
    '@phan-debug-var $tax';
    $first = $x[0];
    '@phan-debug-var $first';
}
/**
 * An unbounded template type may be null, unlike `mixed`.
 * @template T
 * @param array{tax:int}|T $x
 */
function shape_or_template5948($x): void {
    $tax = $x['tax'];
    '@phan-debug-var $tax';
}
/** @param array{tax:int}|?mixed $x */
function shape_or_nullable_mixed5948($x): void {
    $tax = $x['tax'];
    '@phan-debug-var $tax';
}
