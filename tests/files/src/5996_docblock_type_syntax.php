<?php

namespace NS5996;

/**
 * Types on several lines: two brackets that are open on the first line, and a line that ends within a bracket.
 *
 * @return array<string, array{
 *      requiredValue: mixed,
 *      policyTitle: string,
 *  }>
 */
function twoOpenBrackets() {
    return [];
}

/**
 * @return iterable<array{idsite: int|string, name: string,
 *     ts_archived: string}>
 */
function wrappedAfterComma() {
    return [];
}

/**
 * @param array{
 *     'first-key': int,
 *     second: array{
 *         inner: string,
 *     },
 *     ...
 * } $multi The description (with brackets,
 *     on two lines) is not a type.
 * @param int $after
 */
function nestedLines($multi, $after) {
    '@phan-debug-var $multi, $after';
}

/**
 * Not joined: the type ends on its first line.
 * @param array $x a description (that goes on,
 *     here)
 * @return array{a: int} (and this one,
 *     too)
 */
function descriptions($x) {
    '@phan-debug-var $x';
    return ['a' => 1];
}

/**
 * Keys in quotes, and array shapes that can have other keys.
 *
 * @param array{'Content-Type': string, "a b"?: int, 'x:y': bool, plain: int} $quoted
 * @param array{message: string, line: int, ...} $unsealed
 * @param list<array{id: int, ...}> $nested
 * @param array{a: int, ...<string, mixed>}|null $typed
 */
function shapes($quoted, $unsealed, $nested, $typed) {
    '@phan-debug-var $quoted, $unsealed, $nested, $typed';
    echo $quoted['Content-Type'], $quoted['a b'] ?? 0, $quoted['x:y'];
    // Another key is not an invalid offset of an array shape that can have other keys.
    echo $unsealed['other'], $nested[0]['x'], $typed['zz'] ?? 1;
    echo $quoted['missing'];
}

/**
 * A condition with an array shape that can have other keys is not one that Phan can check: the annotation is not used.
 * @return int[]|string[]
 * @phpstan-return ($args is array{fields: 'ids', ...} ? int[] : string[])
 */
function conditional(array $args) {
    return [];
}

/**
 * `number` of PHPDoc and `numeric` of PHPStan and Psalm.
 *
 * @param number $number
 * @param ?number $nullable
 * @param numeric $numeric
 * @param array<string, numeric> $map
 * @param Number $class a class
 * @return number
 */
function numbers($number, $nullable, $numeric, $map, $class) {
    '@phan-debug-var $number, $nullable, $numeric, $map, $class';
    return $number;
}

class Number {}

$results = [twoOpenBrackets(), wrappedAfterComma(), descriptions([]), conditional([]), numbers(1, null, '1', [], new Number())];
'@phan-debug-var $results';
