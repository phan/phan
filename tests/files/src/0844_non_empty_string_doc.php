<?php
/**
 * @param non-empty-string $x
 */
function expect_non_empty_string(string $x) {
    if ($x) {
        '@phan-debug-var $x';
        if ($x) {
            echo "The second check is guaranteed to be redundant\n";
        }
    } else {
        throw new InvalidArgumentException("Expected non-empty-string, got '$x'");
    }
}
/**
 * @param ?non-empty-string $x
 */
function expect_nullable_non_empty_string(?string $x) {
    if ($x) {
        if ($x) {
            echo "The second check is guaranteed to be redundant\n";
        }
    } else {
        echo strlen($x);
    }
}
// non-empty-string is either '' or '0'
expect_non_empty_string('');
expect_non_empty_string('0');
expect_non_empty_string('1');
expect_non_empty_string(null);

expect_nullable_non_empty_string('');
expect_nullable_non_empty_string('0');
expect_nullable_non_empty_string('1');
expect_nullable_non_empty_string(null);
