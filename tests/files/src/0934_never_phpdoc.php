<?php
namespace NS934;

/**
 * @param mixed $value
 * @return never
 */
function shouldReturnNever($value) {
    // these are all invalid
    if (rand(0, 1)) {
        return $value;
    } elseif ($value) {
        return;
    }
    return null;
}

/**
 * @return never this is valid
 */
function neverExceptional(): void {
    throw new \RuntimeException();
}

/**
 * @param mixed $value
 * @return void
 */
function shouldReturnVoid($value) {
    return $value;  // should warn
}
