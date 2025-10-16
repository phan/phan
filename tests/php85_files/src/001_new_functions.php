<?php

// Test new PHP 8.5 functions

// array_first() and array_last()
$arr = [1, 2, 3];
$first = array_first($arr);
$last = array_last($arr);

// get_error_handler() and get_exception_handler()
$error_handler = get_error_handler();
$exception_handler = get_exception_handler();

// Closure::getCurrent() - Returns the currently executing closure
// Test 1: Called from within a closure - should return the closure itself
$test_closure = function(): ?\Closure {
    return \Closure::getCurrent();
};

$result = $test_closure();
if ($result !== null) {
    echo 'Got current closure';
}

// Test 2: Called from regular function - throws Error
function test_not_in_closure(): never {
    try {
        \Closure::getCurrent();
        throw new \Exception('Should have thrown Error');
    } catch (\Error $e) {
        if (str_contains($e->getMessage(), 'not a closure')) {
            echo 'Correctly throws Error when not in closure';
        }
        throw $e;
    }
}

try {
    test_not_in_closure();
} catch (\Error $e) {
    // Expected error
}

// Test 3: Nested closures - should return the innermost closure, not the outer
$outer = function(): ?\Closure {
    $inner = function(): ?\Closure {
        return \Closure::getCurrent(); // Should return $inner, not $outer
    };
    $inner_result = $inner();
    // Verify it's the inner closure, not the outer one
    if ($inner_result !== null) {
        echo 'Got innermost closure';
    }
    return $inner_result;
};

$nested_result = $outer();

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
