<?php

/** @return int|array */
function f() {
    return 42;
}

$v = f() - 42;

/** @return array */
function g() {
    return [];
}

$v = g() - 42;
