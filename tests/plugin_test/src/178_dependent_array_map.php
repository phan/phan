<?php
/** @return array<string, mixed> */
function test178() {
    $result = array_map('strtolower', ['a', 'b']);  // TODO: Support enable_extended_internal_return_type_plugins in array_map for a reasonable element limit
    return $result;
}
var_export(test178());
