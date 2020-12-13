<?php
function test_redundant($size = null) {
    '@phan-debug-var $size';  // mixed
    if (is_null($size) || $size == '0') {
        '@phan-debug-var $size';  // Could be improved to include other loosely equal types such as false in the real type set.
        $size = null;  // Should not emit PhanPluginRedundantAssignment
    }
    return $size;
}
