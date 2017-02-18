<?php

function expect_int267(int $x) {
}

function expect_string267(string $x) {
}

function foo267($x, $y) {
    if (is_int($x) && is_string($y)) {
        expect_string267($x);
        expect_int267($x);
        expect_string267($y);
        expect_int267($y);
    }
    if (is_string($x) && strlen($x) > 5) {
        expect_string267($x);
        expect_int267($x);
    }
    if (is_int($x) and ($x < 0)) {
        expect_string267($x);
        expect_int267($x);
    }
}
