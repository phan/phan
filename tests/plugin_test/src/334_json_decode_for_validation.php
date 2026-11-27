<?php

namespace NS334;

use function json_decode as decode;

// json_decode() is called without using the result to check that a string is JSON.
function isJson(string $other): bool {
    json_decode($other);
    if (json_last_error()) {
        return false;
    }
    return true;
}

function isJsonOrThrows(string $other): void {
    json_decode($other, false, 512, JSON_THROW_ON_ERROR);
}

// Still reported: the result is not used and nothing checks for an error.
function unused(string $body): void {
    json_decode($body);
}

// Also for an imported name.
function aliased(string $other): void {
    decode($other, false, 512, JSON_THROW_ON_ERROR);
}

// Still reported: the check is in a closure, which is not called by calling the function.
function checkInClosure(string $body): \Closure {
    json_decode($body);
    return static fn (): int => json_last_error();
}

// Still reported: `json_last_error(...)` creates a closure and checks nothing.
function checkAsClosure(string $body): \Closure {
    json_decode($body);
    return json_last_error(...);
}
