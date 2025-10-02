<?php
function test15(int $x) : ?int {
    return match($x) {
        1, 2 => throw new Exception(),
        3 => 'invalid',
    };
}
function test_suspicious_scalar(): string {
    return match(2) {
        1 => 'x',
        2 => 'y',
        3 => 'invalid',
    };
}
function test_suspicious_type(string $key): string {
    return match($key) {
        1 => 'x',
        null => 'y'
    };
}
function test_duplicates(int $key, int $other): int|string {
    return match($key) {
        1, 2, 2 => 'x',
        '1' => 'y',
        __LINE__ => 'z',
        1 + 1 => 'w',
        $other => 1,
        $other => 2,
        null, null => 3,
    };
}
