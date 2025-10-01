<?php

// Test new PHP 8.5 functions

// array_first() and array_last()
$arr = [1, 2, 3];
$first = array_first($arr);
$last = array_last($arr);

// get_error_handler() and get_exception_handler()
$error_handler = get_error_handler();
$exception_handler = get_exception_handler();

// Closure::getCurrent()
function test_closure_get_current() {
    return Closure::getCurrent();
}

// locale_is_right_to_left()
$is_rtl = locale_is_right_to_left('ar');

// Type checks - these should not produce warnings
if (is_int($first)) {
    echo $first;
}
if (is_callable($error_handler)) {
    $error_handler(E_USER_WARNING, 'test', __FILE__, __LINE__);
}
if ($exception_handler instanceof Closure) {
    echo 'Has exception handler';
}
