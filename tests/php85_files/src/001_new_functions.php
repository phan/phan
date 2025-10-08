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
function test_closure_get_current(): ?\Closure {
    return \Closure::getCurrent();
}

$current = test_closure_get_current();
if ($current instanceof \Closure) {
    echo 'closure';
}

// locale_is_right_to_left()
$is_rtl = locale_is_right_to_left('ar');

// Grapheme locale-aware functions
$grapheme_pos = grapheme_stripos('straße', 'ss', 0, 'de');
$grapheme_last_pos = grapheme_strripos('straße', 's', 0, 'de');
$grapheme_chunk = grapheme_stristr('straße', 'ss', false, 'de');
$grapheme_last_chunk = grapheme_strrpos('straße', 's', 0, 'de');
$grapheme_strstr_chunk = grapheme_strstr('straße', 'ss', false, 'de');

if ($grapheme_pos !== false) {
    echo $grapheme_pos;
}
if ($grapheme_last_pos !== false) {
    echo $grapheme_last_pos;
}
if (is_string($grapheme_chunk)) {
    echo $grapheme_chunk;
}
if ($grapheme_last_chunk !== false) {
    echo $grapheme_last_chunk;
}
if (is_string($grapheme_strstr_chunk)) {
    echo $grapheme_strstr_chunk;
}

// Directory resource-object interop
$directory = dir(__DIR__);
$entry = readdir($directory);
if ($entry !== false) {
    echo $entry;
}
rewinddir($directory);
closedir($directory);

try {
    $validatedEmail = filter_var('not-an-email', FILTER_VALIDATE_EMAIL, FILTER_THROW_ON_FAILURE);
} catch (\ValueError $e) {
    echo $e->getMessage();
}

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
