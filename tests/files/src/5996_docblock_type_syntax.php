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

/**
 * A description after a type on several lines is not a type, whatever it has.
 *
 * @param array{
 *     a: int,
 *     b: string,
 * } $opts Pass a) foo or b) bar
 * @param array{
 *     x: int,
 * } $opts2 Must be > 0
 * @param array{
 *     y: int,
 * } $opts3 Note (don't break
 *     across lines)
 * @param \Closure(
 *     int,
 *     string
 * ): bool $closure
 * @return array{
 *     a: int,
 * }
 * |array{
 *     b: string,
 * } A record (it's useful).
 */
function afterTheType($opts, $opts2, $opts3, $closure) {
    '@phan-debug-var $opts, $opts2, $opts3, $closure';
    return ['a' => 1];
}

/**
 * A `?` in a key in quotes is a part of the key, and `'...'` is a literal string.
 *
 * @param array{'why?': int, 'maybe'?: int} $keys
 * @return ($x is '...' ? int : string)
 */
function questionMarks($keys, $x) {
    '@phan-debug-var $keys';
    echo $keys['why?'];
    return $x === '...' ? 1 : 'a';
}

/**
 * `numeric` is any string for Phan: a condition with it does not decide that a call does not return.
 *
 * @return void
 * @phpstan-return ($x is numeric ? never : void)
 */
function dieIfNumeric($x): void {
    if (is_numeric($x)) {
        exit;
    }
}

function testNumeric(?string $s): string {
    if ($s === null) {
        dieIfNumeric('not-a-number');
    }
    return $s;
}

$after = [afterTheType([], [], [], function (int $i, string $s): bool { return $i > 0 && $s !== ''; }), questionMarks([], '...')];
'@phan-debug-var $after';

$results = [twoOpenBrackets(), wrappedAfterComma(), descriptions([]), conditional([]), numbers(1, null, '1', [], new Number())];
'@phan-debug-var $results';
