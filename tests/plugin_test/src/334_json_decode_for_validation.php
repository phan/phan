<?php

namespace NS334;

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
