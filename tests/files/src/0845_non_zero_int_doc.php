<?php
/**
 * @param non-zero-int $x
 */
function expect_non_zero_int(int $x) {
    if ($x) {
        '@phan-debug-var $x';
        if ($x) {
            echo "The second check is guaranteed to be redundant\n";
            if ($x === 0) {
                echo "Impossible - Somehow zero\n";
            }
        }
    } else {
        throw new InvalidArgumentException("Expected non-zero-int, got $x");
    }
}
/**
 * @param ?non-zero-int $x
 */
function expect_nullable_non_zero_int(?int $x) {
    if ($x) {
        if ($x) {
            echo "The second check is guaranteed to be redundant\n";
        }
    } else {
        '@phan-debug-var $x';
        echo strlen($x);
    }
}
expect_non_zero_int(0);
expect_non_zero_int(1);
expect_non_zero_int(-1);
expect_non_zero_int(null);

expect_nullable_non_zero_int(0);
expect_nullable_non_zero_int(1);
expect_nullable_non_zero_int(-1);
expect_nullable_non_zero_int(null);
/**
 * @param non-zero-int $x
 */
function expect_non_zero_int_alternative(int $x) {
    if ($x > 0) {
        if (!$x) {
            echo "The second check is guaranteed to be redundant\n";
        }
    }
}
