<?php

function expect_never(string $message = ''): never {
    exit(1);
}

/**
 * @throws RuntimeException
 */
function maybe_throw(): void {
    if (random_int(0, 1) === 1) {
        throw new RuntimeException('fail');
    }
}

function test_try_catch_never_function(): void {
    try {
        maybe_throw();
        $value = 42;
    } catch (RuntimeException $e) {
        expect_never($e->getMessage());
    }

    echo $value;
}
