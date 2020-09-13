<?php

$closure = function () use${0}(&$a, &$b) {
    $a = 42;
    $b = 'string';
};

$closure();
