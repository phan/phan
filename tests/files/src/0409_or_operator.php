<?php

/** @param int|string $scalar */
function example409($scalar) {
    if (is_string($scalar) || strlen($scalar) > 100) {  // This is a bug, the right-hand side would be called for integers
        echo "Scalar is a long string\n";
    }
}

/** @param int|string $scalar */
function example409b($scalar) {
    if (!(is_string($scalar) && intdiv($scalar, 2) < 42)) {  // This is a bug, the right-hand side would be called on a string
        echo "Scalar is a long string\n";
    }
}

// Test long chain of `||` operators
/** @param int|string $scalar */
function example409c($scalar) {
    if (($scalar = rand() % 10) || strlen($scalar) > 100 || count($scalar) > 1) {  // This is a bug, the scalar is reassigned to be an integer
        echo "Condition met\n";
    }
}

/** @param int|string $scalar */
function example409Reassign($scalar) {
    if (is_string($scalar) || ($scalar = 'a string')) {
        echo "Scalar is now a string\n";
        echo intdiv($scalar, 2);  // should warn because the condition ensures $scalar becomes a string
    }
}

/** @param int|string $scalar */
function exampleReassign($scalar) {
    if (is_string($scalar) || ($scalar = false)) {
        echo intdiv($scalar, 2);  // should warn because the condition ensures $scalar becomes a string. The inside of the loop won't be executed with `false`.
    }
}
