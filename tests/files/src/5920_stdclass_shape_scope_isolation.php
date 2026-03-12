<?php

// Regression test for issue #4789: stdClass shape types should not leak
// across unrelated function scopes via the global dynamic property accumulator.

function scope_a_5920(): void {
    $data = (object)[];
    $data->status = 3;
}

function scope_b_5920(): void {
    $data = (object)['status' => 'string'];
    $var = $data->status;
    '@phan-debug-var $var';
}
