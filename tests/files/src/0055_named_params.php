<?php
/**
 * @param float $b
 */
function f($a, $b) {}
f('string', 3.14159);
f(3.14159, 'string');

/**
 * @param float|int|Traversable $b
 */
function g($a, $b) {}
g('string', 42);
g(42, 'string');

/**
 * @param int
 * @param string
 */
function h($a, $b) {}
h('string', 42);
h(42, 'string');
