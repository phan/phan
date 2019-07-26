<?php

namespace ComplexIfCondition;

/** @param ?string $x */
function test($x) {
    if (!(false === is_string($x))) {
        echo strlen($x);  // should not emit PhanTypeMismatchArgumentInternal
    }
}
/** @param ?string $x */
function test2($x) {
    if (false !== is_string($x)) {
        echo strlen($x);  // should not emit PhanTypeMismatchArgumentInternal
    }
}
/** @param ?string $x */
function test3($x) {
    if (!(false !== is_string($x))) {
        echo strlen($x);  // should emit PhanTypeMismatchArgumentInternal
    }
}
