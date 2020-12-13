<?php

function test_void_real() : void {
}

function expects_array_not_void(array $x) {
    var_export($x);
}

function test_void_phpdoc() : void {
    // Should not cause infinite loop with null_casts_as_any_type
    echo strlen(test_void_real());
    echo expects_array_not_void(test_void_real()); // TODO: Currently, null_casts_as_any_type applies to all type casting logic, even real types, so there's no warning. Stricten this?
}
