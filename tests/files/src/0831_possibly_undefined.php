<?php
function test(array $value) {
    $x = ['base' => 'value'];
    if (rand() % 2) {
        $x['value'] = $value;
    }
    '@phan-debug-var $x';
    if (is_null($x['value'])) {
        throw new Exception("Invalid");
    }
    '@phan-debug-var $x';
}
