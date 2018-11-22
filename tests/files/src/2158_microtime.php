<?php  declare(strict_types=1);

$bool = rand(0, 1) == 0;

assert_float(microtime()); // PhanTypeMismatchArgument
assert_string(microtime());
assert_float(microtime($bool));
assert_string(microtime($bool));

assert_float(microtime(true));
assert_float(microtime(false)); // PhanTypeMismatchArgument
assert_string(microtime(true)); // PhanTypeMismatchArgument
assert_string(microtime(false));

function assert_float(float $v) {}
function assert_string(string $v) {}
