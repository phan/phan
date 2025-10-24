<?php

class Aborter {
    public static function terminate(string $message = ''): never {
        exit(1);
    }
}

/**
 * @throws LogicException
 */
function maybe_throw_static(): void {
    if (random_int(0, 1) === 1) {
        throw new LogicException('boom');
    }
}

function test_try_catch_never_static(): void {
    try {
        maybe_throw_static();
        $result = 99;
    } catch (LogicException $e) {
        Aborter::terminate($e->getMessage());
    }

    echo $result;
}
