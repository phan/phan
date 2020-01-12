<?php
/**
 * @param non-empty-mixed $x
 */
function expect_non_empty_mixed($x) {
    if ($x) {
        '@phan-debug-var $x';
        if ($x) {
            echo "The second check is guaranteed to be redundant\n";
            if ($x === 0) {
                echo "Impossible - Somehow zero\n";
            }
        }
    } else {
        throw new InvalidArgumentException("Expected non-empty-mixed, got $x");
    }
}
/**
 * @param ?non-empty-mixed $x
 */
function expect_nullable_non_empty_mixed($x) {
    if ($x) {
        if ($x) {
            echo "The second check is guaranteed to be redundant\n";
        }
    } else {
        '@phan-debug-var $x';
        echo strlen($x);
    }
}
expect_non_empty_mixed(0);
expect_non_empty_mixed(1);
expect_non_empty_mixed(-1);
expect_non_empty_mixed(null);
expect_non_empty_mixed(false);
expect_non_empty_mixed('');
expect_non_empty_mixed('0');
expect_non_empty_mixed('1');

expect_nullable_non_empty_mixed(0);
expect_nullable_non_empty_mixed('0');
expect_nullable_non_empty_mixed(-1);
expect_nullable_non_empty_mixed(null);
