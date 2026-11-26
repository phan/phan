<?php

namespace NS5991;

class Kind {
    const A = 1;
    const TYPE_FEED = 'feed';
    const TYPE_IMAGE = 'image';
}

/**
 * Whitespace inside of an array shape
 *
 * @param array{ openByDefault: bool } $a
 * @param array<string, array{ 0: string, 1: bool }> $b
 * @param array{ a: int }|null $c
 * @return array{ file: non-empty-string, size?: int }
 */
function shapes($a, $b, $c): array {
    '@phan-debug-var $a, $b, $c';
    return ['file' => 'x'];
}

/**
 * Types that Phan has under another name, or has no type of its own for
 *
 * @param non-negative-int $d
 * @param numeric-string $e
 * @param non-positive-int|null $f
 * @param literal-string $g
 * @param truthy-string $h
 * @param non-empty-literal-string $i
 * @param list<non-negative-int> $j
 * @param ?non-negative-int $k
 */
function names($d, $e, $f, $g, $h, $i, $j, $k): void {
    '@phan-debug-var $d, $e, $f, $g, $h, $i, $j, $k';
}

/**
 * The value of a class constant can be anything; the rest of the type is still used.
 *
 * @param Kind::TYPE_FEED|Kind::TYPE_IMAGE $l
 * @param array<int, self::A|Kind::A> $m
 * @param Kind::TYPE_* $n
 * @param \NS5991\Kind::A|null $o
 */
function constants($l, $m, $n, $o): void {
    '@phan-debug-var $l, $m, $n, $o';
}

/**
 * A literal string in double quotes
 *
 * @param "UTF-8"|null $p
 * @param "a"|'b' $q
 */
function literals($p, $q): void {
    '@phan-debug-var $p, $q';
}

function calls(int $n): void {
    names(0, '1', null, 'x', 'x', 'x', [0, 1], null);
    names(-1, '1', 1, 'x', 'x', 'x', [], $n);
    literals('UTF-8', 'a');
    literals('latin1', 'c');
}
