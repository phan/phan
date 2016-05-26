<?php

$closure = function () use (&$a, &$b) {
    $a = 42;
    $b = 'string';
};

$closure();
