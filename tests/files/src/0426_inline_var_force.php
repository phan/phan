<?php

function example(int ...$values) {
    $data = ['key' => 'value'];
    $first = true;
    $sum = 0;
    '@phan-var int $prev';
    foreach ($values as $value) {
        if (!$first) {
            $sum += $prev * $value;
        } else {
            $first = false;
            $prev = $value;
        }
    }
    return $sum;
}

function example2(int ...$values) {
    $data = ['key' => 'value'];
    $first = true;
    $sum = 0;
    <<<'PHAN'
start text
  @phan-example-annotation array<int,string> description :
other text
PHAN;
    '@phan-param a value';
    '@phan-var-force int $prev';
    foreach ($values as $value) {
        if (!$first) {
            $sum += $prev * $value;
        } else {
            $first = false;
            $prev = $value;
        }
    }
    return $sum;
}
