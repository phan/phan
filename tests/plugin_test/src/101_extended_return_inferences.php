<?php

// Demonstrates the return inferences possible when enable_extended_internal_return_type_plugins is enabled

/**
 * @param 0 $x
 */
function expect_zero($x) {
    var_export($x);
}
expect_zero(json_decode('2'));
expect_zero(json_decode('{}', true));
expect_zero(json_decode('[2]', true));
expect_zero(json_decode('{}', false));
expect_zero(implode(',', [1]));
expect_zero(implode(',', [1, 2]));
expect_zero(implode([1, 2]));
expect_zero(json_encode(0));
expect_zero(strrev('test'));
expect_zero(strpos('test', 's'));
expect_zero(strripos('test', 'ES'));
expect_zero(strlen(11));
expect_zero(strlen('test'));
expect_zero(ord('x'));
expect_zero(strtoupper('Test'));
expect_zero(strtolower('Test'));
