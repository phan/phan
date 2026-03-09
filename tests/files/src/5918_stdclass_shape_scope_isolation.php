<?php

function scope_a_5918() {
    $data = (object)[];
    $data->status = 3;
}

function scope_b_5918() {
    $data = (object)['status' => 'string'];
    $var = $data->status;
    '@phan-debug-var $var';
}
