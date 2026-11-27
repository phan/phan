<?php

namespace NS5998;

// The `&` of a literal string is not the `&` of an intersection type.

/**
 * @param '&' $a
 * @param 'a&b'|'c' $b
 * @param array{sep: '&'|'&amp;', n: int} $c
 * @param array<'&', int> $d
 * @param \Countable&\ArrayAccess $e
 * @param 'x&y'|(\Countable&\ArrayAccess)|null $f
 * @param list<'a&b'> $g
 * @return 'R&D'
 */
function f($a, $b, $c, $d, $e, $f, $g) {
    '@phan-debug-var $a, $b, $c, $d, $e, $f, $g';
    return 'R&D';
}
$r = f('&', 'c', ['sep' => '&', 'n' => 1], [], new \ArrayObject(), null, []);
'@phan-debug-var $r';
