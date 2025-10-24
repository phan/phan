<?php

function log_only(string $message): void {
    // intentionally does not exit
}

/**
 * @throws RuntimeException
 */
function maybe_throw_non_never(): void {
    if (random_int(0, 1) === 1) {
        throw new RuntimeException('failure');
    }
}

function test_try_catch_non_never(): void {
    try {
        maybe_throw_non_never();
        $value = 7;
    } catch (RuntimeException $e) {
        log_only($e->getMessage());
    }

    echo $value;
}
