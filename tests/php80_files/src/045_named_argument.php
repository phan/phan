<?php
/**
 * @no-named-arguments
 */
function foo(int $a, int ...$args) {}

foo(a: 123);
foo(b: 456);
foo(a: 123, b: 456);
foo(123, 456);
foo(123, ...['a-b' => 123]);
foo(123, ...['' => 123]);
