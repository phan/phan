<?php

try {
    $a = 42;
} catch (Exception $exception) {
    print $exception->getMessage();
}

print $a;

/*
try {
    $b = 2;
} catch (Exception $exception) {
    print $exception->getMessage();
}

print $b;
 */
