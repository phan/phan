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


function foo51c(int $value) {
    $b = true; // PhanUnusedVariable should be emitted

    do {
        if ($value == 1) {
            $b = 'a string';
            echo $b;
            break;
        }
    } while (false);
}

function foo51d(int $value) {
    $b = true; // PhanUnusedVariable should not be emitted

    do {
        if ($value == 1) {
            $b = 'a string';
            echo $b;
            break;
        }
    } while (false);
    var_export($b);
}
foo51(1);
foo51b();
foo51c(1);
foo51d(1);
