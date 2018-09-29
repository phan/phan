<?php

function foo51(int $value): bool {
    do {
        $a = true; // PhanUnusedVariable should not be emitted

        if ($value == 1) {
            break;
        }

        $a = false;
    } while (false);

    return $a;
}

function foo51b(): bool {
    foreach (['a', 'b'] as $x) {
        $a = true; // PhanUnusedVariable should not be emitted

        if ($x === 'b') {
            break;
        }

        $a = false;
    }

    return $a;
}

function foo51c(int $value): bool {
    $a = true; // PhanUnusedVariable should not be emitted
    $b = true; // PhanUnusedVariable should be emitted

    do {
        if ($value == 1) {
            $b = 'a string';
            echo $b;
            break;
        }

        $a = false;
    } while (false);

    return $a;
}
foo51(1);
foo51b();
foo51c(1);
