<?php  declare(strict_types=1);

$cond1 = version_compare(); // PhanParamTooFewInternal
$cond2 = version_compare('0.1.1', '0.1.2-rc', '>=');
assert_int(version_compare('0.1.1', '0.1.2-rc')); // ok
assert_int(version_compare('0.1.1', '0.1.2-rc', '>='));
assert_bool(version_compare('0.1.1', '0.1.2-rc'));
assert_bool(version_compare('0.1.1', '0.1.2-rc', '>=')); // ok

function assert_int(int $v) {}
function assert_bool(bool $v) {}
