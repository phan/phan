<?php declare(strict_types=1);

function test745SoftCast(string $s, bool $b, int $i, iterable $iter, float $f) : bool {
    if ($b) {
        // Should emit TypeMismatchReturn instead of TypeMismatchReturnReal because it won't throw an error at runtime.
        return $s;
    } elseif (rand() % 2 == 1) {
        return $i;
    } elseif (rand() % 2 == 0) {
        return $f;
    } elseif (rand() % 3 == 1) {
        return new stdClass();
    } elseif (rand() % 3 == 2) {
        return $iter;
    }
    // Should always warn
    return null;
}
