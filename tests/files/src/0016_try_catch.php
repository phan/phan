<?php

try {
    $a = 42;
} catch (Exception $exception) {
    print_r($exception);
}

print $a;

try {
    $b = 2;
} catch (Exception $exception) {
    print_r($exception);
}

print $b;

