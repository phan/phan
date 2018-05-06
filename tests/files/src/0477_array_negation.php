<?php

function test_array_negation(array $example) {
    $v = rand() % 2 ? ['key' => 'value'] : null;
    if (!$v)  {
        echo strlen($v);
    }

    $v2 = rand() % 2 ? $example : null;
    if (!$v2)  {
        echo strlen($v2);
    }
}
